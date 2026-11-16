<?php

namespace Nawasara\Registry\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Nawasara\Registry\Jobs\SyncEmployeeFromSimasJob;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Services\EmployeeSyncService;

/**
 * Queue a SIMAS refresh when someone logs in to Nawasara.
 *
 * Listens to Laravel's own Login event, so nawasara-core needs no knowledge of
 * the registry. Keycloak already refreshes the same data on every ASN login
 * (authenticator 2.4.0), but Nawasara asks SIMAS itself instead of reading
 * the token: the token carries no simas_* claims without extra mappers, and
 * Nawasara accounts are few enough that one lookup per person per day is
 * nothing to the source.
 *
 * Throttled three ways, because SIMAS's firewall punishes bursts:
 *   - skipped when the last attempt is younger than `interval_hours`
 *   - one queued job per user per 10 minutes (Cache::add)
 *   - nothing at all while SIMAS is answering with its block page
 */
class QueueEmployeeSyncOnLogin
{
    public function handle(Login $event): void
    {
        if (! config('nawasara-registry.employee_sync.on_login', true)) {
            return;
        }

        // Nothing here may break a login: a missing column before migrate,
        // a cache outage or a queue hiccup only costs one refresh.
        try {
            $this->queue($event);
        } catch (\Throwable $e) {
            Log::warning('[registry] could not queue employee sync on login: '.$e->getMessage());
        }
    }

    private function queue(Login $event): void
    {
        $user = $event->user;
        $username = trim((string) ($user->username ?? ''));

        if (preg_match('/^\d{18}$/', $username) !== 1
            && ! Employee::where('user_id', $user->getAuthIdentifier())->whereNotNull('nip')->exists()) {
            return;
        }

        if (Cache::has(EmployeeSyncService::BLOCKED_CACHE_KEY)) {
            return;
        }

        $hours = max(1, (int) config('nawasara-registry.employee_sync.interval_hours', 24));
        $last = Employee::where('user_id', $user->getAuthIdentifier())->value('simas_checked_at');

        if ($last && now()->subHours($hours)->lt($last)) {
            return;
        }

        if (! Cache::add('nawasara-registry:employee-sync:'.$user->getAuthIdentifier(), true, now()->addMinutes(10))) {
            return;
        }

        SyncEmployeeFromSimasJob::dispatch((int) $user->getAuthIdentifier());
    }
}
