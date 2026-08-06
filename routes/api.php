<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Registry\Http\Api\AssetController;
use Nawasara\Registry\Http\Api\MembershipController;
use Nawasara\Registry\Http\Api\OpdController;

/*
|--------------------------------------------------------------------------
| Registry API routes
|--------------------------------------------------------------------------
| Di-mount oleh RegistryServiceProvider di prefix /api/v1/registry dengan
| middleware group: api + api.auth + api.log.
|
| Read-only. Registry adalah data master yang diubah lewat UI dengan jejak
| audit; membiarkannya diubah lewat token akan membuat dua aplikasi bisa
| saling menimpa data organisasi tanpa ada yang tahu siapa yang mengubah.
|
| Scope keanggotaan dipisah dari OPD dan aset: yang dua pertama data
| organisasi, yang terakhir memetakan ORANG ke organisasi.
*/

Route::middleware('scope:registry.opd.read')->group(function () {
    Route::get('/opd', [OpdController::class, 'index'])->name('opd.index');
    Route::get('/opd/{code}', [OpdController::class, 'show'])->name('opd.show');
});

Route::middleware('scope:registry.asset.read')->group(function () {
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/{id}', [AssetController::class, 'show'])
        ->whereNumber('id')
        ->name('assets.show');
});

Route::middleware('scope:registry.membership.read')->group(function () {
    Route::get('/memberships', [MembershipController::class, 'index'])->name('memberships.index');
});
