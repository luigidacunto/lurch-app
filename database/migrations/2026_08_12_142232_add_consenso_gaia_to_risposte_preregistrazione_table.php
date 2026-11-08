<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->boolean('consenso_gaia_accettata')->default(false)->after('privacy_accettata');
        });

        // Form già compilati prima dell'introduzione di questo consenso specifico: stessa
        // sanatoria-placeholder già applicata a graduatoria_candidati/candidature_staff/soci
        // — evita che righe di produzione esistenti
        // risultino "senza consenso" per un campo appena introdotto.
        DB::table('risposte_preregistrazione')->update(['consenso_gaia_accettata' => true]);
    }

    public function down(): void
    {
        Schema::table('risposte_preregistrazione', function (Blueprint $table) {
            $table->dropColumn('consenso_gaia_accettata');
        });
    }
};
