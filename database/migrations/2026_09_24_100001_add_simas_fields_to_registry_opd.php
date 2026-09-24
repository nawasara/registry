<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two additions to OPD, both for the SIMAS Hebat lookup (see
     * docs/plan/plan-sinkronisasi-pegawai-simashebat.md).
     *
     * `simas_name` is deliberately separate from `name`. `name` is for people
     * to read and may be tidied at any time; `simas_name` is bound to whatever
     * the source writes and changes only when the source does. Merging them
     * would mean tidying a label silently breaks the matcher.
     *
     * Existing rows show why: production has `DINAS KOMUNIKASI, INFORMATIKA DAN
     * STATISTIK` in caps next to `Badan Kepegawaian dan ...` in title case, and
     * one row whose code carries a trailing space.
     *
     * `parent_id` records that a school or puskesmas sits under a parent OPD.
     * It does NOT change who sees what: ScopedToOpd stays `where('opd_id', ...)`,
     * one OPD exactly, no inheritance. Widening that would alter data isolation
     * in hibah, aspirations and nawasara-api at once, which is its own decision.
     */
    public function up(): void
    {
        Schema::table('nawasara_registry_opd', function (Blueprint $table) {
            $table->string('simas_name')->nullable()->index()->after('name');

            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('nawasara_registry_opd')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_registry_opd', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'simas_name']);
        });
    }
};
