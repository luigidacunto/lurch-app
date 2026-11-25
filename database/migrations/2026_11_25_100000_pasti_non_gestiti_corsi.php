<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pasti non forniti dall'organizzazione, per singolo pasto: elenco di chiavi `tipo_Y-m-d`
// (solo pranzo/cena) escluse dalle voci del form di preregistrazione. Solo aggiunta:
// NULL = tutti i pasti gestiti, quindi i corsi esistenti non cambiano.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->jsonb('pasti_non_gestiti')->nullable()->after('giorni_presenza');
        });
    }

    public function down(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->dropColumn('pasti_non_gestiti');
        });
    }
};
