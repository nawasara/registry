<?php

namespace Nawasara\Registry\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Nawasara\Registry\Models\Membership;

/**
 * Resolves how a user should be scoped against OPD-owned data. The single
 * source of truth for the three-state, fail-closed rule (see app docs/
 * plan-registry-user-membership.md §7):
 *
 *   member     — has an active membership → scope to that opd_id
 *   privileged — no membership but holds a privileged role → see all OPD
 *   restricted — no membership and not privileged → see NOTHING
 *
 * The "restricted" state is what makes this fail-closed: an operator who was
 * given an operator role but never linked to an OPD sees zero rows, not
 * everything. Relying on "remember to link them" (fail-open) is a data-leak
 * waiting to happen.
 *
 * Registry deliberately does NOT know consumer-specific role names — the
 * caller passes its privileged roles (e.g. ['developer', 'hibah-admin']).
 */
class MembershipResolver
{
    /**
     * @param  array<int, string>  $privilegedRoles
     * @return array{state: string, opdId: int|null}
     */
    public function resolve(?Authenticatable $user, array $privilegedRoles = []): array
    {
        // No subject (console / queue / import). Consumers run system
        // operations under withoutGlobalScopes() explicitly, so treat the
        // absence of a user as privileged here rather than locking out CLI.
        if (! $user) {
            return ['state' => 'privileged', 'opdId' => null];
        }

        // Privileged roles win over membership. An admin who also happens
        // to be linked to an OPD (e.g. PIC for their own dinas) still needs
        // cross-OPD visibility to do their admin work. Without this check
        // first, the member branch would scope them to a single OPD even
        // though their role grants global access.
        //
        // hasAnyRole() comes from Spatie's HasRoles trait on the User model.
        if (! empty($privilegedRoles) && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($privilegedRoles)) {
            return ['state' => 'privileged', 'opdId' => null];
        }

        $opdId = Membership::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('aktif', true)
            ->value('opd_id');

        if ($opdId !== null) {
            return ['state' => 'member', 'opdId' => (int) $opdId];
        }

        return ['state' => 'restricted', 'opdId' => null];
    }

    /** Convenience: the OPD id for a member, else null. */
    public function opdIdFor(?Authenticatable $user): ?int
    {
        return $this->resolve($user)['opdId'];
    }
}
