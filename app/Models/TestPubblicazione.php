<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Risultati del test scritto pubblicati a UN discente: nella app vede punteggio e correzioni. `pubblicato_il` NULL =
 * pubblicazione ritirata (torna alla sola lettura delle proprie risposte). Solo il direttore pubblica e ritira.
 */
class TestPubblicazione extends Model
{
    protected $table = 'test_pubblicazioni';

    protected $fillable = ['esame_id', 'discente_socio_id', 'pubblicato_il', 'pubblicato_da'];

    protected function casts(): array
    {
        return ['pubblicato_il' => 'datetime'];
    }

    public function ePubblicata(): bool
    {
        return $this->pubblicato_il !== null;
    }

    public function pubblicatoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'pubblicato_da');
    }
}
