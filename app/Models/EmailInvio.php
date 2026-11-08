<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailInvio extends Model
{
    public $timestamps = false;

    protected $table = 'email_invii';

    protected $fillable = [
        'iscrizioneable_type',
        'iscrizioneable_id',
        'tipo',
        'inviata_at',
        'aperta_at',
        'resend_message_id',
        'errore',
    ];

    protected function casts(): array
    {
        return [
            'inviata_at'  => 'datetime',
            'aperta_at'   => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    /**
     * Relazione polimorfica: può appartenere a Iscrizione o IscrizioneStaff.
     */
    public function iscrizioneable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isInviata(): bool
    {
        return $this->inviata_at !== null && $this->errore === null;
    }

    public function hasErrore(): bool
    {
        return $this->errore !== null;
    }
}
