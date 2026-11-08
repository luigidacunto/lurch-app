<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidature_staff', function (Blueprint $table) {
            $table->json('disponibilita')->nullable()->after('giorni_disponibili');
        });

        // Backfill: ogni giorno già selezionato diventa mattina+pomeriggio (non restringiamo
        // la disponibilità già dichiarata da chi ha candidato prima di questa modifica).
        DB::table('candidature_staff')->select('id', 'giorni_disponibili')->orderBy('id')->each(function ($riga) {
            $giorni = json_decode($riga->giorni_disponibili ?? '[]', true) ?: [];
            $disponibilita = [];
            foreach ($giorni as $data) {
                $disponibilita[] = ['data' => $data, 'porzione' => 'mattina'];
                $disponibilita[] = ['data' => $data, 'porzione' => 'pomeriggio'];
            }

            DB::table('candidature_staff')->where('id', $riga->id)->update([
                'disponibilita' => json_encode($disponibilita),
            ]);
        });

        Schema::table('candidature_staff', function (Blueprint $table) {
            $table->dropColumn('giorni_disponibili');
        });
    }

    public function down(): void
    {
        Schema::table('candidature_staff', function (Blueprint $table) {
            $table->json('giorni_disponibili')->nullable()->after('disponibilita');
        });

        DB::table('candidature_staff')->select('id', 'disponibilita')->orderBy('id')->each(function ($riga) {
            $disponibilita = json_decode($riga->disponibilita ?? '[]', true) ?: [];
            $giorni = collect($disponibilita)->pluck('data')->unique()->values()->all();

            DB::table('candidature_staff')->where('id', $riga->id)->update([
                'giorni_disponibili' => json_encode($giorni),
            ]);
        });

        Schema::table('candidature_staff', function (Blueprint $table) {
            $table->dropColumn('disponibilita');
        });
    }
};
