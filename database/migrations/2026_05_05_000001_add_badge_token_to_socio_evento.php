<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socio_evento', function (Blueprint $table) {
            $table->uuid('badge_token')->nullable()->unique()->after('abilitato_app');
        });

        // Genera token univoci per i record già esistenti tramite gen_random_uuid() nativo PostgreSQL
        DB::statement("UPDATE socio_evento SET badge_token = gen_random_uuid() WHERE badge_token IS NULL");
    }

    public function down(): void
    {
        Schema::table('socio_evento', function (Blueprint $table) {
            $table->dropColumn('badge_token');
        });
    }
};
