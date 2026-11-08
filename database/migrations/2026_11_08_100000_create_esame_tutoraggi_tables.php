<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gruppi di tutoraggio dell'esame: solo configurazione (cascadeOnDelete come i gruppi dei laboratori), nessun dato di
// valutazione. Migrazione solo additiva: nessuna tabella esistente viene modificata.
// Un discente sta in un solo gruppo di tutoraggio per esame (regola applicativa, come i gruppi dei laboratori).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esame_tutoraggi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->cascadeOnDelete();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('esame_tutoraggio_discente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutoraggio_id')->constrained('esame_tutoraggi')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();

            $table->unique(['tutoraggio_id', 'socio_id']);
        });

        Schema::create('esame_tutoraggio_docente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutoraggio_id')->constrained('esame_tutoraggi')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();

            $table->unique(['tutoraggio_id', 'socio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esame_tutoraggio_docente');
        Schema::dropIfExists('esame_tutoraggio_discente');
        Schema::dropIfExists('esame_tutoraggi');
    }
};
