<?php

namespace Nawasara\Registry\Livewire\Asset\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Pic;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasBrowserToast;
    use HasExport;
    use WithPagination;

    // #[Url] so the command palette can deep-link ?search=<term> and prefill.
    #[Url(except: '')]
    public string $search = '';

    /**
     * Multi-select filters using filter-panel array semantics.
     * Empty array == no filter. The Asset scopes (byType/byStatus/byOpd)
     * are polymorphic and accept either string or array.
     *
     * @var array<int, string>
     */
    public array $typeFilter = [];

    /** @var array<int, string> */
    public array $statusFilter = [];

    /** @var array<int, int|string> */
    public array $opdFilter = [];

    /**
     * Toggle button that narrows to assets discovered automatically (e.g.
     * from DNS sync) but not yet assigned to OPD/PIC. Stays scalar bool
     * because it's a single binary state, not a multi-value dimension.
     */
    public bool $onlyDiscovered = false;

    // Modal state
    public ?int $editingId = null;
    public $assetOpdId = '';
    public $assetPicId = '';
    public string $assetType = '';
    public string $assetIdentifier = '';
    public string $assetStatus = 'active';
    public string $assetNotes = '';
    public string $assetTicketRef = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedTypeFilter() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedOpdFilter() { $this->resetPage(); }
    public function updatedOnlyDiscovered() { $this->resetPage(); }

    #[Computed]
    public function items()
    {
        $q = Asset::query()
            ->with(['opd', 'pic'])
            ->search($this->search)
            ->byType($this->typeFilter)
            ->byStatus($this->statusFilter)
            ->byOpd($this->opdFilter);

        if ($this->onlyDiscovered) {
            $q->whereNotNull('discovered_at')
              ->where(function ($q) {
                  $q->whereNull('opd_id')->orWhereNull('pic_id');
              });
        }

        return $q->latest()->paginate(15);
    }

    #[Computed]
    public function discoveredCount()
    {
        return Asset::whereNotNull('discovered_at')
            ->where(function ($q) {
                $q->whereNull('opd_id')->orWhereNull('pic_id');
            })
            ->count();
    }

    #[Computed]
    public function opdList()
    {
        return Opd::orderBy('name')->get(['id', 'name', 'code']);
    }

    #[Computed]
    public function picList()
    {
        if (! $this->assetOpdId) {
            return collect();
        }

        return Pic::where('opd_id', $this->assetOpdId)->orderBy('name')->get(['id', 'name', 'position']);
    }

    #[On('openCreateAsset')]
    public function openCreate()
    {
        Gate::authorize('registry.asset.manage');

        $this->resetModal();
        $this->dispatch('modal-open:registry-asset-form');
    }

    public function openEdit($id)
    {
        Gate::authorize('registry.asset.manage');

        $asset = Asset::findOrFail($id);
        $this->editingId = $asset->id;
        $this->assetOpdId = $asset->opd_id;
        $this->assetPicId = $asset->pic_id ?? '';
        $this->assetType = $asset->type;
        $this->assetIdentifier = $asset->identifier;
        $this->assetStatus = $asset->status;
        $this->assetNotes = $asset->notes ?? '';
        $this->assetTicketRef = $asset->ticket_ref ?? '';
        $this->dispatch('modal-open:registry-asset-form');
    }

    public function saveAsset()
    {
        Gate::authorize('registry.asset.manage');

        $this->validate([
            'assetOpdId' => 'nullable|exists:nawasara_registry_opd,id',
            'assetPicId' => 'nullable',
            'assetType' => 'required',
            'assetIdentifier' => 'required|max:255',
            'assetStatus' => 'required|in:active,inactive,pending',
            'assetNotes' => 'nullable|max:1000',
            'assetTicketRef' => 'nullable|max:100',
        ]);

        $payload = [
            'opd_id' => $this->assetOpdId ?: null,
            'pic_id' => $this->assetPicId ?: null,
            'type' => $this->assetType,
            'identifier' => $this->assetIdentifier,
            'status' => $this->assetStatus,
            'notes' => $this->assetNotes ?: null,
            'ticket_ref' => $this->assetTicketRef ?: null,
        ];

        if (! $this->editingId) {
            $payload['registered_at'] = now();
        }

        Asset::updateOrCreate(['id' => $this->editingId], $payload);

        $this->toastSuccess($this->editingId ? 'Aset berhasil diperbarui' : 'Aset berhasil ditambahkan');
        $this->resetModal();
    }

    public function delete($id)
    {
        Gate::authorize('registry.asset.manage');

        Asset::findOrFail($id)->delete();
        $this->toastSuccess('Aset berhasil dihapus');
    }

    private function resetModal()
    {
        $this->dispatch('modal-close:registry-asset-form');
        $this->editingId = null;
        $this->assetOpdId = '';
        $this->assetPicId = '';
        $this->assetType = '';
        $this->assetIdentifier = '';
        $this->assetStatus = 'active';
        $this->assetNotes = '';
        $this->assetTicketRef = '';
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'registry-assets';
    }

    /**
     * Export FULL asset registry (no filter) per spec. Includes OPD/PIC
     * names so the spreadsheet doesn't need a join with the OPD master.
     */
    protected function exportData(): iterable
    {
        return Asset::query()
            ->with(['opd', 'pic'])
            ->orderBy('id')
            ->get()
            ->map(fn (Asset $a) => [
                'ID' => $a->id,
                'Tipe' => $a->type,
                'Identifier' => $a->identifier,
                'Status' => $a->status,
                'OPD Code' => $a->opd?->code,
                'OPD Name' => $a->opd?->name,
                'PIC Name' => $a->pic?->name,
                'PIC Position' => $a->pic?->position,
                'Notes' => $a->notes,
                'Ticket Ref' => $a->ticket_ref,
                'Discovered At' => optional($a->discovered_at)->format('Y-m-d H:i'),
                'Registered At' => optional($a->registered_at)->format('Y-m-d H:i'),
                'Created' => optional($a->created_at)->format('Y-m-d H:i'),
                'Updated' => optional($a->updated_at)->format('Y-m-d H:i'),
            ]);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.asset.section.table');
    }
}
