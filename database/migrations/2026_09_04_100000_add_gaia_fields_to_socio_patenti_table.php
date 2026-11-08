<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campi provenienti dal curriculum GAIA (riga "Patente CRI"): data di rilascio, data di scadenza
 * e protocollo/codice. Tutti nullable: le patenti inserite a mano dal form staff / CheckboxList
 * di SocioResource non li valorizzano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socio_patenti', function (Blueprint $table) {
            $table->date('data_rilascio')->nullable()->after('patente_cri_id');
            $table->date('data_scadenza')->nullable()->after('data_rilascio');
            $table->string('protocollo')->nullable()->after('data_scadenza');
        });
    }

    public function down(): void
    {
        Schema::table('socio_patenti', function (Blueprint $table) {
            $table->dropColumn(['data_rilascio', 'data_scadenza', 'protocollo']);
        });
    }
};
