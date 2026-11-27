<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Risposta anonima a un questionario di gradimento. ⚠️ Nessun riferimento all'invito, al discente o all'utente,
 * id UUID casuale (v4, non ordinabile nel tempo) e nessuna data: nulla deve permettere di risalire a chi ha risposto.
 * `risposte`: {chiave: valore} per le domande normali, {chiave: {indice valutato: valore}} per quelle di S9.
 */
class QuestionarioRisposta extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'questionario_risposte';

    protected $fillable = ['questionario_id', 'risposte'];

    protected function casts(): array
    {
        return ['risposte' => 'array'];
    }

    /** UUID v4 casuale: quello di default di Laravel (v7) è ordinato nel tempo e rivelerebbe l'ordine di compilazione. */
    public function newUniqueId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
