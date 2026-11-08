<?php

namespace App\Models;

use App\Models\Concerns\HaTotaleSemaforo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Scheda a semaforo di un laboratorio (un docente, un discente). Nessun totale salvato: lo calcola il motore. */
class TcriSchedaSemaforo extends Model
{
    use HaTotaleSemaforo;

    protected $table = 'tcri_schede_semaforo';

    protected $fillable = [
        'laboratorio_id',
        'discente_socio_id',
        'docente_socio_id',
        'inviata_il',
        'annotazione',
    ];

    protected function casts(): array
    {
        return [
            'inviata_il' => 'datetime',
        ];
    }

    public function eInviata(): bool
    {
        return $this->inviata_il !== null;
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(EsameLaboratorio::class, 'laboratorio_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'docente_socio_id');
    }

    public function voci(): HasMany
    {
        return $this->hasMany(TcriSchedaSemaforoVoce::class, 'scheda_id');
    }
}
