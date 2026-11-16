<?php

namespace Nawasara\Registry\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Unit;

/**
 * Keeps a user's employment data and OPD membership in line with SIMAS Hebat.
 *
 * SIMAS Hebat is the official source (keputusan 16 November 2026): when it
 * says someone works at another OPD, their membership MOVES, and someone with
 * no membership yet gets one, both with no approval step. Every change is
 * written to the audit trail so it can be traced.
 *
 * Three callers, one rule set:
 *   - login   QueueEmployeeSyncOnLogin, at most once per interval
 *   - manual  the "Perbarui dari SIMAS" action on the Data Pegawai page
 *   - pic     PicAssignmentService, which writes through writeEmployee()
 *
 * What this deliberately does NOT do:
 *   - grant roles. A membership alone shows nothing: what a user may do with
 *     an OPD's data still comes from their roles (Pengajuan Petugas).
 *   - reactivate a membership an admin deactivated.
 *   - move a membership when `skpd` matches no OPD in the registry. There is
 *     nowhere to move it to; the row is flagged instead (simas_opd_id null)
 *     so an admin can register the OPD or fill its simas_name.
 *   - create OPDs or units. Those stay admin actions (PicAssignmentService).
 *   - move assets. An asset belongs to an OPD, not to the person looking
 *     after it; a PIC who moves leaves the asset where it is.
 */
class EmployeeSyncService
{
    public const SOURCE_LOGIN = 'login';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PIC = 'pic';

    /** Set when SIMAS answers with its firewall page; every caller backs off. */
    public const BLOCKED_CACHE_KEY = 'nawasara-registry:simas-blocked';

    public function __construct(
        private ?SimasHebatClient $simas = null,
        private ?UnitKerjaMatcher $matcher = null,
    ) {
        $this->matcher ??= new UnitKerjaMatcher;
    }

    /**
     * The NIP for a user: the stored one first, else a Keycloak username that
     * is 18 digits (the username IS the NIP on every ASN account).
     */
    public static function nipFor($user): ?string
    {
        $stored = Employee::where('user_id', $user->getAuthIdentifier())->value('nip');
        if ($stored) {
            return $stored;
        }

        $username = trim((string) ($user->username ?? ''));

        return preg_match('/^\d{18}$/', $username) === 1 ? $username : null;
    }

