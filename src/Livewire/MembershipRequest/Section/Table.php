<?php

namespace Nawasara\Registry\Livewire\MembershipRequest\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Registry\Exceptions\MembershipRequestException;
use Nawasara\Registry\Models\MembershipRequest;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\MembershipRequestService;

/**
 * Antrean pengajuan petugas: setujui atau tolak.
 *
 * Setujui = buat akun bila belum ada + tautkan ke OPD pengaju + beri peran,
 * dalam satu transaksi (MembershipRequestService::approve). Tolak wajib
 * beralasan, karena admin OPD membacanya di panel.
 */
class Table extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = MembershipRequest::STATUS_PENDING;

    #[Url]
    public string $opdFilter = '';

    #[Url]
    public string $search = '';

    public ?int $rejectId = null;

    public string $rejectReason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'opdFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function statusOptions(): array
    {
        return [
            MembershipRequest::STATUS_PENDING => 'Menunggu',
            MembershipRequest::STATUS_APPROVED => 'Disetujui',
            MembershipRequest::STATUS_REJECTED => 'Ditolak',
        ];
    }

    #[Computed]
    public function opdOptions(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
    }

    #[Computed]
    public function roleLabels(): array
    {
        return MembershipRequestService::allowedRoles();
    }

    #[Computed]
    public function rows()
    {
        return MembershipRequest::query()
            ->with(['opd:id,name', 'requester:id,name', 'decider:id,name'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->opdFilter !== '', fn ($q) => $q->where('opd_id', (int) $this->opdFilter))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('nip', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return MembershipRequest::where('status', MembershipRequest::STATUS_PENDING)->count();
    }

    public function approve(int $id, MembershipRequestService $service): void
    {
        $this->authorize('registry.membership.manage');

        try {
            $request = $service->approve(MembershipRequest::findOrFail($id), auth()->user());
        } catch (MembershipRequestException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => "{$request->name} kini petugas di {$request->opd?->name}."]);
    }

    public function openReject(int $id): void
    {
        $this->authorize('registry.membership.manage');

        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->resetValidation();
        $this->dispatch('modal-open:registry-request-reject');
    }

    public function reject(MembershipRequestService $service): void
    {
        $this->authorize('registry.membership.manage');

        $this->validate(['rejectReason' => ['required', 'string', 'max:1000']], [], ['rejectReason' => 'alasan']);

        try {
            $service->reject(MembershipRequest::findOrFail($this->rejectId), auth()->user(), $this->rejectReason);
        } catch (MembershipRequestException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->reset(['rejectId', 'rejectReason']);
        $this->dispatch('modal-close:registry-request-reject');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Pengajuan ditolak.']);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.membership-request.section.table');
    }
}
