<?php

use App\Models\Corso;
use App\Services\FormSectionsGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->jsonb('giorni_presenza')->nullable()->after('residenziale');
        });

        // Backfill: per ogni corso, i giorni in presenza = date delle voci pasto ATTIVE
        // (attivo !== false) che rientrano nei giorni_evento dell'evento collegato.
        // Verificato sui dati reali (tutti i corsi con risposte): nessuna risposta cade
        // fuori dai giorni_evento, quindi questo backfill non perde alcuna risposta.
        // Poi si rigenerano le form_sections dai giorni_presenza, tranne per i corsi il
        // cui form è già stato compilato (restano com'erano — congelati).
        $generator = app(FormSectionsGenerator::class);

        Corso::with('evento')->chunkById(100, function ($corsi) use ($generator) {
            foreach ($corsi as $corso) {
                $giorniEvento = collect($corso->evento?->giorni_evento ?? [])->flip();

                $giorniPresenza = collect($corso->form_sections ?? [])
                    ->reject(fn ($s) => ($s['attivo'] ?? true) === false)
                    ->pluck('data')
                    ->filter(fn ($d) => $giorniEvento->has($d))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $corso->giorni_presenza = $giorniPresenza;

                // saveQuietly: non far scattare gli observer/hook durante la migrazione
                if (! $corso->haRisposte()) {
                    $corso->form_sections = $generator->calcola($corso);
                }

                $corso->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('corsi', function (Blueprint $table) {
            $table->dropColumn('giorni_presenza');
        });
    }
};
