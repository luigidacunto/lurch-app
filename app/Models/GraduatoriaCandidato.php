<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GraduatoriaCandidato extends Model
{
    /** Unico tipo di titolo verificabile oggi (diploma superiore). Altri tipi (es. laurea) andranno aggiunti qui quando servirà verificarli. */
    public const TIPO_DIPLOMA_SUPERIORE = 'diploma_superiore';

    protected $table = 'graduatoria_candidati';

    protected $fillable = [
        'graduatoria_id',
        'socio_id',
        'codice_fiscale',
        'nome',
        'cognome',
        'email',
        'telefono',
        'comitato_regionale_id',
        'comitato_locale_id',
        'stato',
        'privacy_accettata',
        'consenso_gaia_accettata',
        'privacy_versione_accettata',
        'fuori_termine',
        'confermato_at',
        'confermato_da_id',
        'aggiunto_da_id',
        'note',
        'ritirato_at',
        'ritirato_da_id',
        'motivo_ritiro',
        'punteggio_finale',
        'ammissibile',
        'ammesso',
        'posizione_classifica',
        'dettaglio_punteggio',
        'calcolato_at',
        'attestazione_presidente_path',
        'attestazione_presidente_verificata',
        'attestazione_presidente_verificata_at',
        'attestazione_presidente_verificata_da_id',
        'email_in_verifica',
        'email_verificata_at',
        'diploma_autodichiarato',
        'diploma_verificato_at',
        'diploma_verificato_da_id',
        'autocertificazione_path',
        'autocertificazione_verificata',
        'autocertificazione_verificata_at',
        'autocertificazione_verificata_da_id',
    ];

    protected function casts(): array
    {
        return [
            'privacy_accettata' => 'boolean',
            'consenso_gaia_accettata' => 'boolean',
            'fuori_termine'     => 'boolean',
            'confermato_at'     => 'datetime',
            'ritirato_at'       => 'datetime',
            'punteggio_finale'  => 'decimal:2',
            'ammissibile'       => 'boolean',
            'ammesso'           => 'boolean',
            'dettaglio_punteggio' => 'array',
            'calcolato_at'      => 'datetime',
            'attestazione_presidente_verificata' => 'boolean',
            'attestazione_presidente_verificata_at' => 'datetime',
            'email_verificata_at' => 'datetime',
            'diploma_autodichiarato' => 'boolean',
            'diploma_verificato_at' => 'datetime',
            'autocertificazione_verificata' => 'boolean',
            'autocertificazione_verificata_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (GraduatoriaCandidato $candidato) {
            if (filled($candidato->codice_fiscale)) {
                $candidato->codice_fiscale = strtoupper($candidato->codice_fiscale);
            }

            // Se l'email cambia, la verifica precedente non è più valida per il nuovo
            // indirizzo — stesso hook di Socio::booted(), vedi lì per il motivo del
            // guard su isDirty('email_verificata_at') (permette a chi ha appena
            // verificato di scrivere email+email_verificata_at nello stesso save).
            if ($candidato->exists && $candidato->isDirty('email') && ! $candidato->isDirty('email_verificata_at')) {
                $candidato->email_verificata_at = null;
            }
        });
    }

    public function graduatoria(): BelongsTo
    {
        return $this->belongsTo(Graduatoria::class);
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class);
    }

    public function comitatoLocale(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class);
    }

    /**
     * "Volontario" o "Dipendente" del socio collegato (Socio::isVolontario(), priorità al
     * comitato di appartenenza) — null se la candidatura non è (ancora) collegata a un
     * socio esistente, usato dall'export/anteprima Excel per segnalare l'anomalia (vedi
     * Graduatoria::haCandidatiNonCollegati()).
     */
    public function tipoSocio(): ?string
    {
        return $this->socio?->tipoSocio();
    }

    /**
     * Comitato di appartenenza (se il socio è volontario, priorità) o comitato dipendente
     * (solo se non ha nessun comitato di appartenenza) del socio collegato — stessa
     * priorità di Socio::isVolontario(). Null se la candidatura non è collegata a un socio.
     */
    public function comitatoSocio(): ?string
    {
        return $this->socio?->comitatoPrincipaleNome();
    }

    public function confermatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confermato_da_id');
    }

    public function aggiuntoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aggiunto_da_id');
    }

    public function ritiratoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ritirato_da_id');
    }

    public function verificataAttestazioneDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attestazione_presidente_verificata_da_id');
    }

    public function verificataAutocertificazioneDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autocertificazione_verificata_da_id');
    }

    public function verificatoreDiploma(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diploma_verificato_da_id');
    }

    /**
     * Stato di verifica del titolo di studio: quello di questa candidatura se presente,
     * altrimenti (se il socio ha già una verifica permanente per lo stesso tipo) quello
     * del socio — così un titolo verificato una volta non va riverificato ad ogni graduatoria.
     */
    public function diplomaVerificatoAt(): ?\Carbon\Carbon
    {
        return $this->diploma_verificato_at
            ?? $this->socio?->titoloStudioVerificato(self::TIPO_DIPLOMA_SUPERIORE)?->verificato_at;
    }

    public function diplomaVerificatoDa(): ?User
    {
        if ($this->diploma_verificato_at) {
            return $this->verificatoreDiploma;
        }

        return $this->socio?->titoloStudioVerificato(self::TIPO_DIPLOMA_SUPERIORE)?->verificatoDa;
    }

    public function punteggiManuali(): HasMany
    {
        return $this->hasMany(GraduatoriaPunteggioManuale::class, 'graduatoria_candidato_id');
    }

    /** Storico completo (append-only) di ritiri/ripristini di questa candidatura. */
    public function storicoRitiri(): MorphMany
    {
        return $this->morphMany(StoricoRitiro::class, 'ritirabile');
    }
}
