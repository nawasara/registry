<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-units WITHIN an OPD: "BIDANG APLIKASI DAN INFORMATIKA", "SEKRETARIAT".
     *
     * Not the same thing as a school. SIMAS Hebat puts both in the same spot of
     * `unit_kerja`, but a bidang is a division inside an OPD while a school is
     * its own institution under one: it has its own assets, its own PIC, its own
     * budget, and a headmaster rather than a kepala bidang. Schools therefore
     * become OPD rows with a parent_id, not rows here. That is what keeps a
     * teacher scoped to their own school instead of to all of Dinas Pendidikan.
     *
     * `simas_name` holds the raw remainder after the OPD prefix is stripped, so
     * matching never depends on how `name` is later tidied.
     */
    public function up(): void
    {
        Schema::create('nawasara_registry_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('nawasara_registry_opd')->cascadeOnDelete();
            $table->string('name');
            $table->string('simas_name');
            $table->timestamps();

            // One sub-unit name per OPD. Two OPDs may each have a "SEKRETARIAT".
            $table->unique(['opd_id', 'simas_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_registry_units');
    }
};
