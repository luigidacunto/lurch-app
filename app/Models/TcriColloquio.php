<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Colloquio. Due famiglie sempre separate:
 *  - TIPO_PERSONALE = appunti di un membro compilatore (direttore o docente di commissione; il presidente non compila):
 *    voto INTERO 1–5 obbligatorio + impressione facoltativa. Non entra nel calcolo.
 *  - TIPO_FINALE = scheda del direttore (membro NULL), voto con fino a 1 decimale + motivazione se diverso dalla
 *    proposta (media dei voti personali troncata a 1 decimale). L'unica che entra nel calcolo.
 * ⚠️ L'unicità è garantita da indici univoci PARZIALI (migrazione): con membro NULL un UNIQUE normale non protegge.
 */
class TcriColloquio extends Model
{
    public const TIPO_PERSONALE = 'personale';
    public const TIPO_FINALE    = 'finale';

    protected $table = 'tcri_colloqui';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'tipo',
        'membro_socio_id',
        'punteggio',
        'impressione',
        'motivazione',
        'inserito_da',
    ];

    protected function casts(): array
    {
        return [
            'punteggio' => 'decimal:1',
        ];
    }

    public function membro(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'membro_socio_id');
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function inseritoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'inserito_da');
    }
}
