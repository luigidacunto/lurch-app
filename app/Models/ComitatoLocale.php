<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComitatoLocale extends Model
{
    use SoftDeletes;

    protected $table = 'comitati_locali';

    protected $fillable = ['comitato_regionale_id', 'nome', 'rappresentante_legale', 'indirizzo', 'email', 'codice', 'attivo'];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    public function scopeAttivo($query)
    {
        return $query->where('attivo', true);
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function partecipanti(): HasMany
    {
        return $this->hasMany(Socio::class, 'comitato_locale_id');
    }

    public function sottosedi(): HasMany
    {
        return $this->hasMany(ComitatoSottosede::class, 'comitato_locale_id')->orderBy('nome');
    }
}
