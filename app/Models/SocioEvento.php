<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class SocioEvento extends Model
{
    public $timestamps = false;

    protected $table = 'socio_evento';

    protected $fillable = [
        'evento_id',
        'partecipante_id',
        'ruolo',
        'abilitato_app',
        'badge_token',
        'badge_tokens_legacy',
    ];

    protected function casts(): array
    {
        return [
            'abilitato_app'        => 'boolean',
            'badge_tokens_legacy'  => 'array',
            'created_at'           => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->badge_token)) {
                $model->badge_token = (string) Str::uuid();
            }
        });
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    /** Alias canonico: partecipante_id è la FK verso soci */
    public function partecipante(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }

    /** @deprecated usare partecipante() */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'partecipante_id');
    }

    /** Accessi checkin/out per questa persona×evento */
    public function accessi(): MorphMany
    {
        return $this->morphMany(AccessoEvento::class, 'iscrizioneable');
    }

    public function ultimoAccesso(): MorphOne
    {
        return $this->morphOne(AccessoEvento::class, 'iscrizioneable')
            ->latestOfMany('timestamp_accesso');
    }

    /** Risposta confermata al primo checkin (una sola per persona×evento) */
    public function rispostaCheckin(): MorphOne
    {
        return $this->morphOne(RispostaCheckin::class, 'iscrizioneable');
    }

    /** Iscrizioni ai corsi di questo evento per questo partecipante */
    public function iscrizioni(): HasMany
    {
        return $this->hasMany(Iscrizione::class, 'partecipante_id', 'partecipante_id')
            ->whereHas('corso', fn ($q) => $q->where('evento_id', $this->evento_id));
    }

    /** Messaggi anonimi lasciati da chi scansiona il badge */
    public function messaggi(): HasMany
    {
        return $this->hasMany(MessaggioBadge::class, 'socio_evento_id');
    }

    /** Iscrizione staff a questo evento per questo partecipante */
    public function iscrizioneStaff(): HasOne
    {
        return $this->hasOne(IscrizioneStaff::class, 'partecipante_id', 'partecipante_id')
            ->where('evento_id', $this->evento_id);
    }

    public function isCorsista(): bool
    {
        return $this->ruolo === 'corsista';
    }

    public function isStaff(): bool
    {
        return $this->ruolo === 'staff';
    }

    public function isPresente(): bool
    {
        return $this->ultimoAccesso?->tipo === 'entrata';
    }
}
