<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Modulo Esami: solo tabelle nuove, nessuna modifica a tabelle esistenti.
return new class extends Migration
{
    public function up(): void
    {
        // Modello d'esame versionato: struttura (prove, aree, voci, scale, pesi) come dato jsonb.
        Schema::create('esame_modelli', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_codice', 20);
            $table->unsignedSmallInteger('versione');
            $table->jsonb('struttura');
            $table->timestamps();

            $table->unique(['tipo_codice', 'versione']);
        });

        Schema::create('esami', function (Blueprint $table) {
            $table->id();
            // Un solo esame per corso. Ambito territoriale (comitato) NON duplicato qui:
            // si eredita da corsi.evento_id → eventi.comitato_*.
            $table->foreignId('corso_id')->unique()->constrained('corsi')->restrictOnDelete();
            $table->string('titolo');
            $table->string('tipo_codice', 20);
            $table->unsignedSmallInteger('modello_versione');
            $table->jsonb('modello_snapshot')->nullable(); // copiato all'avvio dell'esame
            $table->decimal('lap', 5, 2);
            $table->string('stato', 20)->default('bozza'); // bozza | in_corso | chiuso
            $table->timestamp('test_attivato_il')->nullable();
            $table->timestamp('chiuso_il')->nullable();
            $table->foreignId('chiuso_da')->nullable()->constrained('soci')->nullOnDelete(); // socio direttore
            // Ultima riapertura (solo superadmin, dal pannello): nessuno storico.
            $table->foreignId('riaperto_da')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('riaperto_il')->nullable();
            $table->text('riaperto_motivo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tipo_codice', 'modello_versione'], 'esami_modello_foreign')
                ->references(['tipo_codice', 'versione'])->on('esame_modelli')->restrictOnDelete();
        });

        // Solo i docenti del corso scelti per la commissione (0–3). Direttore, presidente o
        // delegato e docenti vivono sul corso: non si duplicano qui.
        Schema::create('esame_docenti_commissione', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('socio_id')->constrained('soci')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['esame_id', 'socio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esame_docenti_commissione');
        Schema::dropIfExists('esami');
        Schema::dropIfExists('esame_modelli');
    }
};
