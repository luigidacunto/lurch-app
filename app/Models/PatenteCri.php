<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatenteCri extends Model
{
    protected $table = 'patenti_cri';

    protected $fillable = ['tipologia', 'descrizione', 'ordine', 'attivo'];

    protected function casts(): array
    {
        return ['attivo' => 'boolean'];
    }

    public function scopeAttive($query)
    {
        return $query->where('attivo', true)->orderBy('ordine');
    }
}
