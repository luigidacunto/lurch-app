<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RispostaCheckin extends Model
{
    protected $table = 'risposte_checkin';

    protected $fillable = [
        'iscrizioneable_type',
        'iscrizioneable_id',
        'risposte',
        'checkin_at',
        'operatore_id',
    ];

    protected function casts(): array
    {
        return [
            'risposte'   => 'array',
            'checkin_at' => 'datetime',
        ];
    }

    /**
     * Relazione polimorfica: può appartenere a Iscrizione o IscrizioneStaff.
     */
    public function iscrizioneable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Operatore che ha eseguito il checkin */
    public function operatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operatore_id');
    }
}
