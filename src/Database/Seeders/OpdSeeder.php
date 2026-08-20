<?php

namespace Nawasara\Registry\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Registry\Models\Opd;

/**
 * Perangkat daerah yang belum terdaftar tetapi sudah dirujuk paket lain.
 *
 * Dibuat karena `nawasara/aspirations` memetakan kategori laporan ke OPD lewat
 * `code`. Kategori yang OPD-nya tidak ada di registry TIDAK dapat didisposisi
 * otomatis — laporannya berhenti di pintu masuk, menunggu dialihkan manual.
 *
 * Idempoten: dicocokkan lewat `code`, jadi aman dijalankan berulang di setiap
 * deploy.
 *
 * ⚠️ Nama HANYA diisi saat OPD pertama kali dibuat. Sesudahnya, nama adalah
 * milik admin: nomenklatur berubah setiap ada perubahan SOTK, dan seeder yang
 * menimpanya di setiap deploy akan mengembalikan nama yang baru saja
 * diperbaiki. Nama ini juga tampil ke warga ("ditangani oleh …"), sehingga
 * salah nama bukan sekadar soal rapi.
 */
class OpdSeeder extends Seeder
{
    /**
     * Kode memakai ALIAS singkat, mengikuti pola yang sudah ada di registry
     * (DPUPKP, DISBUDPARPORA, PERDAGKUM) — bukan nama panjang bersambung.
     */
    protected const OPD = [
        ['code' => 'DLH', 'name' => 'Dinas Lingkungan Hidup'],
        ['code' => 'SATPOLPP', 'name' => 'Satuan Polisi Pamong Praja'],
        ['code' => 'DISNAKER', 'name' => 'Dinas Tenaga Kerja'],
        ['code' => 'INSPEKTORAT', 'name' => 'Inspektorat Kabupaten Ponorogo'],

        // Ditambahkan 20 Agustus 2026: kategori Bencana di Lapor Bunda
        // menunjuk ke sini, dan tanpa BPBD terdaftar ia lahir tanpa OPD —
        // laporan bencana berhenti di antrean Admin Kabupaten alih-alih
        // terdisposisi otomatis. Itu kategori dengan SLA paling ketat di
        // seluruh daftar (3 jam), jadi justru yang paling tidak boleh
        // menunggu penerusan manual.
        ['code' => 'BPBD', 'name' => 'Badan Penanggulangan Bencana Daerah'],

        // BAPPEDA sengaja TIDAK ada di sini. Badan yang sama sudah terdaftar
        // sebagai BAPPERIDA di produksi — nomenklaturnya berganti menjadi
        // "Badan Perencanaan Pembangunan, Riset dan Inovasi Daerah". Menyeed
        // BAPPEDA akan membuat OPD KEDUA untuk badan yang sama, dan laporan
        // warga akan terbelah ke dua tujuan yang berbeda.
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::OPD as $row) {
            $existing = Opd::where('code', $row['code'])->first();

            if ($existing) {
                // Sengaja tidak menyentuh apa pun — lihat catatan kelas.
                continue;
            }

            Opd::create($row);
            $created++;
        }

        $this->command?->info("  OPD ditambahkan: {$created}");
    }
}
