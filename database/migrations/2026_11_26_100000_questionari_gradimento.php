<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questionario di gradimento dei discenti, uno per corso.
 *
 * Toglie le tabelle segnaposto `questionari` / `risposte_questionario` (mai usate dal codice): la migrazione si
 * ferma se una delle due contiene righe, così nessun dato si perde senza una decisione esplicita.
 *
 * Anonimato: `questionario_risposte` non ha riferimenti all'invito, al discente o all'utente, ha un UUID casuale
 * come chiave e nessuna data. `questionario_inviti` non ha `created_at`/`updated_at`: l'aggiornamento di
 * `compilato` registrerebbe l'ora della compilazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['risposte_questionario', 'questionari'] as $tabella) {
            if (Schema::hasTable($tabella) && DB::table($tabella)->exists()) {
                throw new RuntimeException("La tabella {$tabella} contiene dati: migrazione interrotta, nessuna modifica fatta.");
            }
        }
        Schema::dropIfExists('risposte_questionario');
        Schema::dropIfExists('questionari');

        Schema::create('questionari_gradimento', function (Blueprint $table) {
            $table->id();
            // Cascade: sparisce con il corso solo una configurazione mai compilata; con risposte il vincolo
            // restrict di `questionario_risposte` blocca l'eliminazione (e Corso::hasDatiCorrelati la segnala prima).
            $table->foreignId('corso_id')->unique()->constrained('corsi')->cascadeOnDelete();
            $table->unsignedSmallInteger('versione');
            $table->unsignedSmallInteger('giorni_apertura');
            // Sezioni facoltative attive (chiavi dei flag), copia bloccata al primo invio.
            $table->jsonb('sezioni')->default('[]');
            // Copia di direttore e docenti valutati in S9, fissata al primo invio: [{socio_id, nome, ruolo}].
            $table->jsonb('valutati')->nullable();
            $table->foreignId('inviato_da')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inviato_il')->nullable();
            $table->timestamp('chiude_il')->nullable();
            $table->timestamps();
        });

        Schema::create('questionario_inviti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionari_gradimento')->cascadeOnDelete();
            $table->foreignId('iscrizione_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->timestamp('inviato_il');
            $table->boolean('compilato')->default(false);
            $table->unique(['questionario_id', 'iscrizione_id']);
        });

        Schema::create('questionario_risposte', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('questionario_id')->constrained('questionari_gradimento')->restrictOnDelete();
            $table->jsonb('risposte');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionario_risposte');
        Schema::dropIfExists('questionario_inviti');
        Schema::dropIfExists('questionari_gradimento');

        // Ricrea le tabelle segnaposto com'erano (vuote).
        Schema::create('questionari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->string('titolo');
            $table->jsonb('domande')->default('[]');
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
        Schema::create('risposte_questionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionario_id')->constrained('questionari')->cascadeOnDelete();
            $table->uuid('token_risposta')->unique();
            $table->jsonb('risposte')->default('{}');
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
