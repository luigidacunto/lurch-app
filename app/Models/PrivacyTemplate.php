<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyTemplate extends Model
{
    protected $table = 'privacy_templates';

    protected $fillable = [
        'tipo',
        'comitato_regionale_id',
        'testo',
        'versione',
        'pdf_path',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Ogni consenso registrato sui form salva la versione corrente del testo che
        // l'utente ha letto (privacy_versione_accettata) — il numero deve quindi
        // avanzare da solo ad ogni modifica sostanziale del testo, senza dipendere
        // da chi lo edita da pannello.
        static::saving(function (PrivacyTemplate $template) {
            if ($template->exists && $template->isDirty('testo')) {
                $template->versione = ($template->getOriginal('versione') ?? 1) + 1;
            }
        });
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            'informativa_dati' => 'Informativa dati (art. 13 GDPR)',
            'foto_video'       => 'Consenso foto e video',
            default            => $this->tipo,
        };
    }

    /**
     * Restituisce il template attivo per un dato tipo e comitato regionale.
     * Prima cerca l'override regionale, poi il default globale.
     */
    public static function risolvi(string $tipo, ?int $comitatoRegionaleId): ?self
    {
        if ($comitatoRegionaleId) {
            $override = self::where('tipo', $tipo)
                ->where('comitato_regionale_id', $comitatoRegionaleId)
                ->where('attivo', true)
                ->first();
            if ($override) return $override;
        }

        return self::where('tipo', $tipo)
            ->whereNull('comitato_regionale_id')
            ->where('attivo', true)
            ->first();
    }
}
