<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Annotazione libera del docente sulle proprie schede personali (laboratorio e itinere): appunto, non entra nel
// calcolo né negli export; la leggono solo l'autore e il direttore. Solo aggiunta di colonne nullable: nessun dato toccato.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tcri_schede_semaforo', function (Blueprint $table) {
            $table->text('annotazione')->nullable();
        });
        Schema::table('tcri_schede_itinere', function (Blueprint $table) {
            $table->text('annotazione')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tcri_schede_semaforo', function (Blueprint $table) {
            $table->dropColumn('annotazione');
        });
        Schema::table('tcri_schede_itinere', function (Blueprint $table) {
            $table->dropColumn('annotazione');
        });
    }
};
