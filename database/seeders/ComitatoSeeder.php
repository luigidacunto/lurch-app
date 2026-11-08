<?php

namespace Database\Seeders;

use App\Models\ComitatoRegionale;
use Illuminate\Database\Seeder;

class ComitatoSeeder extends Seeder
{
    /**
     * Comitati Regionali CRI ufficiali (fonte: gaia.cri.it/informazioni/sedi/).
     * Include Sede Regionale e Sede Provinciale, escluso il comitato di PROVA.
     * I Comitati Locali vengono creati dagli admin regionali tramite il pannello.
     */
    public function run(): void
    {
        $regionali = [
            ['nome' => 'Comitato Regionale Abruzzo',                          'codice' => 'ABR'],
            ['nome' => 'Comitato Provinciale dell\'Alto Adige - ODV',         'codice' => 'BZO'],
            ['nome' => 'Comitato Regionale Basilicata',                       'codice' => 'BAS'],
            ['nome' => 'Comitato Regionale Calabria',                         'codice' => 'CAL'],
            ['nome' => 'Comitato Regionale Campania',                         'codice' => 'CAM'],
            ['nome' => 'Comitato Regionale Emilia Romagna',                   'codice' => 'EMR'],
            ['nome' => 'Comitato Regionale Friuli - Venezia Giulia',          'codice' => 'FVG'],
            ['nome' => 'Comitato Regionale Lazio',                            'codice' => 'LAZ'],
            ['nome' => 'Comitato Regionale Liguria',                          'codice' => 'LIG'],
            ['nome' => 'Comitato Regionale Lombardia',                        'codice' => 'LOM'],
            ['nome' => 'Comitato Regionale Marche',                           'codice' => 'MAR'],
            ['nome' => 'Comitato Regionale Molise',                           'codice' => 'MOL'],
            ['nome' => 'Comitato Regionale Piemonte',                         'codice' => 'PIE'],
            ['nome' => 'Comitato Regionale Puglia',                           'codice' => 'PUG'],
            ['nome' => 'Comitato Regionale Sardegna',                         'codice' => 'SAR'],
            ['nome' => 'Comitato Regionale Sicilia',                          'codice' => 'SIC'],
            ['nome' => 'Comitato Regionale Toscana',                          'codice' => 'TOS'],
            ['nome' => 'Comitato Provinciale del Trentino - ODV',             'codice' => 'TRE'],
            ['nome' => 'Comitato Regionale Umbria',                           'codice' => 'UMB'],
            ['nome' => 'Comitato Regionale Della Valle D\'Aosta',             'codice' => 'VDA'],
            ['nome' => 'Comitato Regionale Veneto',                           'codice' => 'VEN'],
        ];

        foreach ($regionali as $dati) {
            $record = ComitatoRegionale::withTrashed()->firstOrNew(['codice' => $dati['codice']]);
            $record->nome   = $dati['nome'];
            $record->attivo = $record->attivo ?? true;
            if ($record->trashed()) $record->restore();
            $record->save();
        }

        $codiciValidi = array_column($regionali, 'codice');
        $rimossi = ComitatoRegionale::withTrashed()
            ->where(function ($q) use ($codiciValidi) {
                $q->whereNotIn('codice', $codiciValidi)->orWhereNull('codice');
            })
            ->forceDelete();

        $this->command->info('Creati/aggiornati ' . count($regionali) . ' comitati regionali CRI.');
        if ($rimossi) {
            $this->command->info("Rimossi {$rimossi} comitati regionali non più validi.");
        }
    }
}
