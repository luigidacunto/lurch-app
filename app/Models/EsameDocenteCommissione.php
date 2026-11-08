<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Docente del corso scelto per la commissione (0-3). Direttore e presidente/delegato non stanno qui: vivono sul corso. */
class EsameDocenteCommissione extends Model
{
    protected $table = 'esame_docenti_commissione';

    protected $fillable = [
        'esame_id',
        'socio_id',
    ];

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }
}
