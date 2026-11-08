<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RispostaQuestionario extends Model
{
    public $timestamps = false;

    protected $table = 'risposte_questionario';

    protected $fillable = [
        'questionario_id',
        'token_risposta',
        'risposte',
    ];

    protected function casts(): array
    {
        return [
            'risposte'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    // Nessuna FK al partecipante — le risposte sono completamente anonime

    public function questionario(): BelongsTo
    {
        return $this->belongsTo(Questionario::class, 'questionario_id');
    }
}
