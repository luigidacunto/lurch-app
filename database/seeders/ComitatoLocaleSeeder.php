<?php

namespace Database\Seeders;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\ComitatoSottosede;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ComitatoLocaleSeeder extends Seeder
{
    /**
     * Carica i comitati locali da database/data/comitati_completo.json.
     * Supporta due formati:
     *   - Array root: [{ "nome": "...", "comitati_locali": [...] }, ...]  (formato attuale)
     *   - Oggetto root: { "nome": "...", "comitati_locali": [...] }       (formato legacy per singolo regionale)
     */
    public function run(): void
    {
        $file = database_path('data/comitati_completo.json');

        if (! file_exists($file)) {
            $this->command->warn('File non trovato: database/data/comitati_completo.json. Seeder saltato.');
            return;
        }

        $this->importaFile($file);
    }

    private function importaFile(string $file): void
    {
        $json = json_decode(file_get_contents($file), true);

        if (! is_array($json)) {
            $this->command->warn("File " . basename($file) . " non valido, saltato.");
            return;
        }

        // Array root = file completo con tutti i regionali; oggetto root = singolo regionale
        $voci = isset($json['nome']) ? [$json] : array_values($json);

        foreach ($voci as $dati) {
            $this->importaRegionale($dati);
        }
    }

    private function importaRegionale(array $dati): void
    {
        if (! isset($dati['nome'], $dati['comitati_locali'])) {
            $this->command->warn("Voce non valida (manca 'nome' o 'comitati_locali'), saltata.");
            return;
        }

        $nomeRegionale = $this->normalizzaNome(trim($dati['nome']));
        $regionale     = ComitatoRegionale::where('nome', $nomeRegionale)->first();

        if (! $regionale) {
            $this->command->warn("Comitato regionale '{$nomeRegionale}' non trovato nel DB. Saltato.");
            return;
        }

        $regioneCodice = $regionale->codice;
        $usedCodici    = ComitatoLocale::where('comitato_regionale_id', $regionale->id)
            ->pluck('codice')
            ->toArray();

        $creati     = 0;
        $aggiornati = 0;

        foreach ($dati['comitati_locali'] as $locData) {
            $nome      = $this->normalizzaNome(trim($locData['nome']));
            $sottosedi = array_values(array_filter(array_map('trim', $locData['sottosedi'] ?? [])));

            $codice = $this->generaCodice($nome, $regioneCodice, $usedCodici);
            $usedCodici[] = $codice;

            $locale = ComitatoLocale::withTrashed()->firstOrNew([
                'comitato_regionale_id' => $regionale->id,
                'nome'                  => $nome,
            ]);

            if ($locale->exists && $locale->trashed()) {
                $locale->restore();
            }

            $isNew = ! $locale->exists;

            $locale->fill([
                'codice' => $locale->codice ?? $codice,
                'attivo' => true,
            ])->save();

            // Sincronizza sottosedi nella tabella comitato_sottosedi
            foreach ($sottosedi as $nomeSub) {
                ComitatoSottosede::firstOrCreate(
                    ['comitato_locale_id' => $locale->id, 'nome' => $nomeSub]
                );
            }

            $isNew ? $creati++ : $aggiornati++;
        }

        $this->command->info("{$regionale->nome}: {$creati} creati, {$aggiornati} aggiornati.");
    }

    /**
     * Normalizza il nome: rimuove spazi indesiderati dopo apostrofi
     * (es. "d' Argento" → "d'Argento", "dell' Aquila" → "dell'Aquila").
     */
    private function normalizzaNome(string $nome): string
    {
        return preg_replace("/('\s+)/u", "'", $nome);
    }

    /**
     * Genera un codice univoco nel formato REG-SIGLA.
     * Rimuove prefissi comuni ("Comitato di ", "Comitato ", ecc.) e
     * produce una sigla leggibile da massimo 12 caratteri.
     */
    private function generaCodice(string $nome, string $regioneCodice, array $used): string
    {
        // Rimuove prefissi comuni e suffissi
        $clean = preg_replace('/^Comitato\s+(di\s+|dell[ao]\s+|d\'\s*)?/i', '', $nome);
        $clean = preg_replace('/\s*-\s*ODV.*$/i', '', $clean);
        $clean = preg_replace('/\s+ODV.*$/i', '', $clean);

        // Converte in slug ASCII, rimuove trattini, tronca
        $slug   = strtoupper(Str::ascii($clean));
        $slug   = preg_replace('/[^A-Z0-9]/', '', $slug);
        $slug   = substr($slug, 0, 12);
        $codice = $regioneCodice . '-' . $slug;

        // Gestisce collisioni aggiungendo un numero progressivo
        if (in_array($codice, $used)) {
            $i = 2;
            $base = substr($slug, 0, 10);
            while (in_array($regioneCodice . '-' . $base . $i, $used)) {
                $i++;
            }
            $codice = $regioneCodice . '-' . $base . $i;
        }

        return $codice;
    }
}
