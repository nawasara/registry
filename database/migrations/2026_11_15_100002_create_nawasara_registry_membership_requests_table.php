<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan petugas baru oleh admin OPD (15 Nov 2026).
 *
 * Admin OPD MENGAJUKAN, admin Registry MENYETUJUI. Admin OPD tidak pernah
 * menautkan sendiri: menarik pegawai ke OPD berarti menarik seluruh laporan
 * yang terlihat olehnya (lihat MembershipResolver yang fail-closed).
 *
 * Internal, tidak pernah dirujuk dari luar → id bigint (AGENTS.md §10a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_registry_membership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('nawasara_registry_opd')->cascadeOnDelete();
            $table->string('nip', 32);
            // Nama dari direktori Keycloak saat diajukan — supaya penyetuju
            // melihat SIAPA, bukan sekadar 18 digit angka.
            $table->string('name')->nullable();
            $table->string('role', 64);
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['opd_id', 'status']);
            $table->index(['nip', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_registry_membership_requests');
    }
};
