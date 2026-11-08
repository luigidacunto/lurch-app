<?php

namespace App\Models;

use App\Models\Socio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComitatoRegionale extends Model
{
    use SoftDeletes;

    protected $table = 'comitati_regionali';

    protected $fillable = ['nome', 'rappresentante_legale', 'indirizzo', 'email', 'codice', 'attivo'];

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

    public function comitatiLocali(): HasMany
    {
        return $this->hasMany(ComitatoLocale::class, 'comitato_regionale_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'comitato_regionale_id');
    }

    public function catalogoCorsi(): HasMany
    {
        return $this->hasMany(CatalogoCorso::class, 'comitato_regionale_id');
    }

    public function eventi(): HasMany
    {
        return $this->hasMany(Evento::class, 'comitato_regionale_id');
    }

    public function partecipanti(): HasMany
    {
        return $this->hasMany(Socio::class, 'comitato_regionale_id');
    }
}
