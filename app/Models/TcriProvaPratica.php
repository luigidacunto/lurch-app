<?php

namespace App\Models;

use App\Models\Concerns\HaTotaleSemaforo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scheda della prova pratica (stessa scheda a semaforo dei laboratori). Due famiglie sempre separate:
 *  - TIPO_PERSONALE = appunti di un membro compilatore (direttore o docente di commissione; il presidente o
 *    delegato non compila). Bozza a ogni tocco, `inviata_il` valorizzato con tutte le voci. Non entra nel calcolo.
 *  - TIPO_FINALE = scheda unica inserita dal direttore (membro_socio_id NULL), precompilata dalla proposta: l'unica
 *    che conta nel calcolo.
 *
 * ⚠️ L'unicità è garantita da indici univoci PARZIALI (migrazione) perché con membro NULL un
 * UNIQUE normale non protegge su PostgreSQL.
 */
class TcriProvaPratica extends Model
{
    use HaTotaleSemaforo;

    public const TIPO_PERSONALE = 'personale';
    public const TIPO_FINALE   = 'finale';

    protected $table = 'tcri_prove_pratiche';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'tipo',
        'membro_socio_id',
        'inserito_da',
        'inviata_il',
    ];

    protected function casts(): array
    {
        return [
            'inviata_il' => 'datetime',
        ];
    }

    public function eInviata(): bool
    {
        return $this->inviata_il !== null;
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class);
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function membro(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'membro_socio_id');
    }

    public function inseritoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'inserito_da');
    }

    public function voci(): HasMany
    {
        return $this->hasMany(TcriProvaPraticaVoce::class, 'prova_pratica_id');
    }
}
