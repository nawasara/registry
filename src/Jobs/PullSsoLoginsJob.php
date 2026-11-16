<?php

namespace Nawasara\Registry\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Nawasara\Registry\Services\SsoLoginImporter;

/**
 * Every few minutes: copy SIMAS data for everyone who logged in through SSO
 * to any app. See SsoLoginImporter for why it reads Keycloak, not SIMAS.
 */
class PullSsoLoginsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(SsoLoginImporter $importer): void
    {
        $started = microtime(true);
        $window = max(5, (int) config('nawasara-registry.employee_sync.sso_window_minutes', 15));

        try {
            $counts = $importer->run($window);
        } catch (\Throwable $e) {
            Log::warning('[registry] sso login import failed', ['error' => $e->getMessage()]);

            return;
        }

        if ($counts['applied'] > 0 || $counts['skipped'] > 0) {
            Log::info('[registry] sso login import completed', $counts + [
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }
}
