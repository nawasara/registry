<?php

namespace Nawasara\Registry\Livewire\Asset\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasBrowserToast;
    use HasExport;
    use WithPagination;
    use WithSudo;

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
    //
    // #[Locked] pada editingId (audit keamanan 15 Oktober 2026): hanya diisi
    // server. Tanpa kunci, peramban dapat mengganti editingId untuk menimpa
    // aset lain. OPD dan PIC tidak lagi diubah di form ini — semuanya lewat
    // Section\Pics, beserta pemeriksaan SIMAS-nya.
    #[Locked]
    public ?int $editingId = null;
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
            ->with(['opd', 'penanggungJawab', 'backupAssignments.user'])
            ->search($this->search)
            ->byType($this->typeFilter)
            ->byStatus($this->statusFilter)
            ->byOpd($this->opdFilter);

        if ($this->onlyDiscovered) {
            $q->whereNotNull('discovered_at')
              ->where(function ($q) {
                  $q->whereNull('opd_id')->orWhereNull('pj_user_id');
              });
        }

        return $q->latest()->paginate(15);
    }

    #[Computed]
    public function discoveredCount()
    {
        return Asset::whereNotNull('discovered_at')
            ->where(function ($q) {
                $q->whereNull('opd_id')->orWhereNull('pj_user_id');
            })
            ->count();
    }

    #[Computed]
    public function opdList()
    {
        return Opd::orderBy('name')->get(['id', 'name', 'code']);
    }

    /*
     * pjCandidates() used to live here, and in three other packages besides,
     * identical apart from whitespace. It now sits in the shared pic-picker
     * component together with the NIP lookup, so the rule about who may be
     * appointed has one home rather than four.
     */

    /** Open the "Kelola PIC" modal (Section\Pics) for one asset. */
    public function openPics($id): void
    {
        Gate::authorize('registry.asset.manage');

        $this->dispatch('asset-pics-open', assetId: (int) $id);
    }

    /** Backup PICs are edited in Section\Pics; redraw the column after. */
    #[On('asset-pics-changed')]
    public function refreshPics(): void
    {
        unset($this->items);
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
            'assetType' => 'required',
            'assetIdentifier' => 'required|max:255',
            'assetStatus' => 'required|in:active,inactive,pending',
            'assetNotes' => 'nullable|max:1000',
            'assetTicketRef' => 'nullable|max:100',
        ]);

        // No opd_id and no pj_user_id here: both are changed in the OPD & PIC
        // modal, where appointing someone re-checks SIMAS Hebat. Leaving them
        // out of the payload also means editing a note cannot wipe them.
        $payload = [
            'type' => $this->assetType,
            'identifier' => $this->assetIdentifier,
            'status' => $this->assetStatus,
            'notes' => $this->assetNotes ?: null,
            'ticket_ref' => $this->assetTicketRef ?: null,
        ];

        $created = ! $this->editingId;
        if ($created) {
            $payload['registered_at'] = now();
        }

        $asset = Asset::updateOrCreate(['id' => $this->editingId], $payload);

        $this->toastSuccess($created ? 'Aset berhasil ditambahkan' : 'Aset berhasil diperbarui');
        $this->resetModal();

        // A new asset has nobody yet: go straight to naming them.
        if ($created) {
            $this->dispatch('asset-pics-open', assetId: $asset->id);
        }
    }

    // Sudo: aksi destruktif wajib re-autentikasi (AGENTS.md §6, audit
    // keamanan 15 Oktober 2026 S6).
    #[RequiresSudo(reason: 'menghapus aset')]
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
     * Export FULL asset registry (no filter) per spec. Includes OPD name and
     * the penanggung-jawab contact (name/NIP/WhatsApp resolved from Keycloak)
     * so the spreadsheet doesn't need a join with the OPD master.
     */
    protected function exportData(): iterable
    {
        return Asset::query()
            ->with(['opd', 'penanggungJawab'])
            ->orderBy('id')
            ->get()
            ->map(function (Asset $a) {
                $pj = $a->pjProfile();

                return [
                    'ID' => $a->id,
                    'Tipe' => $a->type,
                    'Identifier' => $a->identifier,
                    'Status' => $a->status,
                    'OPD Code' => $a->opd?->code,
                    'OPD Name' => $a->opd?->name,
                    'Penanggung Jawab' => $a->pj_user_id ? $pj->name : null,
                    'NIP' => $a->pj_user_id ? $pj->nip : null,
                    'WhatsApp' => $a->pj_user_id ? $pj->whatsapp : null,
                    'Notes' => $a->notes,
                    'Ticket Ref' => $a->ticket_ref,
                    'Discovered At' => optional($a->discovered_at)->format('Y-m-d H:i'),
                    'Registered At' => optional($a->registered_at)->format('Y-m-d H:i'),
                    'Created' => optional($a->created_at)->format('Y-m-d H:i'),
                    'Updated' => optional($a->updated_at)->format('Y-m-d H:i'),
                ];
            });
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.asset.section.table');
    }
}
