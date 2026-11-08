<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_domande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->unsignedSmallInteger('ordine');
            $table->text('testo');
            $table->jsonb('opzioni');                       // 4 opzioni
            $table->unsignedSmallInteger('corretta');       // indice della risposta corretta
            $table->decimal('punti', 6, 2)->default(1);
            $table->decimal('penalita_errata', 6, 2)->default(0);
            $table->decimal('penalita_vuota', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['esame_id', 'ordine']);
        });

        // La riga esiste = test consegnato (una sola per esame+discente). Distingue
        // "risposta lasciata vuota" (test_risposte.opzione NULL dentro una consegna)
        // da "test non consegnato" (nessuna consegna). Origine/motivo stanno qui, non per risposta.
        Schema::create('test_consegne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->timestamp('consegnato_il');
            $table->string('origine', 20)->default('online'); // online | cartaceo
            $table->text('motivo_cartaceo')->nullable();      // BES/DSA
            $table->foreignId('inserito_da')->nullable()->constrained('soci')->nullOnDelete();
            $table->timestamps();

            $table->unique(['esame_id', 'discente_socio_id']);
        });

        Schema::create('test_risposte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consegna_id')->constrained('test_consegne')->restrictOnDelete();
            $table->foreignId('domanda_id')->constrained('test_domande')->restrictOnDelete();
            $table->unsignedSmallInteger('opzione')->nullable(); // NULL = lasciata vuota
            $table->timestamps();

            $table->unique(['consegna_id', 'domanda_id']);
        });

        // Snapshot alla chiusura dell'esame (mai ricalcolato).
        Schema::create('esiti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->jsonb('totali');
            $table->decimal('punteggio_finale', 4, 1)->nullable();
            $table->boolean('idoneo')->nullable();
            $table->string('tipo_codice', 20);
            $table->unsignedSmallInteger('modello_versione');
            $table->timestamp('chiuso_il');
            $table->timestamps();

            $table->unique(['esame_id', 'discente_socio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esiti');
        Schema::dropIfExists('test_risposte');
        Schema::dropIfExists('test_consegne');
        Schema::dropIfExists('test_domande');
    }
};
