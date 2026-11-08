<?php

namespace Database\Seeders;

use App\Models\EsameModello;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Carica i modelli d'esame da database/data/esame_modelli/*.json (uno per tipo e versione).
 *
 * - (tipo_codice, versione) assente → lo crea;
 * - presente e identico al JSON → non fa nulla;
 * - presente ma con contenuto diverso → lo salta con un avviso: un modello già caricato non si
 *   sovrascrive mai (una revisione è un NUOVO file/versione), e il deploy (che lancia questo
 *   seeder a ogni giro) non deve fermarsi per questo.
 */
class EsameModelliSeeder extends Seeder
{
    public function run(): void
    {
        foreach (File::glob(database_path('data/esame_modelli/*.json')) as $path) {
            $modello = json_decode(File::get($path), true);

            if (! is_array($modello) || ! isset($modello['tipo_codice'], $modello['versione'])) {
                throw new RuntimeException("Formato JSON non valido: {$path}");
            }

            $esistente = EsameModello::where('tipo_codice', $modello['tipo_codice'])
                ->where('versione', $modello['versione'])
                ->first();

            if (! $esistente) {
                EsameModello::create([
                    'tipo_codice' => $modello['tipo_codice'],
                    'versione'    => $modello['versione'],
                    'struttura'   => $modello,
                ]);
                continue;
            }

            if ($this->normalizza($esistente->struttura) !== $this->normalizza($modello)) {
                $this->command?->warn(
                    "Il modello {$modello['tipo_codice']} v{$modello['versione']} esiste già con contenuto diverso da {$path}: "
                    . 'non viene sovrascritto. Per una revisione crea una nuova versione.'
                );
            }
        }
    }

    /** jsonb non conserva l'ordine delle chiavi: si confronta con le chiavi ordinate. */
    private function normalizza(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        $v = array_map(fn ($x) => $this->normalizza($x), $v);
        if (! array_is_list($v)) {
            ksort($v);
        }

        return $v;
    }
}
