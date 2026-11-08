<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoCorsoValiditaRegionale extends Model
{
    protected $table = 'catalogo_corsi_validita_regionali';

    protected $fillable = [
        'catalogo_corso_id',
        'comitato_regionale_id',
        'scadenza_tipo',
        'scadenza_durata',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'scadenza_durata' => 'integer',
        ];
    }

    public function catalogoCorso(): BelongsTo
    {
        return $this->belongsTo(CatalogoCorso::class, 'catalogo_corso_id');
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function getScadenzaLegibileAttribute(): string
    {
        return match ($this->scadenza_tipo) {
            'nessuna'     => 'Nessuna scadenza',
            'mesi_esatti' => "Ogni {$this->scadenza_durata} mesi (data esatta)",
            'anno_solare' => "Ogni {$this->scadenza_durata} " . ($this->scadenza_durata === 1 ? 'anno' : 'anni') . ' (anno solare)',
            default       => '—',
        };
    }
}
