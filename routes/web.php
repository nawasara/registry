<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Registry\Livewire\Opd\Index as OpdIndex;
use Nawasara\Registry\Livewire\Opd\Form as OpdForm;
use Nawasara\Registry\Livewire\Asset\Index as AssetIndex;

Route::middleware(['web', 'auth'])->prefix('nawasara-registry')->group(function () {
    Route::get('opd', OpdIndex::class)->name('nawasara-registry.opd.index');
    Route::get('opd/create', OpdForm::class)->name('nawasara-registry.opd.create');
    Route::get('opd/{id}/edit', OpdForm::class)->name('nawasara-registry.opd.edit');
    Route::get('assets', AssetIndex::class)->name('nawasara-registry.asset.index');
});
