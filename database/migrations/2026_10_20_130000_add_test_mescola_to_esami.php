<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test scritto: interruttore «mescola domande e risposte» per ogni visualizzazione del discente (default spento = ordine
 * di origine). Colonna nuova con default: nessun dato esistente cambia. La risposta salvata resta l'indice originale
 * dell'opzione (stabile, indipendente dalla posizione mostrata).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esami', function (Blueprint $table) {
            $table->boolean('test_mescola')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('esami', function (Blueprint $table) {
            $table->dropColumn('test_mescola');
        });
    }
};
