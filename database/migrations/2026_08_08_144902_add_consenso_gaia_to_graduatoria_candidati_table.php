<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->boolean('consenso_gaia_accettata')->default(false)->after('privacy_accettata');
            $table->unsignedInteger('privacy_versione_accettata')->nullable()->after('consenso_gaia_accettata');
        });

        // Candidature già esistenti: il consenso GAIA specifico non esisteva ancora,
        // la sanatoria retroattiva viene gestita fuori da LURCH — qui evitiamo solo
        // che righe già in produzione risultino "senza consenso" per un campo nuovo.
        DB::table('graduatoria_candidati')->update(['consenso_gaia_accettata' => true]);
    }

    public function down(): void
    {
        Schema::table('graduatoria_candidati', function (Blueprint $table) {
            $table->dropColumn(['consenso_gaia_accettata', 'privacy_versione_accettata']);
        });
    }
};
