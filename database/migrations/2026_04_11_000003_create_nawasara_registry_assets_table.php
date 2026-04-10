<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_registry_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('nawasara_registry_opd')->cascadeOnDelete();
            $table->foreignId('pic_id')->nullable()->constrained('nawasara_registry_pic')->nullOnDelete();
            $table->string('type');
            $table->string('identifier');
            $table->string('package_ref')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->string('ticket_ref')->nullable();
            $table->date('registered_at')->nullable();
            $table->timestamps();

            $table->index(['opd_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_registry_assets');
    }
};
