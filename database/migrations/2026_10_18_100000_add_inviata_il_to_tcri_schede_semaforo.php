<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bozza vs inviata per la scheda di laboratorio. NULL = bozza; valorizzata solo con tutte le voci compilate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tcri_schede_semaforo', function (Blueprint $table) {
            $table->timestamp('inviata_il')->nullable()->after('docente_socio_id');
        });
    }

    public function down(): void
    {
        Schema::table('tcri_schede_semaforo', function (Blueprint $table) {
            $table->dropColumn('inviata_il');
        });
    }
};
