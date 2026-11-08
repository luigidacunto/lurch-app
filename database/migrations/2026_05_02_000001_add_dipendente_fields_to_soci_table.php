<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->foreignId('comitato_locale_dipendente_id')
                ->nullable()
                ->after('comitato_regionale_estensione_id')
                ->constrained('comitati_locali')
                ->nullOnDelete();

            $table->foreignId('comitato_regionale_dipendente_id')
                ->nullable()
                ->after('comitato_locale_dipendente_id')
                ->constrained('comitati_regionali')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('soci', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comitato_locale_dipendente_id');
            $table->dropConstrainedForeignId('comitato_regionale_dipendente_id');
        });
    }
};
