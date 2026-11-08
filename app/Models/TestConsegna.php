<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compilazione del test di UN discente (una sola per esame+discente, vincolo DB). La riga nasce al primo tocco:
 * `consegnato_il` NULL = in compilazione, valorizzato = Inviato (da qui in poi non si tocca più).
 * Distingue risposta vuota (test_risposte.opzione NULL) da test non inviato.
 */
class TestConsegna extends Model
{
    public const ORIGINE_ONLINE = 'online';
    public const ORIGINE_CARTACEO = 'cartaceo';

    protected $table = 'test_consegne';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'consegnato_il',
        'origine',
        'inserito_da',
    ];

    protected function casts(): array
    {
        return [
            'consegnato_il' => 'datetime',
        ];
    }

    public function eInviata(): bool
    {
        return $this->consegnato_il !== null;
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function inseritoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'inserito_da');
    }

    public function risposte(): HasMany
    {
        return $this->hasMany(TestRisposta::class, 'consegna_id');
    }
}
