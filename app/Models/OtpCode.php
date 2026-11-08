<?php

namespace App\Models;

use App\Models\Socio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpCode extends Model
{
    public $timestamps = false;

    protected $table = 'otp_codes';

    protected $fillable = [
        'partecipante_id',
        'email',
        'codice',
        'expires_at',
        'used_at',
        'tentativi_falliti',
        'bloccato_fino_a',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'       => 'datetime',
            'used_at'          => 'datetime',
            'bloccato_fino_a'  => 'datetime',
            'created_at'       => 'datetime',
            'tentativi_falliti' => 'integer',
        ];
    }

    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }

    public function isScaduto(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsato(): bool
    {
        return $this->used_at !== null;
    }

    public function isBloccato(): bool
    {
        return $this->bloccato_fino_a !== null && $this->bloccato_fino_a->isFuture();
    }
}
