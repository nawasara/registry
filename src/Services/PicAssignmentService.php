<?php

namespace Nawasara\Registry\Services;

use Illuminate\Support\Facades\DB;
use Nawasara\Registry\Exceptions\PicMismatchException;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Unit;

/**
 * The only lawful way to put someone in charge of an asset.
 *
 * Assigning a PIC is not just writing a foreign key; it is the moment the
 * person's employment data gets refreshed. Nawasara is never told about a
 * transfer, so a membership recorded once sits there forever: somebody who
 * moved from Kominfo to Dinas Pendidikan last month still carries Kominfo's
 * scope, and since ScopedToOpd filters rows by opd_id, that is a data leak
 * rather than a stale label.
 *
 * So one assignment does three things:
 *   1. refresh employment data from SIMAS Hebat
 *   2. create the OPD and sub-unit when the registry does not have them
 *   3. record the membership and write the asset
 *
 * A UI component alone could not guarantee this. Every screen ends at the same
 * `Asset` row, and anything writing it another way (a seeder, a sync job, a
 * package that has not heard of this rule) would slip past a check that lived
 * in Blade. The rule lives here so the data cannot be reached around it.
 *
 * See docs/plan/plan-sinkronisasi-pegawai-simashebat.md §4b and §4d.
 */
class PicAssignmentService
{
    public function __construct(
        private ?SimasHebatClient $simas = null,
        private ?UnitKerjaMatcher $matcher = null,
    ) {
        $this->simas ??= SimasHebatClient::fromVault();
        $this->matcher ??= new UnitKerjaMatcher;
    }

    /**
     * Look a NIP up without changing anything.
     *
     * Used by the search box, so the admin sees who they are about to appoint
     * before committing to it.
     *
     * @return array{nama_lengkap: string, nama_jabatan: ?string, jenis_jabatan: ?string, unit_kerja: ?string, jenis_kepegawaian: ?string, opd: ?Opd, sub_unit: ?string}
     *
     * @throws SimasHebatException
     */
    public function lookup(string $nip): array
    {
        if (! $this->simas) {
            throw SimasHebatException::notConfigured();
        }

        $data = $this->simas->pegawai($nip);
        $match = $this->matcher->match((string) $data['unit_kerja']);

        return $data + [
            'opd' => $match['opd'],
            'sub_unit' => $match['sub_unit'],
        ];
    }

    /**
     * Assign $user as PIC of $asset.
     *
     * @param  int|null  $opdId          OPD the admin chose; null means "use what SIMAS says"
     * @param  bool  $acceptMismatch     admin has seen the difference and chose to move them
     * @param  bool  $allowWithoutSimas  proceed when SIMAS is unreachable (existing OPD only)
     * @return array{employee: ?Employee, opd: Opd, unit: ?Unit, simas: ?array}
     *
     * @throws PicMismatchException  when SIMAS places them elsewhere and $acceptMismatch is false
     * @throws SimasHebatException   when SIMAS fails and $allowWithoutSimas is false
     */
    public function assign(
        Asset $asset,
        $user,
        ?int $opdId = null,
        bool $acceptMismatch = false,
        bool $allowWithoutSimas = false,
    ): array {
        $employee = Employee::firstWhere('user_id', $user->getAuthIdentifier());
        $nip = $employee?->nip ?? $this->nipFromKeycloak($user);

        $simasData = null;
        $simasOpd = null;
        $unit = null;

        if ($nip !== null && $this->simas) {
            try {
                $simasData = $this->lookup($nip);
                $simasOpd = $simasData['opd'];
            } catch (SimasHebatException $e) {
                if (! $allowWithoutSimas) {
                    throw $e;
                }
                // Assigning to an OPD that already exists may go ahead on
                // previously verified data; creating a new one may not, and
                // that is enforced below by $simasData staying null.
            }
        }

        $recordedOpdId = $opdId
            ?? Membership::where('user_id', $user->getAuthIdentifier())
                ->where('aktif', true)
                ->value('opd_id');

        // SIMAS disagrees with what is on file: stop and let the admin decide.
        if ($simasOpd && $recordedOpdId && (int) $recordedOpdId !== (int) $simasOpd->id && ! $acceptMismatch) {
            throw new PicMismatchException(
                recorded: Opd::findOrFail($recordedOpdId),
                fromSimas: $simasOpd,
                simasUnitKerja: (string) ($simasData['unit_kerja'] ?? ''),
            );
        }

        $opd = $simasOpd
            ?? ($recordedOpdId ? Opd::findOrFail($recordedOpdId) : null);

        if (! $opd) {
            throw new \InvalidArgumentException(
                'Tidak ada OPD yang dapat dipakai. Pilih OPD atau pastikan SIMAS Hebat dapat dihubungi.'
            );
        }

        if ($simasData && ($simasData['sub_unit'] ?? null)) {
            $unit = $this->findUnit($opd, $simasData['sub_unit']);
        }

        return DB::transaction(function () use ($asset, $user, $opd, $unit, $simasData, $nip, $employee) {
            if ($simasData) {
                $employee = $this->refreshEmployee($user, $nip, $simasData, $unit, $employee);
            }

            Membership::updateOrCreate(
                ['user_id' => $user->getAuthIdentifier()],
                ['opd_id' => $opd->id, 'aktif' => true],
            );

            $asset->forceFill([
                'opd_id' => $opd->id,
                'pj_user_id' => $user->getAuthIdentifier(),
            ])->save();

            activity()
                ->performedOn($asset)
                ->withProperties([
                    'nip' => $nip,
                    'opd' => $opd->name,
                    'unit' => $unit?->name,
                    'simas' => $simasData ? $this->auditableSimas($simasData) : null,
                    // Kept even when false, and NOT run through array_filter:
                    // `false` is exactly the value a reviewer needs to see, and
                    // filtering would drop it as though it were absent.
                    'diverifikasi_simas' => $simasData !== null,
                ])
                ->log('Penanggung jawab ditetapkan');

            return [
                'employee' => $employee,
                'opd' => $opd,
                'unit' => $unit,
                'simas' => $simasData,
            ];
        });
    }

