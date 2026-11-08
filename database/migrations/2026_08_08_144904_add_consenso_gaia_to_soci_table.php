<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->boolean('consenso_gaia_accettata')->default(false)->after('gaia_persona_id');
            $table->unsignedInteger('privacy_versione_accettata')->nullable()->after('consenso_gaia_accettata');
        });

        DB::table('soci')->update(['consenso_gaia_accettata' => true]);
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropColumn(['consenso_gaia_accettata', 'privacy_versione_accettata']);
        });
    }
};
