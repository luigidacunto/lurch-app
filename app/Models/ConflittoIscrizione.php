<?php

namespace App\Models;

use App\Models\Socio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflittoIscrizione extends Model
{
    protected $table = 'conflitti_iscrizioni';

    protected $fillable = [
        'partecipante_id',
        'iscrizione_a_id',
        'iscrizione_b_id',
        'corso_a_id',
        'corso_b_id',
    ];

    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function iscrizioneA(): BelongsTo
    {
        return $this->belongsTo(Iscrizione::class, 'iscrizione_a_id');
    }

    public function iscrizioneB(): BelongsTo
    {
        return $this->belongsTo(Iscrizione::class, 'iscrizione_b_id');
    }

    public function corsoA(): BelongsTo
    {
        return $this->belongsTo(Corso::class, 'corso_a_id');
    }

    public function corsoB(): BelongsTo
    {
        return $this->belongsTo(Corso::class, 'corso_b_id');
    }
}
