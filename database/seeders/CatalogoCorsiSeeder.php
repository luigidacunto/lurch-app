<?php

namespace Database\Seeders;

use App\Models\CatalogoCorso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CatalogoCorsiSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/catalogo_corsi.json');

        if (! File::exists($path)) {
            $this->command->warn("File non trovato: {$path}");
            return;
        }

        $corsi = json_decode(File::get($path), true);

        if (! is_array($corsi)) {
            $this->command->error("Formato JSON non valido: {$path}");
            return;
        }

        $creati      = 0;
        $aggiornati  = 0;
        $disattivati = 0;

        $codiciJson = collect($corsi)
            ->map(fn ($c) => strtoupper(trim($c['codice'] ?? '')))
            ->filter()
            ->all();

        foreach ($corsi as $corso) {
            $codice = strtoupper(trim($corso['codice'] ?? ''));

            if (empty($codice)) {
                $this->command->warn("Corso senza codice ignorato: " . ($corso['nome'] ?? 'N/A'));
                continue;
            }

            $record = CatalogoCorso::withTrashed()->firstOrNew(['codice' => $codice]);
            $isNew  = ! $record->exists;

            $record->nome          = trim($corso['nome'] ?? '');
            $record->nome_visibile = trim($corso['nome_visibile'] ?? $corso['nome'] ?? '');
            $record->tipo          = trim($corso['tipo'] ?? '');
            $record->obiettivo     = trim($corso['obiettivo'] ?? '');
            $record->livello       = trim($corso['livello'] ?? '') ?: null;
            $record->descrizione   = $record->descrizione ?? null;
            $record->attivo        = $record->attivo ?? true;

            if (isset($corso['scheda_pdf']) && $corso['scheda_pdf'] !== '') {
                $record->scheda_pdf = trim($corso['scheda_pdf']);
            }

            if ($record->trashed()) {
                $record->restore();
            }

            $record->save();

            $isNew ? $creati++ : $aggiornati++;
        }

        CatalogoCorso::withTrashed()
            ->whereNotIn('codice', $codiciJson)
            ->where('gestito_manualmente', false)
            ->whereNull('deleted_at')
            ->each(function (CatalogoCorso $c) use (&$disattivati) {
                $c->delete();
                $disattivati++;
            });

        $this->command->info("Catalogo Corsi — Creati: {$creati} · Aggiornati: {$aggiornati} · Disattivati: {$disattivati}");
    }
}
