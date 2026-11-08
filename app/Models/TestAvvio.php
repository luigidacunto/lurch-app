<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avvio del test scritto per UN discente. `avviato_il` NULL = disattivato, altrimenti ultima attivazione (non mostrata).
 * Reversibile finché il discente non ha inviato. Il `token` cambia a ogni attivazione e invalida i moduli già aperti.
 */
class TestAvvio extends Model
{
    public const ONLINE = 'online';
    public const CARTACEO = 'cartaceo';

    protected $table = 'test_avvii';

    protected $fillable = [
        'esame_id',
        'discente_socio_id',
        'modalita',
        'avviato_il',
        'token',
        'motivo_cartaceo',
        'avviato_da',
    ];

    protected function casts(): array
    {
        return [
            'avviato_il' => 'datetime',
        ];
    }

    public function eAttivo(): bool
    {
        return $this->avviato_il !== null;
    }

    public function scopeAttivi(Builder $query): Builder
    {
        return $query->whereNotNull('avviato_il');
    }

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'discente_socio_id');
    }

    public function avviatoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'avviato_da');
    }
}
