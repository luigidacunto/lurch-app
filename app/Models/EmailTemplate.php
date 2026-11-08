<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'tipo',
        'comitato_regionale_id',
        'soggetto',
        'corpo',
        'attivo',
        'from_name',
        'from_address',
        'reply_to',
    ];

    protected function casts(): array
    {
        return ['attivo' => 'boolean'];
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    /**
     * Restituisce il template attivo per un comitato regionale.
     * Prima cerca l'override regionale, poi il default globale.
     */
    public static function risolvi(?int $comitatoRegionaleId, string $tipo = 'iscritti'): ?self
    {
        if ($comitatoRegionaleId) {
            $regionale = self::where('tipo', $tipo)
                ->where('comitato_regionale_id', $comitatoRegionaleId)
                ->where('attivo', true)
                ->first();
            if ($regionale) return $regionale;
        }

        return self::where('tipo', $tipo)
            ->whereNull('comitato_regionale_id')
            ->where('attivo', true)
            ->first();
    }
}
