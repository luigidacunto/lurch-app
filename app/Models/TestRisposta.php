<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** opzione NULL = risposta lasciata vuota (dentro una consegna). */
class TestRisposta extends Model
{
    protected $table = 'test_risposte';

    protected $fillable = [
        'consegna_id',
        'domanda_id',
        'opzione',
    ];

    protected function casts(): array
    {
        return [
            'opzione' => 'integer',
        ];
    }

    public function consegna(): BelongsTo
    {
        return $this->belongsTo(TestConsegna::class, 'consegna_id');
    }

    public function domanda(): BelongsTo
    {
        return $this->belongsTo(TestDomanda::class, 'domanda_id');
    }
}
