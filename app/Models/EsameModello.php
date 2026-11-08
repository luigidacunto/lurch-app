<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modello d'esame versionato (tipo_codice + versione): struttura = prove, aree, voci, scale, pesi come dato. */
class EsameModello extends Model
{
    protected $table = 'esame_modelli';

    protected $fillable = [
        'tipo_codice',
        'versione',
        'struttura',
    ];

    protected function casts(): array
    {
        return [
            'struttura' => 'array',
            'versione' => 'integer',
        ];
    }

    public function esami(): HasMany
    {
        return $this->hasMany(Esame::class);
    }
}
