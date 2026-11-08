<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voto di una voce, per chiave del modello. NULL = voce non valutata (non entra nella media). */
class TcriSchedaItinereVoce extends Model
{
    protected $table = 'tcri_schede_itinere_voci';

    protected $fillable = [
        'scheda_id',
        'voce_codice',
        'voto',
    ];

    protected function casts(): array
    {
        return [
            'voto' => 'integer',
        ];
    }

    public function scheda(): BelongsTo
    {
        return $this->belongsTo(TcriSchedaItinere::class, 'scheda_id');
    }
}
