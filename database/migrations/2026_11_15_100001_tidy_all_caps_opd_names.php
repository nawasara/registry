<?php

use Illuminate\Database\Migrations\Migration;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Support\OpdName;

/**
 * Rapikan nama OPD yang tertulis HURUF KAPITAL SEMUA (15 Nov 2026).
 *
 * Migrasi data, bukan perintah artisan: deploy produksi selalu menjalankan
 * `migrate --force`, sedangkan perintah artisan dari paket tidak selalu
 * terdaftar di kernel produksi.
 *
 * Disimpan lewat model supaya setiap perubahan masuk log aktivitas beserta
 * nama lamanya. `simas_name` sengaja tidak disentuh (lihat OpdName).
 */
return new class extends Migration
{
    public function up(): void
    {
        Opd::query()->each(function (Opd $opd) {
            $rapi = OpdName::tidy((string) $opd->name);

            if ($rapi !== $opd->name) {
                $opd->name = $rapi;
                $opd->save();
            }
        });
    }

    public function down(): void
    {
        // Sengaja kosong: nama lama tercatat di log aktivitas tiap OPD.
    }
};
