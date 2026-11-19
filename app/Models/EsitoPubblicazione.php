<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Esito finale dell'esame pubblicato a UN discente: nella app vede totale, idoneità e contributo delle prove (valori
 * attuali di RisultatiEsame). `pubblicato_il` NULL = pubblicazione ritirata. Pubblica e ritira chi gestisce l'esame.
 */
class EsitoPubblicazione extends Model
{
    protected $table = 'esito_pubblicazioni';

    protected $fillable = ['esame_id', 'discente_socio_id', 'pubblicato_il', 'pubblicato_da'];

    protected function casts(): array
    {
        return ['pubblicato_il' => 'datetime'];
    }

    public function ePubblicata(): bool
    {
        return $this->pubblicato_il !== null;
    }
}
