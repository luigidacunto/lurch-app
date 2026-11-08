<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class OspiteEvento extends Model
{
    protected $table = 'ospiti_evento';

    protected $fillable = [
        'evento_id',
        'nome',
        'cognome',
        'cf',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function accessi(): MorphMany
    {
        return $this->morphMany(AccessoEvento::class, 'iscrizioneable');
    }

    public function ultimoAccesso(): MorphOne
    {
        return $this->morphOne(AccessoEvento::class, 'iscrizioneable')
            ->latestOfMany('timestamp_accesso');
    }

    public function rispostaCheckin(): MorphOne
    {
        return $this->morphOne(RispostaCheckin::class, 'iscrizioneable');
    }

    public function isPresente(): bool
    {
        return $this->ultimoAccesso?->tipo === 'entrata';
    }

    public function getNomeCompletoAttribute(): string
    {
        return "{$this->nome} {$this->cognome}";
    }
}
