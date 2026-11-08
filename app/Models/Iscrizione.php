<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class Iscrizione extends Model
{
    protected $table = 'iscrizioni';

    protected $fillable = [
        'corso_id',
        'partecipante_id',
        'token',
        'stato',
        'form_inviato_at',
        'sollecito_inviato_at',
        'info_evento_inviato_at',
        'qr_code_path',
        'badge_token',
        'non_ammesso',
        'non_ammesso_motivo',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->badge_token)) {
                $model->badge_token = (string) Str::uuid();
            }
        });

        // Dopo la creazione auto-crea SocioEvento se non esiste già. Il ruolo va nella
        // ricerca (non solo nei valori di default): l'indice unico è su
        // (evento_id, partecipante_id, ruolo), quindi lo stesso socio può avere righe
        // diverse per evento con ruoli diversi (es. corsista + staff) — senza 'ruolo'
        // nella ricerca, firstOrCreate() trova ed accetta una riga con un ruolo diverso
        // già esistente invece di crearne una nuova per il corsista.
        static::created(function (self $model) {
            $eventoId = $model->corso?->evento_id;
            if ($eventoId && $model->partecipante_id) {
                SocioEvento::firstOrCreate([
                    'partecipante_id' => $model->partecipante_id,
                    'evento_id'       => $eventoId,
                    'ruolo'           => 'corsista',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'form_inviato_at'         => 'datetime',
            'sollecito_inviato_at'    => 'datetime',
            'info_evento_inviato_at'  => 'datetime',
            'non_ammesso'             => 'boolean',
        ];
    }

    /** Non ammesso all'esame (assenze o altri motivi). Il ritirato si legge da `stato`, non da qui. */
    public function isNonAmmesso(): bool
    {
        return $this->non_ammesso;
    }

    public function corso(): BelongsTo
    {
        return $this->belongsTo(Corso::class, 'corso_id');
    }

    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }

    /** Risposta al form di preregistrazione (polimorfica) */
    public function rispostaPreregistrazione(): MorphOne
    {
        return $this->morphOne(RispostaPreregistrazione::class, 'iscrizioneable');
    }

    /** Risposta al checkin (polimorfica) */
    public function rispostaCheckin(): MorphOne
    {
        return $this->morphOne(RispostaCheckin::class, 'iscrizioneable');
    }

    /** Invii email legati a questa iscrizione (polimorfica) */
    public function emailInvii(): MorphMany
    {
        return $this->morphMany(EmailInvio::class, 'iscrizioneable');
    }

    /** Accessi check-in/out (polimorfica) */
    public function accessi(): MorphMany
    {
        return $this->morphMany(AccessoEvento::class, 'iscrizioneable');
    }

    public function ultimoAccesso(): MorphOne
    {
        return $this->morphOne(AccessoEvento::class, 'iscrizioneable')
            ->latestOfMany('timestamp_accesso');
    }

    public function isPresente(): bool
    {
        return $this->ultimoAccesso?->tipo === 'entrata';
    }

    public function isPending(): bool
    {
        return $this->stato === 'pending';
    }

    public function isFormCompletato(): bool
    {
        return $this->stato === 'form_completato';
    }

    public function isCheckinFatto(): bool
    {
        return $this->stato === 'checkin_fatto';
    }

    public function isRitirato(): bool
    {
        return $this->stato === 'ritirato';
    }
}
