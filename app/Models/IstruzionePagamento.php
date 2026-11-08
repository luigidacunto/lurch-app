<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IstruzionePagamento extends Model
{
    protected $table = 'istruzioni_pagamento';

    protected $fillable = [
        'comitato_regionale_id',
        'testo',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    /**
     * Restituisce le istruzioni attive per un comitato regionale.
     * Prima cerca l'override regionale, poi il default globale.
     */
    public static function risolvi(?int $comitatoRegionaleId): ?self
    {
        if ($comitatoRegionaleId) {
            $regionale = self::where('comitato_regionale_id', $comitatoRegionaleId)
                ->where('attivo', true)
                ->first();
            if ($regionale) return $regionale;
        }

        return self::whereNull('comitato_regionale_id')
            ->where('attivo', true)
            ->first();
    }
}
