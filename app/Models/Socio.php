<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Socio extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'soci';

    protected $fillable = [
        'nome',
        'cognome',
        'codice_fiscale',
        'gaia_persona_id',
        'consenso_gaia_accettata',
        'privacy_versione_accettata',
        'email',
        'email_in_verifica',
        'telefono',
        'data_nascita',
        'luogo_nascita',
        'volontario_da',
        'ruolo_associativo',
        'comitato_regionale_id',
        'comitato_locale_id',
        'comitato_locale_estensione_id',
        'comitato_regionale_estensione_id',
        'comitato_locale_dipendente_id',
        'comitato_regionale_dipendente_id',
        'sottosede_appartenenza_id',
        'sottosede_estensione_id',
        'sottosede_dipendente_id',
        'allergie',
        'note_alimentari',
        'allergeni',
        'diete',
        'prefisso_telefono',
        'telefoni_secondari',
        'email_verificata_at',
        'dimesso_il',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'data_nascita'       => 'date',
            'volontario_da'      => 'date',
            'dimesso_il'         => 'date',
            'telefoni_secondari' => 'array',
            'allergeni'          => 'array',
            'diete'              => 'array',
            'email_verificata_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Socio $socio) {
            // Se l'email cambia, la verifica precedente non è più valida per il nuovo
            // indirizzo: azzerare sempre qui, unico punto centrale, indipendentemente
            // da quale flusso (pannello, import Excel, iscrizione graduatoria) la modifica.
            if ($socio->exists && $socio->isDirty('email') && ! $socio->isDirty('email_verificata_at')) {
                $socio->email_verificata_at = null;
            }

            // Rientro automatico: un socio segnato come dimesso che cambia comitato di
            // appartenenza (form pubblici di iscrizione, import, modifica manuale da pannello)
            // è un trasferimento, non una dimissione — si riattiva. La riconciliazione da import
            // che segna i dimessi NON tocca comitato_*_id, quindi non fa mai scattare questo.
            if ($socio->exists
                && $socio->dimesso_il !== null
                && ! $socio->isDirty('dimesso_il')
                && $socio->isDirty(['comitato_locale_id', 'comitato_regionale_id'])
            ) {
                $socio->dimesso_il = null;
            }
        });
    }

    public function isDimesso(): bool
    {
        return $this->dimesso_il !== null;
    }

    public function emailVerifiche(): HasMany
    {
        return $this->hasMany(EmailVerifica::class);
    }

    public function getNomeCompletoAttribute(): string
    {
        return "{$this->nome} {$this->cognome}";
    }

    /** Caratteri minimi per la ricerca globale dei soci nei campi di selezione (direttore, presidente, docenti). */
    public const RICERCA_MIN_CARATTERI = 4;

    /**
     * Ricerca globale (tutti i soci, qualunque comitato) per i campi di selezione: sotto la soglia
     * non restituisce nulla. Ogni parola deve comparire in nome, cognome o codice fiscale.
     * Il codice fiscale serve solo a cercare: l'etichetta non lo contiene mai.
     *
     * @return array<int, string> id => "Cognome Nome — comitato"
     */
    public static function cercaPerSelezione(string $search): array
    {
        $search = trim($search);
        if (mb_strlen($search) < self::RICERCA_MIN_CARATTERI) {
            return [];
        }

        $query = static::query()->with(['comitatoLocale', 'comitatoRegionale', 'comitatoLocaleDipendente', 'comitatoRegionaleDipendente']);
        foreach (preg_split('/\s+/', $search) as $parola) {
            $like = '%' . addcslashes($parola, '\\%_') . '%';
            $query->where(fn ($q) => $q->where('nome', 'ilike', $like)
                ->orWhere('cognome', 'ilike', $like)
                ->orWhere('codice_fiscale', 'ilike', $like));
        }

        return $query->orderBy('cognome')->orderBy('nome')->limit(20)->get()
            ->mapWithKeys(fn (self $s) => [$s->id => $s->etichettaSelezione()])
            ->all();
    }

    /** "Cognome Nome — comitato" (senza codice fiscale). */
    public function etichettaSelezione(): string
    {
        return trim("{$this->cognome} {$this->nome}") . ' — ' . ($this->comitatoPrincipaleNome() ?? 'nessun comitato');
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function comitatoLocale(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_id');
    }

    public function comitatoLocaleEstensione(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_estensione_id');
    }

    public function comitatoRegionaleEstensione(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_estensione_id');
    }

    public function comitatoLocaleDipendente(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_dipendente_id');
    }

    public function comitatoRegionaleDipendente(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_dipendente_id');
    }

    /**
     * Regione "effettiva" del socio, con fallback per chi non ha un'appartenenza da
     * volontario (dipendenti puri, estensioni pure): volontario → dipendente → estensione.
     * Usata per determinare eventuali override regionali di scadenza qualifiche
     * (CatalogoCorso::scadenzaPerRegione) quando comitato_regionale_id è null.
     */
    public function comitatoRegionaleEffettivoId(): ?int
    {
        return $this->comitato_regionale_id
            ?? $this->comitato_regionale_dipendente_id
            ?? $this->comitato_regionale_estensione_id;
    }

    public function sottosedeAppartenenza(): BelongsTo
    {
        return $this->belongsTo(ComitatoSottosede::class, 'sottosede_appartenenza_id');
    }

    public function sottosedeEstensione(): BelongsTo
    {
        return $this->belongsTo(ComitatoSottosede::class, 'sottosede_estensione_id');
    }

    public function sottosedeDipendente(): BelongsTo
    {
        return $this->belongsTo(ComitatoSottosede::class, 'sottosede_dipendente_id');
    }

    public function setCodiceFiscaleAttribute(string $value): void
    {
        $this->attributes['codice_fiscale'] = strtoupper(trim($value));
    }

    public function getComitatoDisplayAttribute(): string
    {
        $nome = $this->comitatoLocale?->nome ?? '—';
        $sub  = $this->sottosedeAppartenenza?->nome;
        return $sub ? "{$nome}: {$sub}" : $nome;
    }

    /**
     * Volontario in un comitato di appartenenza ha SEMPRE priorità su "dipendente", a
     * prescindere da `ruolo_associativo` — quel campo viene sovrascritto a 'dipendente'
     * da SociImport::processaDipendente() ("il ruolo dipendente ha priorità su qualsiasi
     * ruolo precedente") anche quando il socio ha ANCHE un comitato di appartenenza da
     * volontario, quindi non basta a dire "è (anche) volontario da qualche parte".
     * Include anche i ruoli speciali (infermiere_volontarie, corpo_militare): sono comunque
     * volontari con un comitato di appartenenza, non dipendenti. Centralizzato qui (non
     * duplicato per-feature) perché deve valere su tutto: stessa priorità ovunque nel
     * progetto si distingua volontario/dipendente.
     */
    public function isVolontario(): bool
    {
        return (bool) ($this->comitato_locale_id || $this->comitato_regionale_id);
    }

    /** "Dipendente" solo se non ha nessun comitato di appartenenza da volontario — vedi isVolontario(). */
    public function isDipendente(): bool
    {
        return ! $this->isVolontario();
    }

    /** "Volontario" / "Dipendente" — vedi isVolontario(). */
    public function tipoSocio(): string
    {
        return $this->isVolontario() ? 'Volontario' : 'Dipendente';
    }

    /** Comitato da volontario (priorità) o, solo se non è volontario, comitato da dipendente. */
    public function comitatoPrincipaleNome(): ?string
    {
        $comitato = $this->isVolontario()
            ? ($this->comitatoLocale ?? $this->comitatoRegionale)
            : ($this->comitatoLocaleDipendente ?? $this->comitatoRegionaleDipendente);

        return $comitato?->nome;
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class, 'partecipante_id');
    }

    public function logModificheEmail(): HasMany
    {
        return $this->hasMany(LogModificaEmail::class, 'partecipante_id');
    }

    public function partecipazioniEvento(): HasMany
    {
        return $this->hasMany(SocioEvento::class, 'partecipante_id');
    }

    public function eventi(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'socio_evento', 'partecipante_id', 'evento_id')
                    ->withPivot('ruolo', 'abilitato_app', 'created_at');
    }

    public function iscrizioni(): HasMany
    {
        return $this->hasMany(Iscrizione::class, 'partecipante_id');
    }

    public function iscrizioniStaff(): HasMany
    {
        return $this->hasMany(IscrizioneStaff::class, 'partecipante_id');
    }

    public function patenti(): BelongsToMany
    {
        return $this->belongsToMany(PatenteCri::class, 'socio_patenti', 'socio_id', 'patente_cri_id')
                    ->withPivot('fonte', 'data_rilascio', 'data_scadenza', 'protocollo')
                    ->withTimestamps();
    }

    public function qualificheSoci(): HasMany
    {
        return $this->hasMany(QualificaSocio::class);
    }

    public function titoliStudioVerificati(): HasMany
    {
        return $this->hasMany(SocioTitoloStudioVerificato::class);
    }

    /** Verifica permanente di un titolo di studio per questo socio, se già effettuata in passato. */
    public function titoloStudioVerificato(string $tipo): ?SocioTitoloStudioVerificato
    {
        return $this->relationLoaded('titoliStudioVerificati')
            ? $this->titoliStudioVerificati->firstWhere('tipo', $tipo)
            : $this->titoliStudioVerificati()->where('tipo', $tipo)->first();
    }

    /**
     * Storico ritiri/ripristini dalle candidature graduatorie di questo socio. Il
     * vincolo su ritirabile_type è necessario perché storico_ritiri è polimorfica:
     * senza filtro, hasManyThrough matcherebbe per coincidenza anche righe di
     * altre sorgenti (iscrizioni, candidature staff) con lo stesso id numerico.
     */
    public function ritiriGraduatorie(): HasManyThrough
    {
        return $this->hasManyThrough(
            StoricoRitiro::class,
            GraduatoriaCandidato::class,
            'socio_id', 'ritirabile_id', 'id', 'id'
        )->where('storico_ritiri.ritirabile_type', GraduatoriaCandidato::class);
    }
}
