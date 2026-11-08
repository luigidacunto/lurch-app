<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Test scritto. Le tabelle del modulo esami non sono in produzione (modificabili fino all'annuncio)
// e nessun codice ha mai scritto su test_domande/test_consegne/test_risposte: se le trova non vuote si ferma,
// senza indovinare cosa farne.
//  - esami: via test_attivato_il (sostituito da test_avvii); +configurazione del punteggio (penalità attive, 3 valori uguali per tutte le domande).
//  - test_domande: via punti e penalità per domanda (ora per esame); CHECK su indice della corretta e numero delle opzioni (4, base 0).
//  - test_avvii (nuova): avvio per discente, reversibile fino all'invio (avviato_il NULL = disattivato), token per invalidare i moduli già aperti.
//  - test_consegne: consegnato_il nullable (NULL = in compilazione), il motivo del cartaceo passa ad test_avvii.
//  - test_risposte: CHECK su opzione 0..3.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['test_domande', 'test_consegne', 'test_risposte'] as $tabella) {
            if (DB::table($tabella)->exists()) {
                throw new RuntimeException("{$tabella} contiene già righe: la migrazione non sa come convertirle. Fermarsi e verificare prima di procedere.");
            }
        }

        Schema::table('esami', function (Blueprint $table) {
            $table->dropColumn('test_attivato_il');
            $table->boolean('test_penalita_attive')->default(false);
            $table->decimal('test_punti_corretta', 4, 1)->default(1);
            $table->decimal('test_penalita_errata', 4, 1)->default(0);
            $table->decimal('test_penalita_vuota', 4, 1)->default(0);
        });
        DB::statement('ALTER TABLE esami ADD CONSTRAINT esami_test_punteggio_check CHECK (test_punti_corretta > 0 AND test_penalita_errata >= 0 AND test_penalita_vuota >= 0)');

        Schema::table('test_domande', function (Blueprint $table) {
            $table->dropColumn(['punti', 'penalita_errata', 'penalita_vuota']);
        });
        DB::statement("ALTER TABLE test_domande ADD CONSTRAINT test_domande_corretta_check CHECK (corretta BETWEEN 0 AND 3)");
        DB::statement("ALTER TABLE test_domande ADD CONSTRAINT test_domande_opzioni_check CHECK (jsonb_typeof(opzioni) = 'array' AND jsonb_array_length(opzioni) = 4)");

        Schema::create('test_avvii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esame_id')->constrained('esami')->restrictOnDelete();
            $table->foreignId('discente_socio_id')->constrained('soci')->restrictOnDelete();
            $table->string('modalita', 20)->default('online');   // online | cartaceo
            $table->timestamp('avviato_il')->nullable();         // NULL = disattivato; altrimenti ultima attivazione (non mostrata)
            $table->uuid('token');                               // rigenerato a ogni attivazione: invalida i moduli già aperti
            $table->text('motivo_cartaceo')->nullable();         // BES/DSA, obbligatorio se cartaceo
            $table->foreignId('avviato_da')->nullable()->constrained('soci')->nullOnDelete();
            $table->timestamps();

            $table->unique(['esame_id', 'discente_socio_id']);
        });
        DB::statement("ALTER TABLE test_avvii ADD CONSTRAINT test_avvii_modalita_check CHECK (modalita IN ('online','cartaceo'))");
        DB::statement("ALTER TABLE test_avvii ADD CONSTRAINT test_avvii_motivo_check CHECK (modalita = 'online' OR (motivo_cartaceo IS NOT NULL AND btrim(motivo_cartaceo) <> ''))");

        // La riga esiste dal primo tocco (bozza di compilazione); consegnato_il valorizzato = test Inviato.
        Schema::table('test_consegne', function (Blueprint $table) {
            $table->dropColumn('motivo_cartaceo');
        });
        DB::statement('ALTER TABLE test_consegne ALTER COLUMN consegnato_il DROP NOT NULL');
        DB::statement("ALTER TABLE test_consegne ADD CONSTRAINT test_consegne_origine_check CHECK ((origine = 'online' AND inserito_da IS NULL) OR (origine = 'cartaceo' AND inserito_da IS NOT NULL))");

        DB::statement('ALTER TABLE test_risposte ADD CONSTRAINT test_risposte_opzione_check CHECK (opzione IS NULL OR opzione BETWEEN 0 AND 3)');
    }

    public function down(): void
    {
        if (DB::table('test_avvii')->exists()) {
            throw new RuntimeException('test_avvii contiene avvii: il rollback li cancellerebbe. Svuotare a mano solo dopo verifica.');
        }
        if (DB::table('test_consegne')->whereNull('consegnato_il')->exists()) {
            throw new RuntimeException('test_consegne contiene compilazioni non inviate: il rollback non può renderle NOT NULL.');
        }

        DB::statement('ALTER TABLE test_risposte DROP CONSTRAINT test_risposte_opzione_check');

        DB::statement('ALTER TABLE test_consegne DROP CONSTRAINT test_consegne_origine_check');
        DB::statement('ALTER TABLE test_consegne ALTER COLUMN consegnato_il SET NOT NULL');
        Schema::table('test_consegne', function (Blueprint $table) {
            $table->text('motivo_cartaceo')->nullable()->after('origine');
        });

        Schema::dropIfExists('test_avvii');

        DB::statement('ALTER TABLE test_domande DROP CONSTRAINT test_domande_opzioni_check');
        DB::statement('ALTER TABLE test_domande DROP CONSTRAINT test_domande_corretta_check');
        Schema::table('test_domande', function (Blueprint $table) {
            $table->decimal('punti', 6, 2)->default(1);
            $table->decimal('penalita_errata', 6, 2)->default(0);
            $table->decimal('penalita_vuota', 6, 2)->default(0);
        });

        DB::statement('ALTER TABLE esami DROP CONSTRAINT esami_test_punteggio_check');
        Schema::table('esami', function (Blueprint $table) {
            $table->dropColumn(['test_penalita_attive', 'test_punti_corretta', 'test_penalita_errata', 'test_penalita_vuota']);
            $table->timestamp('test_attivato_il')->nullable();
        });
    }
};
