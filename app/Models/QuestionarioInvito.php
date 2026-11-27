<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invito di un discente al questionario. ⚠️ Niente timestamp automatici: `compilato` non deve portare l'ora
 * della compilazione (anonimato). `inviato_il` è l'ora dell'email, non della risposta.
 * `bozza`: risposte parziali, cifrate con la chiave dell'applicazione; null dopo l'invio e dopo la chiusura.
 */
class QuestionarioInvito extends Model
{
    public $timestamps = false;

    protected $table = 'questionario_inviti';

    protected $fillable = ['questionario_id', 'iscrizione_id', 'inviato_il', 'compilato', 'aperto', 'bozza'];

    protected $hidden = ['bozza'];

    protected function casts(): array
    {
        return ['inviato_il' => 'datetime', 'compilato' => 'boolean', 'aperto' => 'boolean', 'bozza' => 'encrypted:array'];
    }

    public function questionario(): BelongsTo
    {
        return $this->belongsTo(QuestionarioGradimento::class, 'questionario_id');
    }

    public function iscrizione(): BelongsTo
    {
        return $this->belongsTo(Iscrizione::class);
    }
}
