<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraEvento extends Model
{
    protected $table = 'camere_evento';

    protected $fillable = ['evento_id', 'data', 'nome', 'posti'];

    protected $casts = ['data' => 'date:Y-m-d'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }
}
