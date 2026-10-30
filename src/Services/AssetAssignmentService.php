<?php

namespace Nawasara\Registry\Services;

use Illuminate\Support\Collection;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\AssetAssignment;

/**
 * Backup PICs, and "what is this person responsible for".
 *
 * The PRIMARY PIC is still appointed through PicAssignmentService (it
 * refreshes employment data at SIMAS Hebat and moves the person's OPD
 * membership to the asset's OPD). A backup must NOT move anyone's
 * membership: a backup can come from another agency, and ScopedToOpd would
 * otherwise silently change which data they can see in the panel. So
 * backups have their own, lighter path here.
 */
class AssetAssignmentService
{
    public function addBackup(Asset $asset, object $user, ?object $by = null): AssetAssignment
    {
        $userId = $user->getAuthIdentifier();

        if ((int) $asset->pj_user_id === (int) $userId) {
            throw new \InvalidArgumentException('Orang ini sudah menjadi PIC utama aset tersebut.');
        }

        $assignment = AssetAssignment::updateOrCreate(
            ['asset_id' => $asset->id, 'user_id' => $userId],
            ['role' => AssetAssignment::ROLE_BACKUP, 'assigned_by' => $by?->getAuthIdentifier()],
        );

        activity()->performedOn($asset)
            ->withProperties(['user_id' => $userId, 'role' => AssetAssignment::ROLE_BACKUP])
            ->log('PIC cadangan ditambahkan');

        return $assignment;
    }

    /**
     * Remove a backup. The primary is never removed here: clearing it is a
     * change to the asset itself (pj_user_id), made in the asset form.
     */
    public function removeBackup(Asset $asset, int $userId): bool
    {
        $deleted = AssetAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('user_id', $userId)
            ->where('role', AssetAssignment::ROLE_BACKUP)
            ->delete() > 0;

        if ($deleted) {
            activity()->performedOn($asset)
                ->withProperties(['user_id' => $userId, 'role' => AssetAssignment::ROLE_BACKUP])
                ->log('PIC cadangan dihapus');
        }

        return $deleted;
    }

    /**
     * Assets a Keycloak user is responsible for, with every PIC of each.
     *
     * Looked up by `sub` because that is what the ASN account portal holds.
     * Only active assets: an inactive site or mailbox is not something to
     * manage, and listing it would invite actions on a dead resource.
     *
     * @param  list<string>|null  $types  asset types, e.g. ['hosting', 'hosting_account']
     * @return Collection<int, Asset>  each with ->assignments.user loaded and ->my_role set
     */
    public function assetsForKeycloakSub(string $sub, ?array $types = null): Collection
    {
        $userModel = config('auth.providers.users.model');
        $userId = $userModel::query()->where('keycloak_id', $sub)->value('id');

        if ($userId === null) {
            return collect();
        }

        $roles = AssetAssignment::query()->where('user_id', $userId)->pluck('role', 'asset_id');

        if ($roles->isEmpty()) {
            return collect();
        }

        return Asset::query()
            ->whereIn('id', $roles->keys())
            ->where('status', 'active')
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->with(['opd', 'assignments.user'])
            ->orderBy('identifier')
            ->get()
            ->each(fn (Asset $a) => $a->setAttribute('my_role', $roles[$a->id]));
    }

    /** Whether this Keycloak user is a PIC (any role) of the asset with this identifier. */
    public function isAssigned(string $sub, string $identifier, ?array $types = null): bool
    {
        return $this->assetsForKeycloakSub($sub, $types)
            ->contains(fn (Asset $a) => strcasecmp($a->identifier, $identifier) === 0);
    }
}
