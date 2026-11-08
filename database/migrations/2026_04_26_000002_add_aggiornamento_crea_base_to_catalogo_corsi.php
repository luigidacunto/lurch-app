<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->boolean('aggiornamento_crea_base')
                ->default(false)
                ->after('corsi_aggiornamento')
                ->comment('Se true, durante import Excel crea automaticamente la qualifica base se il socio non la possiede');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_corsi', function (Blueprint $table) {
            $table->dropColumn('aggiornamento_crea_base');
        });
    }
};
