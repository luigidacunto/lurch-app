<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Configurazione dei laboratori: unica parte con cascadeOnDelete (nessun dato di valutazione).
// Il numero di laboratori è il numero di righe di esame_laboratori.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esame_gruppi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->cascadeOnDelete();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('esame_gruppo_discente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppo_id')->constrained('esame_gruppi')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();

            $table->unique(['gruppo_id', 'socio_id']);
        });

        Schema::create('esame_gruppo_docente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppo_id')->constrained('esame_gruppi')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('soci')->cascadeOnDelete();

            $table->unique(['gruppo_id', 'socio_id']);
        });

        Schema::create('esame_laboratori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('data')->nullable();
            $table->timestamps();

            $table->unique(['esame_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esame_laboratori');
        Schema::dropIfExists('esame_gruppo_docente');
        Schema::dropIfExists('esame_gruppo_discente');
        Schema::dropIfExists('esame_gruppi');
    }
};
