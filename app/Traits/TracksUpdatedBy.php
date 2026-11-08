<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registra su `updated_by` l'utente autenticato che crea/modifica il record.
 *
 * Solo scritture con una sessione admin attiva (guard `web`): da console, import, job
 * e dal portale PWA (che autentica via `app_socio_id` in sessione, non col guard) il
 * campo resta NULL — corretto, quei modelli non si modificano da quei contesti.
 *
 * Campo interno diagnostico: non va mostrato nei pannelli.
 */
trait TracksUpdatedBy
{
    public static function bootTracksUpdatedBy(): void
    {
        $assegna = function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        };

        static::creating($assegna);
        static::updating($assegna);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
