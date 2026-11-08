<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidature_staff', function (Blueprint $table) {
            $table->boolean('consenso_gaia_accettata')->default(false)->after('privacy_accettata');
            $table->unsignedInteger('privacy_versione_accettata')->nullable()->after('consenso_gaia_accettata');
            // Autodichiarazione del candidato in fase di inserimento CF — distinta da
            // confermato_esterno_at/_da_id, che restano la conferma lato admin.
            $table->boolean('dichiarazione_esterno')->default(false)->after('privacy_versione_accettata');
        });

        DB::table('candidature_staff')->update(['consenso_gaia_accettata' => true]);
    }

    public function down(): void
    {
        Schema::table('candidature_staff', function (Blueprint $table) {
            $table->dropColumn(['consenso_gaia_accettata', 'privacy_versione_accettata', 'dichiarazione_esterno']);
        });
    }
};
