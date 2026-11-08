<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccessoEvento extends Model
{
    protected $table = 'accessi_evento';

    protected $fillable = [
        'iscrizioneable_type',
        'iscrizioneable_id',
        'tipo',
        'timestamp_accesso',
        'operatore_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'timestamp_accesso' => 'datetime',
        ];
    }

    public function iscrizioneable(): MorphTo
    {
        return $this->morphTo();
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operatore_id');
    }
}
