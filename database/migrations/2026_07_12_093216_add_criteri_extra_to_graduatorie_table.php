<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->json('qualifiche_propedeutiche')->nullable()->after('selezione_a_rotazione_comitati');
            $table->decimal('punteggio_diversifica_comitato', 6, 2)->nullable()->after('preferenza_diversifica_comitato');
            $table->boolean('priorita_eta_a_parita_punteggio')->default(false)->after('selezione_a_rotazione_comitati');
        });
    }

    public function down(): void
    {
        Schema::table('graduatorie', function (Blueprint $table) {
            $table->dropColumn(['qualifiche_propedeutiche', 'punteggio_diversifica_comitato', 'priorita_eta_a_parita_punteggio']);
        });
    }
};
