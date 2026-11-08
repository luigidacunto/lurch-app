<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Esami TCRI: non ammissione all'esame sull'iscrizione. Nessuna data.
// Il default costante `false` non riscrive la tabella (PostgreSQL >= 11): le iscrizioni
// esistenti risultano "ammesse". Il ritirato resta lo stato `ritirato`. Il motivo
// obbligatorio alla marcatura è validazione dell'azione in pannello.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->boolean('non_ammesso')->default(false);
            $table->text('non_ammesso_motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->dropColumn(['non_ammesso', 'non_ammesso_motivo']);
        });
    }
};
