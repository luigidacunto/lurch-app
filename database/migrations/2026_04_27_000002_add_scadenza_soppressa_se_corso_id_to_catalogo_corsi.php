<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->foreignId('scadenza_soppressa_se_corso_id')
                ->nullable()
                ->after('equivalente_a_id')
                ->constrained('catalogo_corsi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->dropForeign(['scadenza_soppressa_se_corso_id']);
            $table->dropColumn('scadenza_soppressa_se_corso_id');
        });
    }
};
