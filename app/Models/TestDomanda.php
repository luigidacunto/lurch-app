<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domanda del test scritto (comune a più tipi di esame): testo, 4 opzioni, indice (base 0) della corretta.
 * Il punteggio non sta qui: è la configurazione dell'esame (uguale per tutte le domande).
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
    ];

    protected function casts(): array
    {
        return [
            'opzioni' => 'array',
            'corretta' => 'integer',
        ];
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function risposte(): HasMany
    {
        return $this->hasMany(TestRisposta::class, 'domanda_id');
    }
}
