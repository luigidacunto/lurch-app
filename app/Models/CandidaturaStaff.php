<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CandidaturaStaff extends Model
{
    protected $table = 'candidature_staff';

    protected $fillable = [
        'evento_id',
        'socio_id',
        'codice_fiscale',
        'nome',
        'cognome',
        'email',
        'telefono',
        'prefisso_telefono',
        'comitato_regionale_id',
        'comitato_locale_id',
        'disponibilita',
        'stato',
        'privacy_accettata',
        'consenso_gaia_accettata',
        'privacy_versione_accettata',
        'dichiarazione_esterno',
        'fuori_termine',
        'confermato_at',
        'confermato_da_id',
        'aggiunto_da_id',
        'email_in_verifica',
        'email_verificata_at',
        'confermato_esterno_at',
        'confermato_esterno_da_id',
    ];

    protected function casts(): array
    {
        return [
            'disponibilita'      => 'array',
            'privacy_accettata'  => 'boolean',
            'consenso_gaia_accettata' => 'boolean',
            'dichiarazione_esterno' => 'boolean',
            'fuori_termine'      => 'boolean',
            'confermato_at'      => 'datetime',
            'email_verificata_at' => 'datetime',
            'confermato_esterno_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $candidatura) {
            if (filled($candidatura->codice_fiscale)) {
                $candidatura->codice_fiscale = strtoupper($candidatura->codice_fiscale);
            }

            // Stesso hook di Socio::booted() — vedi lì per il motivo del guard
            // su isDirty('email_verificata_at').
            if ($candidatura->exists && $candidatura->isDirty('email') && ! $candidatura->isDirty('email_verificata_at')) {
                $candidatura->email_verificata_at = null;
            }
        });
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function comitatoLocale(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_id');
    }

    public function corsi(): BelongsToMany
    {
        return $this->belongsToMany(Corso::class, 'candidatura_staff_corso');
    }

    public function confermatoEsternoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confermato_esterno_da_id');
    }

    /** True se in almeno uno slot ha dato disponibilità come docente. */
    public function haDisponibilitaDocente(): bool
    {
        return collect($this->disponibilita)->contains(fn ($d) => ($d['tipo'] ?? null) === 'docente');
    }
}
