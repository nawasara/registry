<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the manual PIC concept with a "penanggung jawab" that references a
 * Laravel user (whose contact data comes from Keycloak). PIC was never
 * populated in production (0 rows), so this is a clean cutover with no data to
 * migrate. Consumer packages (cloudflare/whm/zoom) migrate their own pic_id
 * columns in their own migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent — each step guarded so a partially-applied run can be
        // re-run safely.
        if (Schema::hasColumn('nawasara_registry_assets', 'pic_id')) {
            Schema::table('nawasara_registry_assets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pic_id');
            });
        }
        if (! Schema::hasColumn('nawasara_registry_assets', 'pj_user_id')) {
            Schema::table('nawasara_registry_assets', function (Blueprint $table) {
                $table->foreignId('pj_user_id')->nullable()->after('opd_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        // Drop any lingering FK that points at the PIC table before removing it.
        // The zoom package's meeting table had `pic_id` → nawasara_registry_pic;
        // its own migration drops that column, but ordering isn't guaranteed, so
        // we defensively drop the zoom FK here first. Wrapped in try/catch so it
        // no-ops when the column/table isn't present.
        if (Schema::hasTable('nawasara_zoom_meetings') && Schema::hasColumn('nawasara_zoom_meetings', 'pic_id')) {
            try {
                Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('pic_id');
                });
            } catch (\Throwable $e) {
                // FK already gone / named differently — the zoom migration handles it.
            }
        }

        // PIC is gone entirely.
        Schema::dropIfExists('nawasara_registry_pic');
    }

    public function down(): void
    {
        Schema::create('nawasara_registry_pic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('nawasara_registry_opd')->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::table('nawasara_registry_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pj_user_id');
            $table->foreignId('pic_id')->nullable()->after('opd_id')
                ->constrained('nawasara_registry_pic')->nullOnDelete();
        });
    }
};
