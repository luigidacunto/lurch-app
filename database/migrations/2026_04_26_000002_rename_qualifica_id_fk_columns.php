<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi_validita_regionali', function (Blueprint $table) {
            $table->renameColumn('qualifica_id', 'catalogo_corso_id');
        });

        Schema::table('qualifiche_soci', function (Blueprint $table) {
            $table->renameColumn('qualifica_id', 'catalogo_corso_id');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi_validita_regionali', function (Blueprint $table) {
            $table->renameColumn('catalogo_corso_id', 'qualifica_id');
        });

        Schema::table('qualifiche_soci', function (Blueprint $table) {
            $table->renameColumn('catalogo_corso_id', 'qualifica_id');
        });
    }
};
