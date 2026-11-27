<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Questionario di gradimento dei discenti di un corso. `inviato_il` NULL = configurazione non ancora inviata.
 * Al primo invio si fissano versione, sezioni, valutati (direttore + docenti) e data di chiusura; gli invii
 * successivi aggiungono solo destinatari. Logica in App\Questionari.
 */
class QuestionarioGradimento extends Model
{
    protected $table = 'questionari_gradimento';

    protected $fillable = ['corso_id', 'versione', 'giorni_apertura', 'sezioni', 'valutati', 'inviato_da', 'inviato_il', 'chiude_il'];

    protected function casts(): array
    {
        return [
            'sezioni'    => 'array',
            'valutati'   => 'array',
            'inviato_il' => 'datetime',
            'chiude_il'  => 'datetime',
        ];
    }

    public function corso(): BelongsTo
    {
        return $this->belongsTo(Corso::class);
    }

    public function inviti(): HasMany
    {
        return $this->hasMany(QuestionarioInvito::class, 'questionario_id');
    }

    public function risposte(): HasMany
    {
        return $this->hasMany(QuestionarioRisposta::class, 'questionario_id');
    }

    public function inviatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviato_da');
    }

    public function eInviato(): bool
    {
        return $this->inviato_il !== null;
    }

    public function eAperto(): bool
    {
        return $this->eInviato() && now()->lte($this->chiude_il);
    }

    public function eChiuso(): bool
    {
        return $this->eInviato() && now()->gt($this->chiude_il);
    }
}
