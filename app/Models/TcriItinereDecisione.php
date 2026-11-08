<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Punteggio finale d'area deciso dalla commissione; separato dalle schede dei docenti, che non sovrascrive mai. */
class TcriItinereDecisione extends Model
{
    protected $table = 'tcri_itinere_decisioni';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'area_codice',
        'punteggio',
        'motivazione',
        'deciso_da',
    ];

    protected function casts(): array
    {
        return [
            'punteggio' => 'decimal:1',
        ];
    }

    // Nessun `created_by`/`updated_by`: l'autore è `deciso_da` (socio del direttore), la data è `updated_at`.

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function decisoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'deciso_da');
    }
}
