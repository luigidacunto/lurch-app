<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domanda del test scritto (comune a più tipi di esame): testo, 4 opzioni, indice (base 0) della corretta.
 * Il punteggio non sta qui: è la configurazione dell'esame (uguale per tutte le domande).
 * Annullata (`annullata_il` non NULL, App\Esami\AnnullamentoDomandaTest): resta con le risposte ma esce dal calcolo.
 * ⚠️ `corretta` non va mai al client del discente: App\Esami\TestScritto::domandePerDiscente() non la carica nemmeno.
 */
class TestDomanda extends Model
{
    protected $table = 'test_domande';

    protected $hidden = ['corretta'];

    protected $fillable = [
        'esame_id',
        'ordine',
        'testo',
        'opzioni',
        'corretta',
        'annullata_il',
        'annullata_da',
        'motivo_annullamento',
    ];

    protected function casts(): array
    {
        return [
            'opzioni' => 'array',
            'corretta' => 'integer',
            'annullata_il' => 'datetime',
        ];
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function eAnnullata(): bool
    {
        return $this->annullata_il !== null;
    }

    public function annullataDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'annullata_da');
    }

    public function risposte(): HasMany
    {
        return $this->hasMany(TestRisposta::class, 'domanda_id');
    }
}
