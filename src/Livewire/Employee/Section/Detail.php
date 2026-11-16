<?php

namespace Nawasara\Registry\Livewire\Employee\Section;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\AssetAssignment;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Models\Membership;
use Spatie\Activitylog\Models\Activity;

/**
 * Rincian satu pegawai: jawaban SIMAS apa adanya, keanggotaan OPD, aset yang
 * dipegangnya, dan riwayat perpindahan OPD yang mengikuti SIMAS.
 */
class Detail extends Component
{
    use SyncsFromSimas;

    #[Locked]
    public ?int $userId = null;

    #[On('registry-employee-detail')]
    public function open(int $userId): void
    {
        $this->authorize('registry.employee.view');
        $this->userId = $userId;
        $this->dispatch('modal-open:registry-employee-detail');
    }

    /** Re-render after a refresh; the data is read fresh in render(). */
    #[On('registry-employee-synced')]
    public function refreshAfterSync(): void {}

    public function render()
    {
        $data = null;
        $userModel = config('auth.providers.users.model');
        $user = $this->userId ? $userModel::find($this->userId) : null;

        if ($user) {
            $membership = Membership::with('opd:id,name')->where('user_id', $user->id)->first();
            $backupIds = AssetAssignment::where('user_id', $user->id)->pluck('asset_id');

            $data = [
                'user' => $user,
                'employee' => Employee::with(['simasOpd:id,name', 'unit:id,name'])->where('user_id', $user->id)->first(),
                'membership' => $membership,
                'assets' => Asset::with('opd:id,name')
                    ->where(fn ($q) => $q->where('pj_user_id', $user->id)->orWhereIn('id', $backupIds))
                    ->orderBy('identifier')->limit(50)->get(),
                'moves' => $membership
                    ? Activity::where('subject_type', $membership->getMorphClass())
                        ->where('subject_id', $membership->id)
                        ->where('description', 'Keanggotaan OPD dipindah mengikuti SIMAS Hebat')
                        ->latest('id')->limit(10)->get()
                    : collect(),
            ];
        }

        return view('nawasara-registry::livewire.pages.employee.section.detail', ['d' => $data]);
    }
}
