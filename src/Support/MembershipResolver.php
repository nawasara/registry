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
        if (! empty($privilegedRoles) && $this->actsAsPrivileged($user, $privilegedRoles)) {
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

    /**
     * Is the user acting as a privileged role RIGHT NOW?
     *
     * ⚠️ Not simply hasAnyRole(). When a user switches role (SwitchRole
     * stores `active_role` in the session), permission checks narrow to that
     * one role — but data scope did not, because this used hasAnyRole()
     * which reads every role the account holds.
     *
     * The result was a developer who also holds hibah-operator switching to
     * operator, seeing operator-level menus, and still seeing every OPD's
     * data. The narrowing looked like it worked, which is worse than it
     * plainly not working: it invites testing tenant isolation from an
     * account that can never demonstrate it.
     *
     * With an active role set, ONLY that role counts. Without one, every
     * role counts, as before.
     */
    protected function actsAsPrivileged(Authenticatable $user, array $privilegedRoles): bool
    {
        if (! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        $activeRole = session('active_role');

        if ($activeRole && method_exists($user, 'hasRole') && $user->hasRole($activeRole)) {
            // Stale active_role (role revoked since switching) falls through
            // to the full check below rather than locking the user out.
            return in_array($activeRole, $privilegedRoles, true);
        }

        return $user->hasAnyRole($privilegedRoles);
    }

    /**
     * Bolehkah user dengan resolusi ini menulis `opd_id` = $target?
     *
     * Audit keamanan 15 Oktober 2026, temuan S3: lingkup OPD sebelumnya hanya
     * menyaring BACA. Aturan tulisnya mengikuti tiga keadaan yang sama:
     *
     *   privileged: bebas, itulah gunanya tingkat admin
     *   member:     hanya ke OPD-nya sendiri, dan baris lama pun harus
     *               berasal dari OPD-nya (tidak boleh "memindahkan keluar")
     *   restricted: tidak boleh menulis `opd_id` apa pun (fail-closed)
     *
     * Fungsi murni supaya dapat diuji tanpa basis data.
     *
     * @param  array{state: string, opdId: int|null}  $resolution
     */
    public static function allowsOpdWrite(array $resolution, ?int $target, bool $exists, ?int $original): bool
    {
        return match ($resolution['state'] ?? 'restricted') {
            'privileged' => true,
            'member' => $resolution['opdId'] !== null
                && $target === $resolution['opdId']
                && (! $exists || $original === $resolution['opdId']),
            default => false,
        };
    }

    /** Convenience: the OPD id for a member, else null. */
    public function opdIdFor(?Authenticatable $user): ?int
    {
        return $this->resolve($user)['opdId'];
    }
}
