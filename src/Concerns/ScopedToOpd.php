<?php

namespace Nawasara\Registry\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * Apply per-OPD data isolation to any model that has an `opd_id` column.
 * Adds a global scope driven by the three-state membership rule
 * (MembershipResolver). Single enforcement point — a forgotten where-clause
 * in a component cannot leak another OPD's rows.
 *
 * Consumers override privilegedRoles() to declare which roles may see across
 * all OPD without a membership (their admin tier). Default is ['developer']
 * so the super-admin is never locked out.
 *
 * Bypass deliberately (system ops, cross-OPD admin reports) with
 * Model::withoutGlobalScopes() — and gate that behind a permission check.
 */
trait ScopedToOpd
{
    /** @return array<int, string> */
    protected static function privilegedRoles(): array
    {
        return ['developer'];
    }

    public static function bootScopedToOpd(): void
    {
        static::addGlobalScope('registry-opd', function (Builder $builder) {
            $resolution = app(MembershipResolver::class)
                ->resolve(auth()->user(), static::privilegedRoles());

            $table = $builder->getModel()->getTable();

            match ($resolution['state']) {
                'member' => $builder->where("{$table}.opd_id", $resolution['opdId']),
                'restricted' => $builder->whereRaw('1 = 0'), // fail-closed: see nothing
                default => null, // 'privileged' — no filter
            };
        });
    }
}
