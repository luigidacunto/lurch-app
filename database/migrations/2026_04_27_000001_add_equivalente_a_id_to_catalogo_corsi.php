<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->foreignId('equivalente_a_id')
                ->nullable()
                ->after('attivo')
                ->constrained('catalogo_corsi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->dropForeign(['equivalente_a_id']);
            $table->dropColumn('equivalente_a_id');
        });
    }
};
