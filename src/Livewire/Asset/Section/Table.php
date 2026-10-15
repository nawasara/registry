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
use Nawasara\Registry\Exceptions\PicMismatchException;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\PicAssignmentService;
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
    // #[Locked] pada editingId, picMismatch, dan picSimasError (audit
    // keamanan 15 Oktober 2026): ketiganya hanya diisi server. Tanpa kunci,
    // peramban dapat mengganti editingId untuk menimpa aset lain, atau
    // mengisi picMismatch agar saveAssetConfirmed() melewati pemeriksaan
    // SIMAS.
    #[Locked]
    public ?int $editingId = null;
    public $assetOpdId = '';
    public $assetPjUserId = '';
    public string $assetType = '';
    public string $assetIdentifier = '';
    public string $assetStatus = 'active';
    public string $assetNotes = '';
    public string $assetTicketRef = '';

    /**
     * A held-back assignment: SIMAS Hebat places this person in a different OPD
     * than the one on file. Kept as state rather than resolved on the spot, so
     * the admin sees both and decides. See PicMismatchException.
     *
     * @var array{tercatat: string, simas: string, unit_kerja: string}|null
     */
    #[Locked]
    public ?array $picMismatch = null;

    /** SIMAS was unreachable; assignment may proceed unverified once confirmed. */
    #[Locked]
    public ?string $picSimasError = null;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedTypeFilter() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedOpdFilter() { $this->resetPage(); }
    public function updatedOnlyDiscovered() { $this->resetPage(); }

    #[Computed]
    public function items()
    {
        $q = Asset::query()
            ->with(['opd', 'penanggungJawab'])
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
        $this->assetPjUserId = $asset->pj_user_id ?? '';
        $this->assetType = $asset->type;
        $this->assetIdentifier = $asset->identifier;
        $this->assetStatus = $asset->status;
        $this->assetNotes = $asset->notes ?? '';
        $this->assetTicketRef = $asset->ticket_ref ?? '';
        $this->dispatch('modal-open:registry-asset-form');
    }

    /**
     * Picked in the shared pic-picker. It reports rather than writes, so the
     * two values land here and the assignment still goes through the service
     * below when the form is saved.
     */
    #[On('pic-selected')]
    public function onPicSelected(array $payload): void
    {
        $this->assetOpdId = $payload['opd_id'] ?? '';
        $this->assetPjUserId = $payload['pj_user_id'] ?? '';

        // A fresh choice invalidates a held-back one.
        $this->picMismatch = null;
        $this->picSimasError = null;
    }

    public function saveAsset()
    {
        $this->saveAssetInternal();
    }

    /**
     * Save after the admin has seen a mismatch and chosen to move the person,
     * or seen that SIMAS is down and chosen to proceed unverified.
     */
    public function saveAssetConfirmed()
    {
        $this->saveAssetInternal(
            acceptMismatch: $this->picMismatch !== null,
            allowWithoutSimas: $this->picSimasError !== null,
        );
    }

    protected function saveAssetInternal(bool $acceptMismatch = false, bool $allowWithoutSimas = false)
    {
        Gate::authorize('registry.asset.manage');

        $this->validate([
            'assetOpdId' => 'nullable|exists:nawasara_registry_opd,id',
            'assetPjUserId' => 'nullable',
            'assetType' => 'required',
            'assetIdentifier' => 'required|max:255',
            'assetStatus' => 'required|in:active,inactive,pending',
            'assetNotes' => 'nullable|max:1000',
            'assetTicketRef' => 'nullable|max:100',
        ]);

        $payload = [
            'opd_id' => $this->assetOpdId ?: null,
            'type' => $this->assetType,
            'identifier' => $this->assetIdentifier,
            'status' => $this->assetStatus,
            'notes' => $this->assetNotes ?: null,
            'ticket_ref' => $this->assetTicketRef ?: null,
        ];

        if (! $this->editingId) {
            $payload['registered_at'] = now();
        }

        // Written WITHOUT pj_user_id: appointing someone is not a field write.
        // It re-checks their posting at SIMAS Hebat, refreshes their employment
        // record and may create an OPD, so it runs through the service below.
        $asset = Asset::updateOrCreate(['id' => $this->editingId], $payload);

        if ($this->assetPjUserId) {
            $userModel = config('auth.providers.users.model');
            $user = $userModel::find($this->assetPjUserId);

            if (! $user) {
                $this->toastError('Pengguna yang dipilih tidak ditemukan.');

                return;
            }

            try {
                app(PicAssignmentService::class)->assign(
                    asset: $asset,
                    user: $user,
                    opdId: $this->assetOpdId ?: null,
                    acceptMismatch: $acceptMismatch,
                    allowWithoutSimas: $allowWithoutSimas,
                );
            } catch (PicMismatchException $e) {
                // Held, not resolved. Overwriting opd_id would revoke someone's
                // access with nobody told, and if the matcher is what slipped,
                // the access revoked is the correct one.
                $this->picMismatch = [
                    'tercatat' => $e->recorded->name,
                    'simas' => $e->fromSimas?->name ?? $e->simasUnitKerja,
                    'unit_kerja' => $e->simasUnitKerja,
                ];

                return;
            } catch (SimasHebatException $e) {
                $this->picSimasError = $e->getMessage();

                return;
            }
        } elseif ($this->editingId) {
            // PIC cleared on an existing asset.
            $asset->forceFill(['pj_user_id' => null])->save();
        }

        $this->toastSuccess($this->editingId ? 'Aset berhasil diperbarui' : 'Aset berhasil ditambahkan');
        $this->resetModal();
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
        $this->assetOpdId = '';
        $this->assetPjUserId = '';
        $this->assetType = '';
        $this->assetIdentifier = '';
        $this->assetStatus = 'active';
        $this->assetNotes = '';
        $this->assetTicketRef = '';
        $this->picMismatch = null;
        $this->picSimasError = null;

        // The picker holds its own copy of the two values and the lookup box.
        $this->dispatch('pic-picker-reset');
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
