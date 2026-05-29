<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User ↔ OPD membership — the reusable "this user belongs to this OPD"
     * relation that any package can scope data against (hibah, assets, ...).
     * Named generically (membership, not "operator") because it's a registry
     * concept, not specific to one domain.
     *
     * One OPD per user for now (unique user_id). If multi-OPD membership is
     * ever needed, dropping this unique is non-breaking (loosening).
     */
    public function up(): void
    {
        Schema::create('nawasara_registry_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opd_id')->constrained('nawasara_registry_opd')->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_registry_memberships');
    }
};