    /**
     * Ask SIMAS about this user and apply the answer.
     *
     * @return array{status: string, employee: ?Employee, opd: ?Opd, moved_from: ?Opd, message: string}
     *
     * @throws SimasHebatException  when SIMAS cannot be asked (not configured,
     *                              unreachable, blocked, token rejected). The
     *                              failure is recorded on the row first.
     */
    public function sync($user, string $source): array
    {
        $nip = static::nipFor($user);

        if ($nip === null) {
            return $this->result('no_nip', null, null, null, 'Akun ini tidak punya NIP, jadi tidak dapat dicari di SIMAS Hebat.');
        }

        if (! $this->simas) {
            throw SimasHebatException::notConfigured();
        }

        if (Cache::has(self::BLOCKED_CACHE_KEY)) {
            throw SimasHebatException::blocked();
        }

        $started = microtime(true);

        try {
            $data = $this->simas->pegawai($nip);
        } catch (SimasHebatException $e) {
            if ($e->errorCode === 'simas_not_found') {
                $employee = $this->recordAttempt($user, $nip, 'not_found', null, $source);
                Log::info('[registry] employee sync: NIP not found in SIMAS', ['user_id' => $user->getAuthIdentifier(), 'nip' => $nip, 'source' => $source]);

                return $this->result('not_found', $employee, null, null, 'NIP ini tidak ditemukan di SIMAS Hebat. Keanggotaan OPD tidak diubah.');
            }

            if ($e->errorCode === 'simas_blocked') {
                Cache::put(self::BLOCKED_CACHE_KEY, true, now()->addHour());
            }

            $this->recordAttempt($user, $nip, 'error', $e->getMessage(), $source);
            Log::warning('[registry] employee sync failed', ['user_id' => $user->getAuthIdentifier(), 'nip' => $nip, 'source' => $source, 'code' => $e->errorCode]);

            throw $e;
        }

        $result = $this->apply($user, $nip, $data, $source);

        Log::info('[registry] employee sync completed', [
            'user_id' => $user->getAuthIdentifier(),
            'nip' => $nip,
            'source' => $source,
            'opd_matched' => $result['opd'] !== null,
            'moved' => $result['moved_from'] !== null,
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $result;
    }

    /**
     * Apply a SIMAS reply that has already been fetched.
     *
     * @return array{status: string, employee: ?Employee, opd: ?Opd, moved_from: ?Opd, message: string}
     */
    public function apply($user, string $nip, array $data, string $source): array
    {
        $match = $this->matcher->matchSimas($data);
        $opd = $match['opd'];
        $unit = $opd && $match['sub_unit'] ? $this->findUnit($opd, $match['sub_unit']) : null;

        return DB::transaction(function () use ($user, $nip, $data, $source, $opd, $unit) {
            $employee = $this->writeEmployee($user, $nip, $data, $opd, $unit, $source);

            $movedFrom = null;
            $created = false;
            $membership = Membership::where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();

            if ($opd && ! $membership) {
                $membership = Membership::create([
                    'user_id' => $user->getAuthIdentifier(),
                    'opd_id' => $opd->id,
                    'aktif' => true,
                ]);
                $created = true;
                $this->logMove($membership, null, $opd, $nip, $data, $source, 'Keanggotaan OPD dibuat dari SIMAS Hebat');
            } elseif ($opd && (int) $membership->opd_id !== (int) $opd->id) {
                $movedFrom = Opd::find($membership->opd_id);
                $membership->forceFill(['opd_id' => $opd->id])->save();
                $this->logMove($membership, $movedFrom, $opd, $nip, $data, $source);
            }

            if (! $opd) {
                $message = 'Data diperbarui, tetapi OPD "'.($data['skpd'] ?? $data['unit_kerja'] ?? '-').'" belum dikenal Registry. Keanggotaan OPD tidak diubah.';
            } elseif ($created) {
                $message = "Data diperbarui. Pegawai ini kini anggota {$opd->name} mengikuti SIMAS Hebat.";
            } elseif ($movedFrom) {
                $message = "Data diperbarui. Keanggotaan dipindah dari {$movedFrom->name} ke {$opd->name} mengikuti SIMAS Hebat.";
            } else {
                $message = 'Data kepegawaian diperbarui dari SIMAS Hebat.';
            }

            return $this->result('ok', $employee, $opd, $movedFrom, $message);
        });
    }

    /**
     * Write the employment fields from a SIMAS reply.
     *
     * ⚠️ `nip` is only written when the row has none. It links the account to
     * its Keycloak identity; a wrong one is fixed at the source, not here.
     */
    public function writeEmployee($user, ?string $nip, array $data, ?Opd $opd, ?Unit $unit, string $source): Employee
    {
        $employee = Employee::firstOrNew(['user_id' => $user->getAuthIdentifier()]);

        $employee->fill([
            'simas_nama' => $data['nama_lengkap'] ?? null,
            'nama_jabatan' => $data['nama_jabatan'] ?? null,
            'jenis_jabatan' => $data['jenis_jabatan'] ?? null,
            'jenis_kepegawaian' => $data['jenis_kepegawaian'] ?? null,
            'simas_unit_kerja' => $data['unit_kerja'] ?? null,
            'simas_skpd' => $data['skpd'] ?? null,
            'simas_unit' => $data['unit'] ?? null,
            'simas_opd_id' => $opd?->id,
            'simas_status' => 'ok',
            'simas_error' => null,
            'simas_source' => $source,
            'simas_synced_at' => now(),
            'simas_checked_at' => now(),
        ]);

        if ($unit) {
            $employee->unit_id = $unit->id;
        } elseif ($employee->unit_id && $opd
            && (int) Unit::whereKey($employee->unit_id)->value('opd_id') !== (int) $opd->id) {
            // A unit from the previous OPD is wrong after a move. Cleared
            // rather than kept when the new one is not registered yet.
            $employee->unit_id = null;
        }

        if ($employee->nip === null && $nip !== null) {
            $employee->nip = $nip;
        }

        $employee->save();

        return $employee;
    }

    /** Existing sub-unit for this OPD, or null when not registered yet. */
    public function findUnit(Opd $opd, string $subUnit): ?Unit
    {
        // Compared normalised: units registered from the old prefix match
        // hold the normalised remainder ("BAGIAN UMUM SEKRETARIAT DAERAH"),
        // units registered from the split reply hold it raw ("BAGIAN UMUM -
        // SEKRETARIAT DAERAH"). An OPD has a handful of units, so comparing
        // in PHP costs nothing.
        $needle = UnitKerjaMatcher::normalize($subUnit);

        if ($needle === '') {
            return null;
        }

        return Unit::where('opd_id', $opd->id)->get()
            ->first(fn (Unit $u) => UnitKerjaMatcher::normalize((string) $u->simas_name) === $needle);
    }

    /**
     * Audit entry for a membership that followed SIMAS.
     *
     * Logged on the membership and named after the person, so it shows up
     * both in the activity log and when someone asks "why can this user no
     * longer see OPD X".
     */
    public function logMove(Membership $membership, ?Opd $from, Opd $to, string $nip, array $data, string $source, string $description = 'Keanggotaan OPD dipindah mengikuti SIMAS Hebat'): void
    {
        activity()
            ->performedOn($membership)
            ->withProperties([
                'user_id' => $membership->user_id,
                'nip' => $nip,
                'dari' => $from?->name,
                'ke' => $to->name,
                'sumber' => $source,
                'simas' => [
                    'skpd' => $data['skpd'] ?? null,
                    'unit' => $data['unit'] ?? null,
                    'unit_kerja' => $data['unit_kerja'] ?? null,
                ],
            ])
            ->log($description);
    }

    /** Remember a lookup that did not produce data, without touching the old data. */
    protected function recordAttempt($user, string $nip, string $status, ?string $error, string $source): Employee
    {
        $employee = Employee::firstOrNew(['user_id' => $user->getAuthIdentifier()]);

        if ($employee->nip === null) {
            $employee->nip = $nip;
        }

        $employee->forceFill([
            'simas_status' => $status,
            'simas_error' => $error ? mb_substr($error, 0, 250) : null,
            'simas_source' => $source,
            'simas_checked_at' => now(),
        ])->save();

        return $employee;
    }

    private function result(string $status, ?Employee $employee, ?Opd $opd, ?Opd $movedFrom, string $message): array
    {
        return [
            'status' => $status,
            'employee' => $employee,
            'opd' => $opd,
            'moved_from' => $movedFrom,
            'message' => $message,
        ];
    }
}
