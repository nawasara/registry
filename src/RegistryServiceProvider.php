<?php

namespace Nawasara\Registry;

use Livewire\Livewire;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\ServiceProvider;

class RegistryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-registry');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerLivewire();
        $this->offerPublishing();

        // Scope didaftarkan sebelum routes: UI token memfilter scope yang
        // tidak ter-register, jadi lupa mendaftarkan berarti scope-nya tidak
        // bisa diberikan ke token mana pun — diam-diam.
        $this->registerApiScopes();
        $this->registerApiRoutes();
    }

    /**
     * Daftarkan scope API ke registry terpusat `nawasara/api`.
     *
     * Guard class_exists, bukan dependency composer — package ini tetap jalan
     * penuh tanpa nawasara/api terpasang; API-nya saja yang absen.
     */
    public function registerApiScopes(): void
    {
        if (! class_exists(\Nawasara\Api\Support\ScopeRegistry::class)) {
            return;
        }

        $registry = $this->app->make(\Nawasara\Api\Support\ScopeRegistry::class);

        $registry->register(
            'registry.opd.read',
            'Data master OPD: kode, nama, alamat, dan kontak dinas. Dipakai aplikasi lain '
            .'supaya memakai daftar organisasi yang sama, bukan salinan masing-masing. Read-only.',
        );

        $registry->register(
            'registry.asset.read',
            'Aset milik OPD: domain, subdomain, dan akun layanan beserta penanggung jawabnya. '
            .'Catatan operator dan rujukan tiket internal tidak termasuk. Read-only.',
        );

        $registry->register(
            'registry.membership.read',
            'Keanggotaan OPD: pegawai mana bertugas di dinas mana (nama, NIP). '
            .'Dipisah dari scope OPD karena ini memetakan orang, bukan organisasi. Read-only.',
        );
    }

    /**
     * Mount routes/api.php di prefix /api/v1/registry.
     */
    public function registerApiRoutes(): void
    {
        if (! class_exists(\Nawasara\Api\ApiServiceProvider::class)) {
            return;
        }

        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/registry';

        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware(['api', 'api.auth', 'api.log'])
            ->name('nawasara-api.registry.')
            ->group(__DIR__.'/../routes/api.php');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-registry.php', 'nawasara-registry');
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Registry\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-registry.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }

    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/nawasara-registry.php' => config_path('nawasara-registry.php'),
        ], 'nawasara-registry:config');
    }
}