    /**
     * Create an OPD from a SIMAS reply.
     *
     * ⚠️ Requires $simasData. Nothing here may be created from an admin's
     * memory: an OPD is a permission container, and one born without a
     * recorded reason cannot be reviewed by anyone later.
     *
     * @param  array  $simasData  the reply that prompted this, stored in the audit trail
     */
    public function createOpdFromSimas(array $simasData, string $code, ?int $parentId = null): Opd
    {
        $unitKerja = trim((string) ($simasData['unit_kerja'] ?? ''));

        if ($unitKerja === '') {
            throw new \InvalidArgumentException(
                'OPD hanya dapat dibuat dari jawaban SIMAS Hebat, dan jawabannya kosong.'
            );
        }

        $opd = Opd::create([
            'code' => $code,
            'name' => $unitKerja,
            'simas_name' => $unitKerja,
            'parent_id' => $parentId,
        ]);

        activity()
            ->performedOn($opd)
            ->withProperties([
                'alasan' => 'unit_kerja tidak dikenali saat penetapan PIC',
                'simas' => $this->auditableSimas($simasData),
                'diambil_pada' => now()->toIso8601String(),
            ])
            ->log('OPD dibuat dari SIMAS Hebat');

        return $opd;
    }

    /**
     * Create a sub-unit under an OPD, again only from a SIMAS reply.
     */
    public function createUnitFromSimas(Opd $opd, array $simasData, string $subUnit): Unit
    {
        $subUnit = trim($subUnit);

        if ($subUnit === '') {
            throw new \InvalidArgumentException(
                'Unit hanya dapat dibuat dari jawaban SIMAS Hebat.'
            );
        }

        $unit = Unit::firstOrCreate(
            ['opd_id' => $opd->id, 'simas_name' => $subUnit],
            ['name' => $subUnit],
        );

        if ($unit->wasRecentlyCreated) {
            activity()
                ->performedOn($unit)
                ->withProperties([
                    'alasan' => 'sub-unit belum terdaftar saat penetapan PIC',
                    'opd' => $opd->name,
                    'simas' => $this->auditableSimas($simasData),
                    'diambil_pada' => now()->toIso8601String(),
                ])
                ->log('Unit dibuat dari SIMAS Hebat');
        }

        return $unit;
    }

    /** Existing sub-unit for this OPD, or null when it has not been created yet. */
    public function findUnit(Opd $opd, string $subUnit): ?Unit
    {
        return Unit::where('opd_id', $opd->id)
            ->where('simas_name', trim($subUnit))
            ->first();
    }

    /**
     * Write the employment fields back.
     *
     * ⚠️ `nip` is only ever written when the row does not have one yet. It is
     * the key that links this person to their Keycloak account, where the
     * username IS the NIP, so changing it cuts an account loose from its owner
     * with no simple way back. A NIP that is genuinely wrong gets fixed at the
     * source, not here.
     */
    protected function refreshEmployee($user, ?string $nip, array $simasData, ?Unit $unit, ?Employee $employee): Employee
    {
        $employee ??= new Employee(['user_id' => $user->getAuthIdentifier()]);

        $employee->fill([
            'nama_jabatan' => $simasData['nama_jabatan'],
            'jenis_jabatan' => $simasData['jenis_jabatan'],
            'jenis_kepegawaian' => $simasData['jenis_kepegawaian'],
            'simas_unit_kerja' => $simasData['unit_kerja'],
            'simas_synced_at' => now(),
        ]);

        if ($unit) {
            $employee->unit_id = $unit->id;
        }

        if ($employee->nip === null && $nip !== null) {
            $employee->nip = $nip;
        }

        $employee->user_id = $user->getAuthIdentifier();
        $employee->save();

        return $employee;
    }

    /**
     * The NIP for someone we have no employment row for yet.
     *
     * Keycloak's username is the NIP on every production account, which makes
     * it the one field that is always there; the `nip` attribute is present on
     * most but not all, so it is the cross-check rather than the source.
     */
    protected function nipFromKeycloak($user): ?string
    {
        $username = trim((string) ($user->username ?? ''));

        return preg_match('/^\d{18}$/', $username) === 1 ? $username : null;
    }

    /**
     * What goes into the audit trail: the reply as it arrived.
     *
     * Stored raw so that six months from now somebody can judge whether an OPD
     * was right without calling the API again, even if SIMAS has changed since.
     */
    protected function auditableSimas(array $simasData): array
    {
        return [
            'nama_lengkap' => $simasData['nama_lengkap'] ?? null,
            'nama_jabatan' => $simasData['nama_jabatan'] ?? null,
            'jenis_jabatan' => $simasData['jenis_jabatan'] ?? null,
            'unit_kerja' => $simasData['unit_kerja'] ?? null,
            'jenis_kepegawaian' => $simasData['jenis_kepegawaian'] ?? null,
        ];
    }
}
