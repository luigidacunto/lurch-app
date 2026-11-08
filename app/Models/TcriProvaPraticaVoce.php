<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TcriProvaPraticaVoce extends Model
{
    protected $table = 'tcri_prove_pratiche_voci';

    protected $fillable = [
        'prova_pratica_id',
        'voce_codice',
        'valore',
    ];

    protected function casts(): array
    {
        return [
            'valore' => 'decimal:2',
        ];
    }

    public function provaPratica(): BelongsTo
    {
        return $this->belongsTo(TcriProvaPratica::class, 'prova_pratica_id');
    }
}
