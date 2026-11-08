<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduatoriaPunteggioManuale extends Model
{
    protected $table = 'graduatoria_punteggi_manuali';

    protected $fillable = [
        'graduatoria_candidato_id',
        'valore',
        'motivo',
        'creato_da_id',
    ];

    protected function casts(): array
    {
        return [
            'valore' => 'decimal:2',
        ];
    }

    public function candidato(): BelongsTo
    {
        return $this->belongsTo(GraduatoriaCandidato::class, 'graduatoria_candidato_id');
    }

    public function creatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da_id');
    }
}
