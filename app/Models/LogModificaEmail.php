<?php

namespace App\Models;

use App\Models\Socio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogModificaEmail extends Model
{
    public $timestamps = false;

    protected $table = 'log_modifiche_email';

    protected $fillable = [
        'partecipante_id',
        'email_vecchia',
        'email_nuova',
        'fonte',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }
}
