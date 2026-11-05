<?php

namespace Nawasara\Registry\Livewire\Asset\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Registry\Exceptions\PicMismatchException;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\AssetAssignment;
use Nawasara\Registry\Services\AssetAssignmentService;
use Nawasara\Registry\Services\PicAssignmentService;

/**
 * "OPD & PIC" modal: the ONE place an asset's people are changed — OPD and
 * primary PIC (through PicAssignmentService, which re-checks SIMAS Hebat)
 * and backup PICs (through AssetAssignmentService).
 *
 * The primary used to be set in the asset form while backups lived here.
 * Staff opened "Edit", found a PIC field that worked, and concluded this
 * modal was redundant. One place for every PIC removes the guess.
 *
 * Only one picker is mounted at a time ($mode): two OPD + NIP pickers in one
 * modal was a wall of fields, and both would answer the same reset event.
 *
 * Backups matter outside the panel: the ASN account portal lists every site
 * and mailbox a civil servant looks after, and opens agency mailboxes for
 * them without a password. Removing a PIC therefore revokes access and is
 * sudo-gated.
 */
class Pics extends Component
{
    use WithSudo;

    public const MODE_PRIMARY = 'primary';

    public const MODE_BACKUP = 'backup';

    #[Locked]
    public ?int $assetId = null;

    /** Which picker is open: '' (none), 'primary' or 'backup'. */
    #[Locked]
    public string $mode = '';

    /** Picked in the embedded PicPicker, not yet saved. */
    public ?int $pickedOpdId = null;

    public ?int $pickedUserId = null;

    /**
     * A held-back primary assignment: SIMAS Hebat places this person in a
     * different OPD than the one on file. #[Locked] because only the server
     * may fill it — otherwise the browser could set it and skip the check.
     *
     * @var array{tercatat: string, simas: string, unit_kerja: string}|null
     */
    #[Locked]
    public ?array $picMismatch = null;

    /** SIMAS was unreachable; the primary may be saved unverified once confirmed. */
    #[Locked]
    public ?string $picSimasError = null;

    #[On('asset-pics-open')]
    public function open(int $assetId): void
    {
        Gate::authorize('registry.asset.manage');

        $this->assetId = Asset::findOrFail($assetId)->id;
        $this->closePicker();
        unset($this->asset);

        $this->dispatch('modal-open:registry-asset-pics');
    }

    #[Computed]
    public function asset(): ?Asset
    {
        return $this->assetId
            ? Asset::with(['assignments.user', 'opd'])->find($this->assetId)
            : null;
    }

    public function startPrimary(): void
    {
        Gate::authorize('registry.asset.manage');

        $this->closePicker();
        $this->mode = self::MODE_PRIMARY;
        $this->pickedOpdId = $this->asset?->opd_id;
        $this->pickedUserId = $this->asset?->pj_user_id;
    }

    public function startBackup(): void
    {
        Gate::authorize('registry.asset.manage');

        $this->closePicker();
        $this->mode = self::MODE_BACKUP;
    }

    public function closePicker(): void
    {
        $this->mode = '';
        $this->pickedOpdId = null;
        $this->pickedUserId = null;
        $this->picMismatch = null;
        $this->picSimasError = null;
        $this->dispatch('pic-picker-reset');
    }

    #[On('primary-pic-selected')]
    public function onPrimaryPicked(array $payload): void
    {
        $this->pickedOpdId = ($payload['opd_id'] ?? null) ?: null;
        $this->pickedUserId = ($payload['pj_user_id'] ?? null) ?: null;

        // A fresh choice invalidates a held-back one.
        $this->picMismatch = null;
        $this->picSimasError = null;
    }

    #[On('backup-pic-selected')]
    public function onBackupPicked(array $payload): void
    {
        $this->pickedUserId = ($payload['pj_user_id'] ?? null) ?: null;
    }

    public function savePrimary(): void
    {
        $this->savePrimaryInternal();
    }

    /**
     * Save after the admin has seen a mismatch and chosen to move the person,
     * or seen that SIMAS is down and chosen to proceed unverified.
     */
    public function savePrimaryConfirmed(): void
    {
        $this->savePrimaryInternal(
            acceptMismatch: $this->picMismatch !== null,
            allowWithoutSimas: $this->picSimasError !== null,
        );
    }

    protected function savePrimaryInternal(bool $acceptMismatch = false, bool $allowWithoutSimas = false): void
    {
        Gate::authorize('registry.asset.manage');

        $asset = Asset::findOrFail($this->assetId);

        if (! $this->pickedUserId) {
            // OPD only, nobody appointed yet — the picker allows that, and an
            // asset may belong to an OPD before anyone is named for it.
            if (! $this->pickedOpdId) {
                $this->dispatch('toast', type: 'error', message: 'Pilih OPD atau orangnya dulu.');

                return;
            }
            $asset->forceFill(['opd_id' => $this->pickedOpdId])->save();
            $this->finishPrimary('OPD aset disimpan.');

            return;
        }

        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($this->pickedUserId);

        if (! $user) {
            $this->dispatch('toast', type: 'error', message: 'Pengguna yang dipilih tidak ditemukan.');

            return;
        }

        try {
            app(PicAssignmentService::class)->assign(
                asset: $asset,
                user: $user,
                opdId: $this->pickedOpdId,
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

        $this->finishPrimary('PIC utama disimpan.');
    }

    #[RequiresSudo(reason: 'melepas PIC utama aset')]
    public function clearPrimary(): void
    {
        Gate::authorize('registry.asset.manage');

        Asset::findOrFail($this->assetId)->forceFill(['pj_user_id' => null])->save();
        $this->finishPrimary('PIC utama dilepas.');
    }

    public function addBackup(): void
    {
        Gate::authorize('registry.asset.manage');

        $asset = Asset::findOrFail($this->assetId);
        $userModel = config('auth.providers.users.model');
        $user = $this->pickedUserId ? $userModel::find($this->pickedUserId) : null;

        if (! $user) {
            $this->dispatch('toast', type: 'error', message: 'Pilih orangnya dulu.');

            return;
        }

        try {
            app(AssetAssignmentService::class)->addBackup($asset, $user, auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->closePicker();
        $this->dispatch('toast', type: 'success', message: 'PIC cadangan ditambahkan.');
        $this->dispatch('asset-pics-changed');
        unset($this->asset);
    }

    #[RequiresSudo(reason: 'mencabut PIC cadangan aset')]
    public function removeBackup(int $userId): void
    {
        Gate::authorize('registry.asset.manage');

        app(AssetAssignmentService::class)->removeBackup(Asset::findOrFail($this->assetId), $userId);

        $this->dispatch('toast', type: 'success', message: 'PIC cadangan dicabut.');
        $this->dispatch('asset-pics-changed');
        unset($this->asset);
    }

    private function finishPrimary(string $message): void
    {
        $this->closePicker();
        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('asset-pics-changed');
        unset($this->asset);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.asset.section.pics', [
            'roleLabels' => [AssetAssignment::ROLE_PRIMARY => 'Utama', AssetAssignment::ROLE_BACKUP => 'Cadangan'],
        ]);
    }
}
