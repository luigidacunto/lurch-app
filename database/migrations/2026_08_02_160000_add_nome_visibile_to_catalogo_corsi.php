<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->string('nome_visibile')->nullable()->after('nome');
        });

        // Backfill: finché non arriva un valore ripulito dal JSON, il nome visualizzato
        // coincide con quello grezzo GAIA (include i record soft-deleted).
        DB::table('catalogo_corsi')->update(['nome_visibile' => DB::raw('nome')]);

        DB::statement('ALTER TABLE catalogo_corsi ALTER COLUMN nome_visibile SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->dropColumn('nome_visibile');
        });
    }
};
