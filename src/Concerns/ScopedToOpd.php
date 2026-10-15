<?php

namespace Nawasara\Registry\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 *
 * Tulis juga dijaga (audit keamanan 15 Oktober 2026, temuan S3): global
 * scope hanya menyaring BACA, sehingga operator OPD dapat membuat atau
 * memindahkan baris ke OPD lain cukup dengan mengirim `opd_id` karangan.
 * Hook `saving` di bawah menolak penulisan `opd_id` di luar lingkup user
 * yang sedang login. Console, queue, dan seeder tidak punya user, jadi
 * resolver memperlakukannya sebagai privileged dan tidak terpengaruh.
 */
trait ScopedToOpd
{
    /**
     * Penghitung bersarang untuk withoutOpdWriteGuard(). Statis per kelas
     * pemakai trait, bukan global, supaya bypass di satu model tidak ikut
     * membuka model lain.
     */
    protected static int $opdWriteGuardSuspended = 0;

    /** @return array<int, string> */
    protected static function privilegedRoles(): array
    {
        return ['developer'];
    }

    public static function bootScopedToOpd(): void
    {
        static::addGlobalScope('registry-opd', function (Builder $builder) {
            $resolution = static::opdResolution(auth()->user());

            $table = $builder->getModel()->getTable();

            match ($resolution['state']) {
                'member' => $builder->where("{$table}.opd_id", $resolution['opdId']),
                'restricted' => $builder->whereRaw('1 = 0'), // fail-closed: see nothing
                default => null, // 'privileged' — no filter
            };
        });

        // Hanya diperiksa saat `opd_id` benar-benar ditulis (baris baru, atau
        // kolomnya berubah). Operator yang mengubah kolom lain pada baris
        // OPD-nya sendiri tidak tersentuh.
        static::saving(function (Model $model): void {
            if (static::$opdWriteGuardSuspended > 0) {
                return;
            }

            if ($model->exists && ! $model->isDirty('opd_id')) {
                return;
            }

            $user = auth()->user();

            if (! $user) {
                return;
            }

            $target = $model->getAttribute('opd_id');
            $original = $model->exists ? $model->getOriginal('opd_id') : null;

            $allowed = MembershipResolver::allowsOpdWrite(
                static::opdResolution($user),
                $target === null ? null : (int) $target,
                $model->exists,
                $original === null ? null : (int) $original,
            );

            if (! $allowed) {
                // Melempar, bukan mengembalikan false: `saving` yang
                // mengembalikan false membatalkan simpan DIAM-DIAM, dan staf
                // akan mengira datanya tersimpan.
                throw new AuthorizationException('Anda tidak berwenang menyimpan data untuk OPD tersebut.');
            }
        });
    }

    /**
     * Status lingkup OPD user untuk model ini, memakai privilegedRoles()
     * milik model. Dipakai formulir untuk mempersempit pilihan OPD dan
     * memaksa `opd_id` bagi anggota.
     *
     * @return array{state: string, opdId: int|null}
     */
    public static function opdResolution(?Authenticatable $user): array
    {
        return app(MembershipResolver::class)->resolve($user, static::privilegedRoles());
    }

    /**
     * Jalankan operasi sistem lintas OPD (mis. impor massal oleh admin)
     * tanpa penjaga tulis. Pemanggil WAJIB sudah memeriksa izinnya sendiri.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutOpdWriteGuard(callable $callback): mixed
    {
        static::$opdWriteGuardSuspended++;

        try {
            return $callback();
        } finally {
            static::$opdWriteGuardSuspended--;
        }
    }
}
