<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Questionario extends Model
{
    protected $fillable = [
        'evento_id',
        'titolo',
        'domande',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'domande' => 'array',
            'attivo'  => 'boolean',
        ];
    }

    public function scopeAttivo($query)
    {
        return $query->where('attivo', true);
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function risposte(): HasMany
    {
        return $this->hasMany(RispostaQuestionario::class, 'questionario_id');
    }
}
