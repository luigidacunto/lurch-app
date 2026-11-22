<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Note libere sulla prova finale, solo aggiunte (nessun dato esistente cambia):
//  - tcri_prove_pratiche: `annotazione` (scheda personale, come i laboratori) e `motivazione` (scheda finale, facoltativa).
//  - tcri_colloqui: il voto della scheda PERSONALE diventa facoltativo, così l'impressione si scrive anche prima del voto;
//    la scheda FINALE resta con voto obbligatorio (vincolo CHECK).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tcri_prove_pratiche', function (Blueprint $table) {
            $table->text('annotazione')->nullable()->after('inviata_il');
            $table->text('motivazione')->nullable()->after('annotazione');
        });

        DB::statement('ALTER TABLE tcri_colloqui ALTER COLUMN punteggio DROP NOT NULL');
        DB::statement("ALTER TABLE tcri_colloqui ADD CONSTRAINT tcri_colloqui_finale_punteggio_check CHECK (tipo <> 'finale' OR punteggio IS NOT NULL)");
    }

    public function down(): void
    {
        if (DB::table('tcri_colloqui')->whereNull('punteggio')->exists()) {
            throw new RuntimeException('Esistono schede personali del colloquio senza voto (solo impressione): il voto non può tornare obbligatorio. Fermarsi e verificare prima di procedere.');
        }
        DB::statement('ALTER TABLE tcri_colloqui DROP CONSTRAINT tcri_colloqui_finale_punteggio_check');
        DB::statement('ALTER TABLE tcri_colloqui ALTER COLUMN punteggio SET NOT NULL');

        Schema::table('tcri_prove_pratiche', function (Blueprint $table) {
            $table->dropColumn(['annotazione', 'motivazione']);
        });
    }
};
