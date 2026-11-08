<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Esami: i gruppi appartengono a un laboratorio (tabelle del modulo non ancora in produzione,
// nessun dato da preservare: se esistessero righe in esame_gruppi la NOT NULL fallirebbe, senza perdite).
// La commissione è configurazione: sparisce con l'esame vuoto (restrict -> cascade).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esame_gruppi', function (Blueprint $table) {
            $table->foreignId('laboratorio_id')->after('esame_id')->constrained('esame_laboratori')->cascadeOnDelete();
        });

        Schema::table('esame_docenti_commissione', function (Blueprint $table) {
            $table->dropForeign(['esame_id']);
            $table->foreign('esame_id')->references('id')->on('esami')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('esame_docenti_commissione', function (Blueprint $table) {
            $table->dropForeign(['esame_id']);
            $table->foreign('esame_id')->references('id')->on('esami')->restrictOnDelete();
        });

        Schema::table('esame_gruppi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('laboratorio_id');
        });
    }
};
