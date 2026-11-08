<?php

namespace App\Models;

use App\Casts\ArrayOrCsvString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduatoriaQualificaPreferenziale extends Model
{
    public $timestamps = false;

    protected $table = 'graduatoria_qualifiche_preferenziali';

    protected $fillable = [
        'graduatoria_id',
        'codici_corso',
        'punteggio',
    ];

    protected function casts(): array
    {
        return [
            'codici_corso' => ArrayOrCsvString::class,
            'punteggio'    => 'decimal:2',
        ];
    }

    public function graduatoria(): BelongsTo
    {
        return $this->belongsTo(Graduatoria::class);
    }
}
