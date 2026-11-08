<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una sola scheda per (esame, discente, docente): le modifiche sovrascrivono, nessuno storico. */
class TcriSchedaItinere extends Model
{
    protected $table = 'tcri_schede_itinere';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'docente_socio_id',
        'annotazione',
    ];

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'docente_socio_id');
    }

    public function voci(): HasMany
    {
        return $this->hasMany(TcriSchedaItinereVoce::class, 'scheda_id');
    }

    /**
     * Medie d'area e totale della scheda (appunto personale, mai salvato) dal motore di calcolo.
     * Un'area senza voti NON prende il valore forzato (quello è della sintesi d'area): media null e fuori dal totale.
     *
     * @return array{aree: list<array{codice: string, nome: string, media: ?\App\Esami\Calcolo\Frazione, voti_contati: int, n_voci: int}>, totale: ?\App\Esami\Calcolo\Frazione, aree_valutate: int, n_aree: int, voti: int}
     */
    public function riepilogo(array $snapshot, string|int|float $lap): array
    {
        $calcolo = \App\Esami\Calcolo\CalcoloEsameFactory::tcri();
        $perVoce = $this->voci->mapWithKeys(fn ($v) => [$v->voce_codice => $v->voto])->all();

        $aree = [];
        $totale = null;
        $valutate = 0;
        $voti = 0;
        foreach ($snapshot['itinere']['aree'] as $area) {
            $codici = array_column($area['voci'], 'codice');
            $votiArea = array_intersect_key($perVoce, array_flip($codici));
            $r = $calcolo->mediaArea($snapshot, $area['codice'], $votiArea, $lap);
            $media = $r['forzato'] ? null : $r['media'];
            if ($media !== null) {
                $totale = $totale === null ? $media : $totale->somma($media);
                $valutate++;
            }
            $voti += $r['voti_contati'];
            $aree[] = ['codice' => $area['codice'], 'nome' => $area['nome'], 'media' => $media, 'voti_contati' => $r['voti_contati'], 'n_voci' => count($codici)];
        }

        return ['aree' => $aree, 'totale' => $totale, 'aree_valutate' => $valutate, 'n_aree' => count($aree), 'voti' => $voti];
    }
}
