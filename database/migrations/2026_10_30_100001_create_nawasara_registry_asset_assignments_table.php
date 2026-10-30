<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Who is responsible for an asset, possibly several people.
|
| `pj_user_id` on the asset holds ONE person, but an agency site or a shared
| mailbox is usually looked after by two or three: a primary and backups,
| sometimes from another agency. The ASN account portal shows a civil servant
| every site and mailbox they look after, and opens agency mailboxes for them
| without a password, so "who is a PIC of what" must be complete.
|
| `pj_user_id` stays as the primary PIC and is mirrored here as
| role=primary (Asset::booted keeps the two in step), so every existing
| screen and package that reads `pj_user_id` keeps working unchanged.
|
| Keyed by user_id: PICs are picked from the Keycloak directory and
| provisioned as users when appointed (PicPicker), so every PIC has a row,
| and its keycloak_id links back to the portal's token `sub`.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_registry_asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('nawasara_registry_assets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // primary | backup
            $table->string('role', 10);

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'user_id'], 'registry_assignment_asset_user_unique');
            $table->index(['user_id', 'role'], 'registry_assignment_user_role_index');
        });

        // Existing primary PICs become role=primary rows.
        DB::table('nawasara_registry_assets')
            ->whereNotNull('pj_user_id')
            ->orderBy('id')
            ->select(['id', 'pj_user_id'])
            ->chunk(500, function ($assets) {
                DB::table('nawasara_registry_asset_assignments')->insert(
                    $assets->map(fn ($a) => [
                        'asset_id' => $a->id,
                        'user_id' => $a->pj_user_id,
                        'role' => 'primary',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_registry_asset_assignments');
    }
};
