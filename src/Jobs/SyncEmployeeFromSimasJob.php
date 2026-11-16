<?php

namespace Nawasara\Registry\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Services\EmployeeSyncService;

/**
 * Refresh one user's employment data after they log in.
 *
 * Queued so login never waits on SIMAS Hebat. One attempt only: SIMAS's
 * firewall blocks bursts, and the next login tries again anyway.
 */
class SyncEmployeeFromSimasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $userId) {}

    public function handle(EmployeeSyncService $service): void
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $service->sync($user, EmployeeSyncService::SOURCE_LOGIN);
        } catch (SimasHebatException $e) {
            // Already recorded on the row and logged by the service. Not
            // rethrown: a failed job here would only add noise to failed_jobs.
            Log::info('[registry] login sync skipped: '.$e->errorCode, ['user_id' => $this->userId]);
        }
    }
}
