<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_registry_assets', function (Blueprint $table) {
            $table->unsignedBigInteger('opd_id')->nullable()->change();
            $table->index(['package_ref', 'external_id'], 'assets_package_external_idx');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_registry_assets', function (Blueprint $table) {
            $table->dropIndex('assets_package_external_idx');
            $table->unsignedBigInteger('opd_id')->nullable(false)->change();
        });
    }
};
