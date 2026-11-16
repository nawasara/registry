<?php

namespace Nawasara\Registry\Services;

use Illuminate\Support\Facades\DB;
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
 * SIMAS Hebat is the official source (keputusan 16 November 2026). When it
 * places the person in another OPD than the one on file, the membership
 * follows SIMAS without asking; the move is logged and reported back so the
 * screen can say so. EmployeeSyncService applies the same rule at login.
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
        $match = $this->matcher->matchSimas($data);

        return $data + [
            'opd' => $match['opd'],
            'sub_unit' => $match['sub_unit'],
        ];
    }

    /**
     * Assign $user as PIC of $asset.
     *
     * @param  int|null  $opdId          OPD the admin chose; used only when SIMAS
     *                                   gives no recognised OPD (SIMAS wins otherwise)
     * @param  bool  $allowWithoutSimas  proceed when SIMAS is unreachable (existing OPD only)
     * @return array{employee: ?Employee, opd: Opd, unit: ?Unit, simas: ?array, moved_from: ?Opd}
     *
     * @throws SimasHebatException   when SIMAS fails and $allowWithoutSimas is false
     */
    public function assign(
        Asset $asset,
        $user,
        ?int $opdId = null,
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

        // SIMAS is the official source: when it names a known OPD, that wins
        // over both the admin's pick and the membership on file.
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

        return DB::transaction(function () use ($asset, $user, $opd, $simasOpd, $unit, $simasData, $nip, $employee) {
            if ($simasData) {
                // $simasOpd, not $opd: when SIMAS names an OPD the registry
                // does not know, the admin's pick must not be recorded as what
                // SIMAS said, or the "OPD belum dikenal" flag disappears.
                $employee = $this->employees()->writeEmployee(
                    $user, $nip, $simasData, $simasOpd, $unit, EmployeeSyncService::SOURCE_PIC,
                );
            }

            $membership = Membership::where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();
            $movedFrom = null;

            if ($membership && (int) $membership->opd_id !== (int) $opd->id) {
                $movedFrom = Opd::find($membership->opd_id);
            }

            $membership = Membership::updateOrCreate(
                ['user_id' => $user->getAuthIdentifier()],
                ['opd_id' => $opd->id, 'aktif' => true],
            );

            if ($movedFrom && $simasData) {
                $this->employees()->logMove(
                    $membership, $movedFrom, $opd, (string) $nip, $simasData, EmployeeSyncService::SOURCE_PIC,
                );
            }

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
                'moved_from' => $movedFrom,
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
     * The admin MAY correct the display name. Prefix matching can pick the
     * wrong boundary between the OPD and its sub-unit, and if there is no way
     * to fix that on the spot, a mistake becomes permanent.
     *
     * ⚠️ But the correction touches `name` ONLY. `simas_name` stays exactly as
     * the source wrote it, because that is what the next reply is matched
     * against. Letting a tidy-up change the matching key would mean the next
     * person from the same OPD is no longer recognised, and nothing would say
     * why.
     *
     * @param  array  $simasData  the reply that prompted this, stored in the audit trail
     * @param  string|null  $name  admin's correction; the raw unit_kerja when null
     * @param  string|null  $simasName  the OPD portion of unit_kerja; the whole
     *                                  string when the admin did not split it
     */
    public function createOpdFromSimas(
        array $simasData,
        string $code,
        ?int $parentId = null,
        ?string $name = null,
        ?string $simasName = null,
    ): Opd {
        $unitKerja = trim((string) ($simasData['unit_kerja'] ?? ''));

        if ($unitKerja === '') {
            throw new \InvalidArgumentException(
                'OPD hanya dapat dibuat dari jawaban SIMAS Hebat, dan jawabannya kosong.'
            );
        }

        $simasName = trim((string) ($simasName ?: $unitKerja));
        $adminName = trim((string) $name);
        // Nama tampilan dirapikan bila admin tidak mengoreksinya — unit_kerja
        // SIMAS ditulis kapital semua. simas_name tetap persis sumbernya.
        $name = $adminName !== '' ? $adminName : \Nawasara\Registry\Support\OpdName::tidy($simasName);

        $opd = Opd::create([
            'code' => $code,
            'name' => $name,
            'simas_name' => $simasName,
            'parent_id' => $parentId,
        ]);

        activity()
            ->performedOn($opd)
            ->withProperties([
                'alasan' => 'unit_kerja tidak dikenali saat penetapan PIC',
                'simas' => $this->auditableSimas($simasData),
                // Recorded so a later reviewer can see the admin adjusted it,
                // and compare against what the source actually said.
                // Perapian huruf otomatis (OpdName::tidy) BUKAN koreksi admin.
                'dikoreksi_admin' => ($adminName !== '' && $adminName !== $unitKerja) || $simasName !== $unitKerja,
                'diambil_pada' => now()->toIso8601String(),
            ])
            ->log('OPD dibuat dari SIMAS Hebat');

        return $opd;
    }

    /**
     * Create a sub-unit under an OPD, again only from a SIMAS reply.
     *
     * Same split as above: `$name` is the admin's correction, `$subUnit` is
     * the raw remainder from `unit_kerja` and becomes `simas_name`.
     */
    public function createUnitFromSimas(
        Opd $opd,
        array $simasData,
        string $subUnit,
        ?string $name = null,
    ): Unit {
        $subUnit = trim($subUnit);

        if ($subUnit === '') {
            throw new \InvalidArgumentException(
                'Unit hanya dapat dibuat dari jawaban SIMAS Hebat.'
            );
        }

        $unit = $this->findUnit($opd, $subUnit) ?? Unit::firstOrCreate(
            ['opd_id' => $opd->id, 'simas_name' => $subUnit],
            ['name' => trim((string) ($name ?: $subUnit))],
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
        return $this->employees()->findUnit($opd, $subUnit);
    }

    private function employees(): EmployeeSyncService
    {
        return new EmployeeSyncService($this->simas, $this->matcher);
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
