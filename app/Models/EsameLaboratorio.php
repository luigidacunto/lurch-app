<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Il numero di laboratori dell'esame e' il numero di righe di questa tabella. */
class EsameLaboratorio extends Model
{
    protected $table = 'esame_laboratori';

    protected $fillable = [
        'esame_id',
        'numero',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function gruppi(): HasMany
    {
        return $this->hasMany(EsameGruppo::class, 'laboratorio_id');
    }

    public function schedeSemaforo(): HasMany
    {
        return $this->hasMany(TcriSchedaSemaforo::class, 'laboratorio_id');
    }
}
