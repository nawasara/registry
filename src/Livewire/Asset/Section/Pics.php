<?php

namespace Nawasara\Registry\Livewire\Asset\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\AssetAssignment;
use Nawasara\Registry\Services\AssetAssignmentService;

/**
 * "Kelola PIC" modal: the primary PIC (read-only here, changed in the asset
 * form) and backup PICs (added and removed here).
 *
 * Backups matter outside the panel: the ASN account portal lists every site
 * and mailbox a civil servant looks after, and opens agency mailboxes for
 * them without a password. Removing a backup therefore revokes access and
 * is sudo-gated.
 */
class Pics extends Component
{
    use WithSudo;

    #[Locked]
    public ?int $assetId = null;

    /** Picked in the embedded PicPicker, not yet added. */
    public ?int $pickedUserId = null;

    #[On('asset-pics-open')]
    public function open(int $assetId): void
    {
        Gate::authorize('registry.asset.manage');

        $this->assetId = Asset::findOrFail($assetId)->id;
        $this->pickedUserId = null;
        unset($this->asset);

        $this->dispatch('pic-picker-reset');
        $this->dispatch('modal-open:registry-asset-pics');
    }

    #[Computed]
    public function asset(): ?Asset
    {
        return $this->assetId
            ? Asset::with(['assignments.user', 'opd'])->find($this->assetId)
            : null;
    }

    #[On('backup-pic-selected')]
    public function onPicked(array $payload): void
    {
        $this->pickedUserId = $payload['pj_user_id'] ?? null;
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

        $this->pickedUserId = null;
        $this->dispatch('pic-picker-reset');
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

    public function render()
    {
        return view('nawasara-registry::livewire.pages.asset.section.pics', [
            'roleLabels' => [AssetAssignment::ROLE_PRIMARY => 'Utama', AssetAssignment::ROLE_BACKUP => 'Cadangan'],
        ]);
    }
}
