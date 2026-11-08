<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Alcuni record hanno storicamente salvato liste di codici corso come stringa
 * CSV unica (es. "AGGISIC,EAGGISIC,IESIC") invece che come vero array JSON.
 * Normalizza sempre in lettura a un array pulito, e scrive sempre un vero
 * array JSON — così i dati legacy si auto-correggono al primo salvataggio.
 */
class ArrayOrCsvString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        $lista   = is_array($decoded) ? $decoded : explode(',', (string) $decoded);

        return $this->pulisci($lista);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $lista = is_array($value) ? $value : explode(',', (string) ($value ?? ''));

        return [$key => json_encode($this->pulisci($lista))];
    }

    private function pulisci(array $lista): array
    {
        return array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), $lista),
            fn ($v) => $v !== ''
        ));
    }
}
