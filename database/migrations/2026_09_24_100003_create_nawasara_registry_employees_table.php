<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employment data from SIMAS Hebat, one row per user.
     *
     * A separate table rather than columns on `users`, following what
     * nawasara/citizen already does for residents. `users` belongs to the core
     * app and every package shares it; a package that bolts its own domain
     * columns onto it makes the core's shape depend on which packages happen to
     * be installed. Registry owns employment data, so registry holds it.
     *
     * The NIP is the linking key to Keycloak, where the username IS the NIP on
     * all 10,430 production users. It is read-only from the PIC screen: a
     * mistyped digit cuts an account loose from its owner, and the unique index
     * below turns a copy-paste into another person's NIP into an error instead
     * of two accounts quietly sharing an identity.
     *
     * `simas_unit_kerja` keeps the raw string even though it has been matched
     * already, so a later fix to the matcher can re-run over stored text
     * without calling the API again, and a bad match can still be inspected
     * against what the source actually said.
     */
    public function up(): void
    {
        Schema::create('nawasara_registry_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('nip', 32)->nullable()->unique();
            $table->string('nama_jabatan')->nullable();
            $table->string('jenis_jabatan', 64)->nullable();     // Struktural | Fungsional
            $table->string('jenis_kepegawaian', 32)->nullable(); // PNS | PPPK | ...

            $table->foreignId('unit_id')->nullable()
                ->constrained('nawasara_registry_units')->nullOnDelete();

            $table->string('simas_unit_kerja')->nullable();
            $table->timestamp('simas_synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_registry_employees');
    }
};
