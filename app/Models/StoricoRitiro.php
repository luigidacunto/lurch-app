<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Log immutabile di eventi ritiro/ripristino, riferibile a sorgenti diverse
 * (GraduatoriaCandidato oggi; in futuro Iscrizione, CandidaturaStaff, ecc.)
 * tramite relazione polimorfica `ritirabile` — stesso pattern già usato nel
 * progetto da AccessoEvento/EmailInvio/RispostaCheckin verso `iscrizioneable`.
 */
class StoricoRitiro extends Model
{
    public $timestamps = false;

    protected $table = 'storico_ritiri';

    protected $fillable = [
        'ritirabile_type',
        'ritirabile_id',
        'tipo',
        'motivo',
        'eseguito_da_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function ritirabile(): MorphTo
    {
        return $this->morphTo();
    }

    public function eseguitoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eseguito_da_id');
    }
}
