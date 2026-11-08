<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComitatoSottosede extends Model
{
    protected $table = 'comitato_sottosedi';

    protected $fillable = ['comitato_locale_id', 'nome'];

    public function comitatoLocale(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_id');
    }
}
