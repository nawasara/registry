<?php

namespace Nawasara\Registry\Livewire\Employee\Section;

use Illuminate\Support\Facades\RateLimiter;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Services\EmployeeSyncService;

/**
 * "Perbarui dari SIMAS" for one user, shared by the table and the detail modal.
 *
 * Rate limited per admin: SIMAS's firewall blocks the calling IP after a
 * burst, and that block stops the login refresh for everyone, not only this
 * screen.
 */
trait SyncsFromSimas
{
    public function syncNow(int $userId): void
    {
        $this->authorize('registry.employee.sync');

        $key = 'registry-employee-sync:'.auth()->id();
        $limit = max(1, (int) config('nawasara-registry.employee_sync.manual_per_minute', 10));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Terlalu sering. Tunggu '.RateLimiter::availableIn($key).' detik supaya SIMAS Hebat tidak memblokir Nawasara.']);

            return;
        }
        RateLimiter::hit($key, 60);

        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($userId);

        if (! $user) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Pengguna tidak ditemukan.']);

            return;
        }

        try {
            $result = app(EmployeeSyncService::class)->sync($user, EmployeeSyncService::SOURCE_MANUAL);
        } catch (SimasHebatException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Gagal: '.$e->getMessage()]);
            $this->dispatch('registry-employee-synced', userId: $userId);

            return;
        }

        $type = $result['status'] === 'ok' && $result['opd'] !== null ? 'success' : 'warning';
        $this->dispatch('toast', ['type' => $type, 'message' => $result['message']]);
        $this->dispatch('registry-employee-synced', userId: $userId);
    }
}
