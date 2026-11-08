<?php

namespace Database\Seeders;

use App\Models\OpzioneAlimentare;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OpzioneAlimentareSeeder extends Seeder
{
    /**
     * Il catalogo è testo libero copiato nel JSON del socio (nessuna FK), quindi
     * il seeder riparte da zero ad ogni run: elimina tutto e reinserisce dal JSON.
     * Così la lista resta identica al file, senza doppioni quando si riorganizzano
     * le categorie a mano.
     */
    public function run(): void
    {
        $path = database_path('data/opzioni_alimentari.json');

        if (! File::exists($path)) {
            $this->command->warn("File non trovato: {$path}");
            return;
        }

        $data = json_decode(File::get($path), true);

        $categorie = [
            'allergeni'    => 'allergene',
            'intolleranze' => 'intolleranza',
            'diete'        => 'dieta',
            'esclusioni'   => 'esclusione',
        ];

        OpzioneAlimentare::truncate();

        foreach ($categorie as $chiave => $tipo) {
            foreach (($data[$chiave] ?? []) as $i => $row) {
                OpzioneAlimentare::create([
                    'tipo'      => $tipo,
                    'nome'      => $row['nome'],
                    'etichetta' => $row['etichetta'],
                    'ordine'    => $i,
                    'attivo'    => true,
                ]);
            }
        }
    }
}
