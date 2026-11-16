<?php

namespace Nawasara\Registry\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Registry\Models\Employee;

/**
 * Copies SIMAS data that Keycloak already fetched into Nawasara, for everyone
 * who logged in through SSO to ANY app (Portal ASN, godash, bansos, ...), not
 * only to Nawasara.
 *
 * Keycloak's authenticator (2.4.0) asks SIMAS Hebat on every login and stores
 * the reply as `simas_*` user attributes. So Nawasara does not call SIMAS
 * here at all: it reads recent LOGIN events from Keycloak, and for each
 * person who also has a Nawasara account copies those attributes through
 * EmployeeSyncService, the same rules as every other path (membership follows
 * SIMAS).
 *
 * The window overlaps on purpose (15 minutes, run every 5). Keycloak fills
 * the attributes on a background thread a moment AFTER the LOGIN event, so a
 * run that lands in between reads the old values; the next run sees the same
 * event again and picks the new ones up. Rows already as new as Keycloak's
 * `simas_synced_at` are skipped, which keeps the overlap free.
 */
class SsoLoginImporter
{
    private const PAGE = 200;

    private const MAX_PAGES = 10;

    public function __construct(
        private KeycloakClient $keycloak,
        private EmployeeSyncService $sync,
    ) {}

    /**
     * @return array{events: int, people: int, applied: int, unchanged: int, skipped: int, moved: int}
     */
    public function run(int $windowMinutes = 15): array
    {
        $counts = ['events' => 0, 'people' => 0, 'applied' => 0, 'unchanged' => 0, 'skipped' => 0, 'moved' => 0];

        if (! $this->keycloak->isConfigured()) {
            return $counts;
        }

        $from = (string) (int) (now()->subMinutes($windowMinutes)->getTimestamp() * 1000);
        $kcIds = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $events = $this->keycloak->getEvents([
                'type' => 'LOGIN',
                'dateFrom' => $from,
                'first' => $page * self::PAGE,
                'max' => self::PAGE,
            ]);

            foreach ($events as $event) {
                if (! empty($event['userId'])) {
                    $kcIds[$event['userId']] = true;
                }
            }

            $counts['events'] += count($events);

            if (count($events) < self::PAGE) {
                break;
            }
        }

        if ($kcIds === []) {
            return $counts;
        }

        $userModel = config('auth.providers.users.model');
        $users = $userModel::whereIn('keycloak_id', array_keys($kcIds))->get();
        $counts['people'] = $users->count();

        foreach ($users as $user) {
            try {
                $outcome = $this->importOne($user);
            } catch (\Throwable $e) {
                // One bad account must not stop the rest of the batch.
                Log::warning('[registry] sso import failed for one user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                $outcome = 'skipped';
            }

            $counts[$outcome === 'moved' ? 'applied' : $outcome]++;
            if ($outcome === 'moved') {
                $counts['moved']++;
            }
        }

        return $counts;
    }

    /** @return 'applied'|'moved'|'unchanged'|'skipped' */
    public function importOne($user): string
    {
        $kcUser = $this->keycloak->getUser((string) $user->keycloak_id);

        if (! $kcUser) {
            return 'skipped';
        }

        $attr = fn (string $key) => $kcUser['attributes'][$key][0] ?? null;

        $status = $attr('simas_status');
        $syncedAt = static::parseTime($attr('simas_synced_at'));
        // The DB keeps whole seconds; Keycloak sends nanoseconds. Compared at
        // second precision, or every run would re-apply the same data.
        $syncedAt = $syncedAt?->startOfSecond();
        $nip = (string) ($kcUser['username'] ?? '');

        // Keycloak has not asked SIMAS about this person yet (not an ASN
        // account, or a login before 2.4.0): nothing to copy.
        if (! $status || ! $syncedAt || preg_match('/^\d{18}$/', $nip) !== 1) {
            return 'skipped';
        }

        $employee = Employee::where('user_id', $user->id)->first();

        if ($status === 'not_found') {
            if ($employee?->simas_checked_at && $employee->simas_checked_at->gte($syncedAt)) {
                return 'unchanged';
            }
            $this->sync->recordNotFound($user, $nip, EmployeeSyncService::SOURCE_SSO);

            return 'applied';
        }

        if ($employee?->simas_synced_at && $employee->simas_synced_at->gte($syncedAt)) {
            return 'unchanged';
        }

        $skpd = $attr('simas_skpd');
        $unit = $attr('simas_unit');

        $result = $this->sync->apply($user, $nip, [
            'nama_lengkap' => $attr('simas_nama'),
            'nama_jabatan' => $attr('simas_jabatan'),
            'jenis_jabatan' => $attr('simas_jenis_jabatan'),
            'jenis_kepegawaian' => $attr('simas_jenis_kepegawaian'),
            'skpd' => $skpd,
            'unit' => $unit,
            'unit_kerja' => trim(($skpd ?? '').' '.($unit ?? '')) ?: null,
        ], EmployeeSyncService::SOURCE_SSO, $syncedAt);

        return $result['moved_from'] ? 'moved' : 'applied';
    }

    /**
     * Keycloak writes Instant.toString(): up to nine fractional digits and a
     * Z. Trimmed to six for PHP, then forced to UTC (an offset kept as-is is
     * stored as wall-clock time; see reference_carbon_offset_eloquent_trap).
     */
    public static function parseTime(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse(preg_replace('/(\.\d{6})\d+/', '$1', $value))->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
