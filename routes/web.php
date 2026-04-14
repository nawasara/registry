<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Registry\Livewire\Opd\Index as OpdIndex;
use Nawasara\Registry\Livewire\Opd\Form as OpdForm;
use Nawasara\Registry\Livewire\Asset\Index as AssetIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-registry')->group(function () {
    Route::get('opd', OpdIndex::class)
        ->middleware(PermissionMiddleware::using('registry.opd.view'))
        ->name('nawasara-registry.opd.index');

    Route::get('opd/create', OpdForm::class)
        ->middleware(PermissionMiddleware::using('registry.opd.manage'))
        ->name('nawasara-registry.opd.create');

    Route::get('opd/{id}/edit', OpdForm::class)
        ->middleware(PermissionMiddleware::using('registry.opd.manage'))
        ->name('nawasara-registry.opd.edit');

    Route::get('assets', AssetIndex::class)
        ->middleware(PermissionMiddleware::using('registry.asset.view'))
        ->name('nawasara-registry.asset.index');
});
