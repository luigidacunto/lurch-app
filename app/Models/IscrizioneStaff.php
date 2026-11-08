<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class IscrizioneStaff extends Model
{
    protected $table = 'iscrizioni_staff';

    protected $fillable = [
        'evento_id',
        'partecipante_id',
        'token',
        'giorni_disponibili',
        'stato',
        'form_inviato_at',
        'sollecito_inviato_at',
        'info_evento_inviato_at',
        'ruolo',
        'qr_code_path',
        'badge_token',
        'candidatura_staff_id',
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
        // già esistente invece di crearne una nuova per lo staff.
        static::created(function (self $model) {
            if ($model->evento_id && $model->partecipante_id) {
                SocioEvento::firstOrCreate([
                    'partecipante_id' => $model->partecipante_id,
                    'evento_id'       => $model->evento_id,
                    'ruolo'           => 'staff',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'giorni_disponibili'     => 'array',
            'form_inviato_at'        => 'datetime',
            'sollecito_inviato_at'   => 'datetime',
            'info_evento_inviato_at' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }

    public function candidaturaStaff(): BelongsTo
    {
        return $this->belongsTo(CandidaturaStaff::class);
    }

    /** Risposta al form di preregistrazione staff (polimorfica) */
    public function rispostaPreregistrazione(): MorphOne
    {
        return $this->morphOne(RispostaPreregistrazione::class, 'iscrizioneable');
    }

    /** Risposta al checkin staff (polimorfica) */
    public function rispostaCheckin(): MorphOne
    {
        return $this->morphOne(RispostaCheckin::class, 'iscrizioneable');
    }

    /** Invii email legati a questa iscrizione staff (polimorfica) */
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
}
