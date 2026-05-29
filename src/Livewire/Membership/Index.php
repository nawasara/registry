<?php

namespace Nawasara\Registry\Livewire\Membership;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;

/**
 * Admin: link a user to the OPD they belong to. This membership is what
 * activates per-OPD data scoping in consumer packages (hibah, etc.). A user
 * without a membership is "restricted" (sees nothing) unless they hold a
 * privileged role — see MembershipResolver.
 */
class Index extends Component
{
    use WithPagination;

    public ?int $userId = null;
    public ?int $opdId = null;

    public function mount(): void
    {
        $this->authorize('registry.membership.manage');
    }

    #[Computed]
    public function rows()
    {
        return Membership::query()
            ->with(['user:id,name,email', 'opd:id,code,name'])
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function opdOptions(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function userOptions(): array
    {
        $assigned = Membership::pluck('user_id')->all();

        return \App\Models\User::query()
            ->whereNotIn('id', $assigned)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"])
            ->all();
    }

    public function assign(): void
    {
        $this->authorize('registry.membership.manage');

        $this->validate([
            'userId' => ['required', 'exists:users,id', 'unique:nawasara_registry_memberships,user_id'],
            'opdId' => ['required', 'exists:nawasara_registry_opd,id'],
        ], [], [
            'userId' => 'User',
            'opdId' => 'OPD',
        ]);

        Membership::create([
            'user_id' => $this->userId,
            'opd_id' => $this->opdId,
            'aktif' => true,
        ]);

        $this->reset(['userId', 'opdId']);
        $this->dispatch('close-modal', 'registry-membership-assign');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Keanggotaan ditambahkan.']);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('registry.membership.manage');

        $m = Membership::findOrFail($id);
        $m->update(['aktif' => ! $m->aktif]);

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => $m->aktif ? 'Keanggotaan diaktifkan.' : 'Keanggotaan dinonaktifkan.',
        ]);
    }

    public function remove(int $id): void
    {
        $this->authorize('registry.membership.manage');

        Membership::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Keanggotaan dihapus.']);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.membership.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
