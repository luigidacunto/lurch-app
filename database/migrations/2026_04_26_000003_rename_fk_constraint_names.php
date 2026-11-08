<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi_validita_regionali', function (Blueprint $table) {
            $table->dropForeign('qualifica_validita_regionali_qualifica_id_foreign');
            $table->foreign('catalogo_corso_id')
                ->references('id')
                ->on('catalogo_corsi')
                ->cascadeOnDelete();
        });

        Schema::table('qualifiche_soci', function (Blueprint $table) {
            $table->dropForeign('socio_qualifiche_qualifica_id_foreign');
            $table->foreign('catalogo_corso_id')
                ->references('id')
                ->on('catalogo_corsi')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi_validita_regionali', function (Blueprint $table) {
            $table->dropForeign(['catalogo_corso_id']);
            $table->foreign('catalogo_corso_id', 'qualifica_validita_regionali_qualifica_id_foreign')
                ->references('id')
                ->on('catalogo_corsi')
                ->cascadeOnDelete();
        });

        Schema::table('qualifiche_soci', function (Blueprint $table) {
            $table->dropForeign(['catalogo_corso_id']);
            $table->foreign('catalogo_corso_id', 'socio_qualifiche_qualifica_id_foreign')
                ->references('id')
                ->on('catalogo_corsi')
                ->cascadeOnDelete();
        });
    }
};
