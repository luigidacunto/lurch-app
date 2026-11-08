<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Colloquio e prova pratica. Le tabelle del modulo esami non sono in produzione (modificabili fino
// all'annuncio): nessun codice ha mai scritto su tcri_colloqui, quindi se la trova non vuota
// si ferma, senza indovinare un tipo/membro da assegnare alle righe.
//  - esami.prova_finale_aperta_il: interruttore «Apri la prova finale», NULL = chiuso.
//  - tcri_colloqui: una riga per discente → schede PERSONALI (membro, voto intero, impressione) + FINALE
//    (direttore, voto con 1 decimale, motivazione), con indici univoci parziali come tcri_prove_pratiche.
//  - tcri_prove_pratiche: il tipo 'commissario' diventa 'personale' (stesso nome del colloquio); +inviata_il (bozza/invia).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tcri_colloqui')->exists()) {
            throw new RuntimeException('tcri_colloqui contiene già righe: la migrazione non sa a che tipo/membro assegnarle. Fermarsi e verificare prima di procedere.');
        }

        Schema::table('esami', function (Blueprint $table) {
            $table->timestamp('prova_finale_aperta_il')->nullable()->after('test_attivato_il');
        });

        Schema::table('tcri_colloqui', function (Blueprint $table) {
            $table->dropUnique(['esame_id', 'discente_socio_id']);
            $table->string('tipo', 20)->after('discente_socio_id');
            $table->foreignId('membro_socio_id')->nullable()->after('tipo')->constrained('soci')->restrictOnDelete();
            $table->text('impressione')->nullable()->after('punteggio');
            $table->text('motivazione')->nullable()->after('impressione');
        });
        // Personale: intero 1–5 (validato dall'app); finale: fino a 1 decimale.
        DB::statement('ALTER TABLE tcri_colloqui ALTER COLUMN punteggio TYPE numeric(3,1)');
        // ⚠️ Con membro_socio_id NULL (finale) un UNIQUE normale non protegge in PostgreSQL: indici parziali.
        DB::statement("CREATE UNIQUE INDEX tcri_colloqui_finale_unique ON tcri_colloqui (esame_id, discente_socio_id) WHERE tipo = 'finale'");
        DB::statement("CREATE UNIQUE INDEX tcri_colloqui_personale_unique ON tcri_colloqui (esame_id, discente_socio_id, membro_socio_id) WHERE tipo = 'personale'");
        DB::statement("ALTER TABLE tcri_colloqui ADD CONSTRAINT tcri_colloqui_tipo_membro_check CHECK ((tipo = 'finale' AND membro_socio_id IS NULL) OR (tipo = 'personale' AND membro_socio_id IS NOT NULL))");

        // Rinomina del valore: lossless anche se esistessero righe (si cambia solo l'etichetta del tipo).
        DB::statement('ALTER TABLE tcri_prove_pratiche DROP CONSTRAINT tcri_prove_pratiche_tipo_membro_check');
        DB::statement('DROP INDEX tcri_prove_pratiche_commissario_unique');
        DB::table('tcri_prove_pratiche')->where('tipo', 'commissario')->update(['tipo' => 'personale']);
        DB::statement("CREATE UNIQUE INDEX tcri_prove_pratiche_personale_unique ON tcri_prove_pratiche (esame_id, discente_socio_id, membro_socio_id) WHERE tipo = 'personale'");
        DB::statement("ALTER TABLE tcri_prove_pratiche ADD CONSTRAINT tcri_prove_pratiche_tipo_membro_check CHECK ((tipo = 'finale' AND membro_socio_id IS NULL) OR (tipo = 'personale' AND membro_socio_id IS NOT NULL))");
        Schema::table('tcri_prove_pratiche', function (Blueprint $table) {
            $table->timestamp('inviata_il')->nullable()->after('inserito_da'); // solo schede personali; NULL = bozza
        });
    }

    public function down(): void
    {
        Schema::table('tcri_prove_pratiche', function (Blueprint $table) {
            $table->dropColumn('inviata_il');
        });
        DB::statement('ALTER TABLE tcri_prove_pratiche DROP CONSTRAINT tcri_prove_pratiche_tipo_membro_check');
        DB::statement('DROP INDEX tcri_prove_pratiche_personale_unique');
        DB::table('tcri_prove_pratiche')->where('tipo', 'personale')->update(['tipo' => 'commissario']);
        DB::statement("CREATE UNIQUE INDEX tcri_prove_pratiche_commissario_unique ON tcri_prove_pratiche (esame_id, discente_socio_id, membro_socio_id) WHERE tipo = 'commissario'");
        DB::statement("ALTER TABLE tcri_prove_pratiche ADD CONSTRAINT tcri_prove_pratiche_tipo_membro_check CHECK ((tipo = 'finale' AND membro_socio_id IS NULL) OR (tipo = 'commissario' AND membro_socio_id IS NOT NULL))");

        if (DB::table('tcri_colloqui')->exists()) {
            throw new RuntimeException('tcri_colloqui contiene schede: il rollback le cancellerebbe. Svuotare a mano solo dopo verifica.');
        }
        DB::statement('ALTER TABLE tcri_colloqui DROP CONSTRAINT tcri_colloqui_tipo_membro_check');
        DB::statement('DROP INDEX tcri_colloqui_personale_unique');
        DB::statement('DROP INDEX tcri_colloqui_finale_unique');
        Schema::table('tcri_colloqui', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membro_socio_id');
            $table->dropColumn(['tipo', 'impressione', 'motivazione']);
        });
        DB::statement('ALTER TABLE tcri_colloqui ALTER COLUMN punteggio TYPE smallint USING punteggio::smallint');
        Schema::table('tcri_colloqui', function (Blueprint $table) {
            $table->unique(['esame_id', 'discente_socio_id']);
        });

        Schema::table('esami', function (Blueprint $table) {
            $table->dropColumn('prova_finale_aperta_il');
        });
    }
};
