<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data kepegawaian kini diperbarui terus, bukan hanya saat penetapan PIC.
     *
     * SIMAS Hebat memecah `unit_kerja` menjadi `skpd` + `unit` (12 November
     * 2026), jadi keduanya disimpan mentah di kolom sendiri. `simas_unit_kerja`
     * dibiarkan untuk baris lama.
     *
     * `simas_opd_id` adalah OPD Registry yang cocok dengan `skpd`. Disimpan
     * terpisah dari keanggotaan supaya layar dapat menunjukkan "SIMAS menyebut
     * OPD yang belum dikenal Registry", satu-satunya keadaan ketika
     * keanggotaan tidak dapat mengikuti SIMAS.
     *
     * `simas_checked_at` adalah percobaan terakhir, berhasil atau gagal.
     * `simas_synced_at` hanya bergerak ketika datanya benar-benar diterima,
     * sehingga "terakhir sinkron" tidak berbohong saat SIMAS sedang mati.
     */
    public function up(): void
    {
        Schema::table('nawasara_registry_employees', function (Blueprint $table) {
            $table->string('simas_nama')->nullable()->after('nip');
            $table->string('simas_skpd')->nullable()->after('simas_unit_kerja');
            $table->string('simas_unit')->nullable()->after('simas_skpd');
            $table->foreignId('simas_opd_id')->nullable()->after('simas_unit')
                ->constrained('nawasara_registry_opd')->nullOnDelete();

            // ok | not_found | error
            $table->string('simas_status', 16)->nullable()->after('simas_opd_id');
            $table->string('simas_error')->nullable()->after('simas_status');
            // login | manual | pic
            $table->string('simas_source', 16)->nullable()->after('simas_error');
            $table->timestamp('simas_checked_at')->nullable()->after('simas_synced_at');

            $table->index('simas_status');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_registry_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('simas_opd_id');
            $table->dropIndex(['simas_status']);
            $table->dropColumn([
                'simas_nama', 'simas_skpd', 'simas_unit',
                'simas_status', 'simas_error', 'simas_source', 'simas_checked_at',
            ]);
        });
    }
};
