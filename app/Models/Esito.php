<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot alla chiusura dell'esame: mai ricalcolato. */
class Esito extends Model
{
    protected $table = 'esiti';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'totali',
        'punteggio_finale',
        'idoneo',
        'tipo_codice',
        'modello_versione',
        'chiuso_il',
        'stato',
        'lap',
        'motivo_non_ammesso',
    ];

    public const STATO_CALCOLATO = 'calcolato';
    public const STATO_NON_AMMESSO = 'non_ammesso';

    /** Uno snapshot non si modifica mai: si cancella (riapertura) e si riscrive (nuova chiusura). */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Un esito chiuso non si modifica: si può solo riscrivere con una nuova chiusura.');
        });
    }

    protected function casts(): array
    {
        return [
            'totali' => 'array',
            'punteggio_finale' => 'decimal:1',
            'idoneo' => 'boolean',
            'lap' => 'decimal:2',
            'chiuso_il' => 'datetime',
        ];
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }
}
