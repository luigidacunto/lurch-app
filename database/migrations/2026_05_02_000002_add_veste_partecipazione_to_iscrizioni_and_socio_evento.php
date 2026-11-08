<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->string('veste_partecipazione', 20)
                ->nullable()
                ->after('fonte');
        });

        Schema::table('socio_evento', function (Blueprint $table) {
            $table->string('veste_partecipazione', 20)
                ->nullable()
                ->after('ruolo');
        });

        // Backfill: le iscrizioni/partecipazioni precedenti alla feature dipendenti
        // appartengono a soci che erano volontari all'epoca → veste = 'volontario'
        DB::table('iscrizioni')->whereNull('veste_partecipazione')
            ->update(['veste_partecipazione' => 'volontario']);

        DB::table('socio_evento')->whereNull('veste_partecipazione')
            ->update(['veste_partecipazione' => 'volontario']);
    }

    public function down(): void
    {
        Schema::table('iscrizioni', function (Blueprint $table) {
            $table->dropColumn('veste_partecipazione');
        });

        Schema::table('socio_evento', function (Blueprint $table) {
            $table->dropColumn('veste_partecipazione');
        });
    }
};
