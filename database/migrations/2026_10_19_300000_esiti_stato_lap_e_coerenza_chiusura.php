<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Chiusura dell'esame. `esiti` non è in produzione (tabelle esami modificabili fino all'annuncio) e nessun codice
// l'ha mai scritta: se la trova non vuota si ferma, senza indovinare cosa farne.
//  - esiti: + stato (calcolato | non_ammesso), + lap usato, + motivo_non_ammesso; CHECK di coerenza con punteggio/idoneo.
//  - esami: CHECK (stato = 'chiuso') <=> chiuso_il valorizzato (la riapertura azzera chiuso_il).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('esiti')->exists()) {
            throw new RuntimeException('esiti contiene già righe: la migrazione non sa come convertirle. Fermarsi e verificare prima di procedere.');
        }
        if (DB::table('esami')->where('stato', 'chiuso')->exists()) {
            throw new RuntimeException("Esistono esami chiusi: il CHECK di coerenza richiede chiuso_il valorizzato. Fermarsi e verificare prima di procedere.");
        }

        Schema::table('esiti', function (Blueprint $table) {
            $table->string('stato', 20);
            $table->decimal('lap', 5, 2);
            $table->text('motivo_non_ammesso')->nullable();
        });
        DB::statement("ALTER TABLE esiti ADD CONSTRAINT esiti_stato_check CHECK (stato IN ('calcolato','non_ammesso'))");
        DB::statement("ALTER TABLE esiti ADD CONSTRAINT esiti_coerenza_check CHECK ("
            ."(stato = 'calcolato' AND punteggio_finale IS NOT NULL AND idoneo IS NOT NULL AND motivo_non_ammesso IS NULL)"
            ." OR (stato = 'non_ammesso' AND punteggio_finale IS NULL AND idoneo IS NULL AND motivo_non_ammesso IS NOT NULL AND btrim(motivo_non_ammesso) <> ''))");

        DB::statement("ALTER TABLE esami ADD CONSTRAINT esami_chiusura_coerente_check CHECK ((stato = 'chiuso') = (chiuso_il IS NOT NULL))");
    }

    public function down(): void
    {
        if (DB::table('esiti')->exists()) {
            throw new RuntimeException('Rollback rifiutato: esiti contiene righe.');
        }
        DB::statement('ALTER TABLE esami DROP CONSTRAINT IF EXISTS esami_chiusura_coerente_check');
        DB::statement('ALTER TABLE esiti DROP CONSTRAINT IF EXISTS esiti_coerenza_check');
        DB::statement('ALTER TABLE esiti DROP CONSTRAINT IF EXISTS esiti_stato_check');
        Schema::table('esiti', function (Blueprint $table) {
            $table->dropColumn(['stato', 'lap', 'motivo_non_ammesso']);
        });
    }
};
