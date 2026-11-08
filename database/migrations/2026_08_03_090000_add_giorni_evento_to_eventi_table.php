<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->json('giorni_evento')->nullable()->after('data_fine');
        });

        // Backfill: ogni evento esistente riceve il suo range continuo attuale,
        // così il comportamento resta identico finché nessuno lo modifica.
        DB::table('eventi')->select('id', 'data_inizio', 'data_fine')->orderBy('id')->each(function ($evento) {
            $giorni = collect(CarbonPeriod::create($evento->data_inizio, $evento->data_fine))
                ->map(fn ($d) => $d->format('Y-m-d'))
                ->values()
                ->all();

            DB::table('eventi')->where('id', $evento->id)->update([
                'giorni_evento' => json_encode($giorni),
            ]);
        });

        Schema::table('eventi', function (Blueprint $table) {
            $table->json('giorni_evento')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn('giorni_evento');
        });
    }
};
