<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('soci')->nullOnDelete();
            $table->string('codice_fiscale');
            $table->string('nome');
            $table->string('cognome');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('prefisso_telefono')->nullable();
            $table->foreignId('comitato_regionale_id')->nullable()->constrained('comitati_regionali')->nullOnDelete();
            $table->foreignId('comitato_locale_id')->nullable()->constrained('comitati_locali')->nullOnDelete();
            $table->json('giorni_disponibili')->nullable();
            $table->string('stato')->default('in_attesa');
            $table->boolean('privacy_accettata')->default(false);
            $table->boolean('fuori_termine')->default(false);
            $table->timestamp('confermato_at')->nullable();
            $table->foreignId('confermato_da_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aggiunto_da_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['evento_id', 'codice_fiscale']);
        });

        DB::statement("ALTER TABLE candidature_staff ADD CONSTRAINT candidature_staff_stato_check CHECK (stato IN ('confermato','in_attesa','rifiutato'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_staff');
    }
};
