<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifiche', function (Blueprint $table) {
            // Rimuovi colonne del vecchio schema
            $table->dropConstrainedForeignId('comitato_regionale_id');
            $table->dropColumn(['ordine', 'attiva']);
        });

        Schema::table('qualifiche', function (Blueprint $table) {
            // Aggiungi nuove colonne
            $table->string('codice', 50)->unique()->after('id');
            $table->string('tipo')->after('nome');
            $table->string('obiettivo')->after('tipo');
            $table->string('livello')->nullable()->after('obiettivo');
            $table->boolean('attivo')->default(true)->after('descrizione');
            $table->unsignedSmallInteger('mesi_validita')->nullable()->after('attivo')
                ->comment('Se valorizzato, la qualifica ha una scadenza (es. 24 mesi)');
            $table->json('corsi_aggiornamento')->nullable()->after('mesi_validita')
                ->comment('Codici dei corsi validi come aggiornamento di questa qualifica');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('qualifiche', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['codice', 'tipo', 'obiettivo', 'livello', 'attivo', 'mesi_validita', 'corsi_aggiornamento']);
        });

        Schema::table('qualifiche', function (Blueprint $table) {
            $table->foreignId('comitato_regionale_id')->nullable()->constrained('comitati_regionali')->cascadeOnDelete();
            $table->integer('ordine')->default(0);
            $table->boolean('attiva')->default(true);
        });
    }
};
