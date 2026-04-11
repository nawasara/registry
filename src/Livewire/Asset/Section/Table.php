<?php

namespace Nawasara\Registry\Livewire\Asset\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Pic;

class Table extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public string $statusFilter = '';
    public string $opdFilter = '';

    // Modal state
    public bool $showModal = false;
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

    #[Computed]
    public function items()
    {
        return Asset::query()
            ->with(['opd', 'pic'])
            ->search($this->search)
            ->byType($this->typeFilter)
            ->byStatus($this->statusFilter)
            ->byOpd($this->opdFilter)
            ->latest()
            ->paginate(15);
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
        $this->resetModal();
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        $asset = Asset::findOrFail($id);
        $this->editingId = $asset->id;
        $this->assetOpdId = $asset->opd_id;
        $this->assetPicId = $asset->pic_id ?? '';
        $this->assetType = $asset->type;
        $this->assetIdentifier = $asset->identifier;
        $this->assetStatus = $asset->status;
        $this->assetNotes = $asset->notes ?? '';
        $this->assetTicketRef = $asset->ticket_ref ?? '';
        $this->showModal = true;
    }

    public function saveAsset()
    {
        $this->validate([
            'assetOpdId' => 'required|exists:nawasara_registry_opd,id',
            'assetPicId' => 'nullable',
            'assetType' => 'required',
            'assetIdentifier' => 'required|max:255',
            'assetStatus' => 'required|in:active,inactive,pending',
            'assetNotes' => 'nullable|max:1000',
            'assetTicketRef' => 'nullable|max:100',
        ]);

        $payload = [
            'opd_id' => $this->assetOpdId,
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

        toaster_success($this->editingId ? 'Aset berhasil diperbarui' : 'Aset berhasil ditambahkan');
        $this->resetModal();
    }

    public function delete($id)
    {
        Asset::findOrFail($id)->delete();
        toaster_success('Aset berhasil dihapus');
    }

    private function resetModal()
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->assetOpdId = '';
        $this->assetPicId = '';
        $this->assetType = '';
        $this->assetIdentifier = '';
        $this->assetStatus = 'active';
        $this->assetNotes = '';
        $this->assetTicketRef = '';
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.asset.section.table');
    }
}
