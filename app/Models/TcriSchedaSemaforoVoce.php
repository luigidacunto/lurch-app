<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Valore di una voce, per chiave del modello (voce_codice). NULL = non ancora compilata (bozza). */
class TcriSchedaSemaforoVoce extends Model
{
    protected $table = 'tcri_schede_semaforo_voci';

    protected $fillable = [
        'scheda_id',
        'voce_codice',
        'valore',
    ];

    protected function casts(): array
    {
        return [
            'valore' => 'decimal:2',
        ];
    }

    public function scheda(): BelongsTo
    {
        return $this->belongsTo(TcriSchedaSemaforo::class, 'scheda_id');
    }
}
