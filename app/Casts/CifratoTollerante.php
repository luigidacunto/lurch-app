<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Come il cast «encrypted» di Laravel (stesso formato: encryptString), ma un valore cifrato con
 * un'altra APP_KEY (database condiviso, chiave cambiata) si legge come null invece di lanciare
 * «The MAC is invalid». Così la pagina si apre e il valore si può sovrascrivere: il controllo
 * delle modifiche di Eloquent confronta i valori grezzi, senza decifrare.
 */
class CifratoTollerante implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString((string) $value);
    }
}
