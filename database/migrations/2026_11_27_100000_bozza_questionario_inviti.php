<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bozza del questionario di gradimento: le risposte si salvano mentre il discente compila, cifrate con la chiave
 * dell'applicazione (cast `encrypted:array`), e si cancellano all'invio (diventano la risposta anonima) o alla
 * chiusura. `aperto` = il discente ha aperto il questionario almeno una volta (nessuna data: anonimato).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionario_inviti', function (Blueprint $table) {
            $table->boolean('aperto')->default(false);
            $table->text('bozza')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questionario_inviti', function (Blueprint $table) {
            $table->dropColumn(['aperto', 'bozza']);
        });
    }
};
