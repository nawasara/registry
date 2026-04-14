<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_registry_assets', function (Blueprint $table) {
            $table->timestamp('discovered_at')->nullable()->after('registered_at');
            $table->index('discovered_at');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_registry_assets', function (Blueprint $table) {
            $table->dropIndex(['discovered_at']);
            $table->dropColumn('discovered_at');
        });
    }
};
