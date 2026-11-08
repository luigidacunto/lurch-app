<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessaggioBadge extends Model
{
    protected $table = 'messaggi_badge';

    protected $fillable = ['socio_evento_id', 'corpo'];

    protected function casts(): array
    {
        return [
            'letto_at' => 'datetime',
        ];
    }

    public function socioEvento(): BelongsTo
    {
        return $this->belongsTo(SocioEvento::class, 'socio_evento_id');
    }

    public function isLetto(): bool
    {
        return $this->letto_at !== null;
    }

    public function segnaLetto(): void
    {
        if (! $this->letto_at) {
            $this->letto_at = now();
            $this->save();
        }
    }
}
