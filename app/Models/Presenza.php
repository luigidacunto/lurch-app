<?php

namespace App\Models;

use App\Models\Socio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presenza extends Model
{
    protected $table = 'presenze_evento';

    protected $fillable = [
        'evento_id',
        'partecipante_id',
        'iscrizione_id',
        'tipo',
        'data',
        'fonte',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }

    public function iscrizione(): BelongsTo
    {
        return $this->belongsTo(Iscrizione::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
