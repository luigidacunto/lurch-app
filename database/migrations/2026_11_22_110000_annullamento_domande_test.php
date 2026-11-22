<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Domanda del test annullata dal direttore (solo aggiunte): resta con le risposte di tutti ma esce dal calcolo.
// NULL in `annullata_il` = domanda valida. Motivazione obbligatoria quando è annullata (CHECK).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_domande', function (Blueprint $table) {
            $table->timestamp('annullata_il')->nullable()->after('corretta');
            $table->foreignId('annullata_da')->nullable()->after('annullata_il')->constrained('soci')->nullOnDelete();
            $table->text('motivo_annullamento')->nullable()->after('annullata_da');
        });
        DB::statement('ALTER TABLE test_domande ADD CONSTRAINT test_domande_annullata_motivo_check CHECK (annullata_il IS NULL OR motivo_annullamento IS NOT NULL)');
    }

    public function down(): void
    {
        if (DB::table('test_domande')->whereNotNull('annullata_il')->exists()) {
            throw new RuntimeException('Esistono domande annullate: tornando indietro rientrerebbero nel calcolo. Ripristinarle dal pannello prima di procedere.');
        }
        DB::statement('ALTER TABLE test_domande DROP CONSTRAINT test_domande_annullata_motivo_check');
        Schema::table('test_domande', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annullata_da');
            $table->dropColumn(['annullata_il', 'motivo_annullamento']);
        });
    }
};
