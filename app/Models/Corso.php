<?php

namespace App\Models;

use App\Traits\TracksUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Corso extends Model
{
    use TracksUpdatedBy;

    protected $table = 'corsi';

    protected $fillable = [
        'evento_id',
        'nome',
        'numero_protocollo',
        'descrizione',
        'direttore_corso',
        'direttore_socio_id',
        'presidente_socio_id',
        'catalogo_corso_id',
        'link_gaia',
        'data_inizio',
        'ora_inizio',
        'data_fine',
        'ora_fine',
        'scadenza_domande',
        'residenziale',
        'pagamento_previsto',
        'quota_partecipazione',
        'richiede_documento_aggiuntivo',
        'dicitura_documento_aggiuntivo',
        'giorni_presenza',
        'form_sections',
        'invio_massivo_iscritti_at',
        'invio_massivo_sollecito_iscritti_at',
        'invio_massivo_info_at',
        'annullato_at',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio'           => 'date',
            'data_fine'             => 'date',
            'scadenza_domande'      => 'datetime',
            'residenziale'          => 'boolean',
            'pagamento_previsto'    => 'boolean',
            'quota_partecipazione'  => 'decimal:2',
            'richiede_documento_aggiuntivo' => 'boolean',
            'giorni_presenza'            => 'array',
            'form_sections'              => 'array',
            'invio_massivo_iscritti_at'           => 'datetime',
            'invio_massivo_sollecito_iscritti_at' => 'datetime',
            'invio_massivo_info_at'               => 'datetime',
            'annullato_at'                        => 'datetime',
        ];
    }

    /** true se il corso è stato annullato (troppi ritiri, non parte): reversibile, vedi Ripristina corso. */
    public function isAnnullato(): bool
    {
        return $this->annullato_at !== null;
    }

    /** true se almeno un iscritto ha già compilato il form: le voci pasto vanno congelate. */
    public function haRisposte(): bool
    {
        return $this->iscrizioniConRisposta()->exists();
    }

    protected static function booted(): void
    {
        // Il testo `direttore_corso` resta letto da liste/stampe/altre funzioni: lo scrive solo qui,
        // quando si collega un direttore. Può restare indietro se il socio cambia nome in anagrafica
        // (compromesso accettato). Senza socio collegato (corsi vecchi) il testo non si tocca.
        static::saving(function (Corso $corso) {
            if ($corso->direttore_socio_id && $corso->isDirty('direttore_socio_id')) {
                $nome = Socio::find($corso->direttore_socio_id)?->nome_completo;
                if ($nome) {
                    $corso->direttore_corso = $nome;
                }
            }
        });

        // Le voci pasto (form_sections) sono sempre derivate da giorni_presenza + residenziale:
        // si rigenerano a ogni salvataggio, tranne quando il form è già stato compilato da
        // qualcuno (in quel caso restano congelate).
        static::saving(function (Corso $corso) {
            if (! $corso->isDirty(['giorni_presenza', 'residenziale'])) {
                return;
            }
            if ($corso->exists && $corso->haRisposte()) {
                return;
            }
            $corso->form_sections = app(\App\Services\FormSectionsGenerator::class)->calcola($corso);
        });

        static::saved(function (Corso $corso) {
            if (! $corso->wasChanged('scadenza_domande') || ! $corso->scadenza_domande) {
                return;
            }
            foreach ($corso->graduatorie as $graduatoria) {
                if ($corso->scadenza_domande->lt($graduatoria->data_apertura)) {
                    continue;
                }
                if (! $corso->scadenza_domande->eq($graduatoria->data_chiusura)) {
                    $graduatoria->update(['data_chiusura' => $corso->scadenza_domande]);
                }
            }
        });
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function graduatorie(): HasMany
    {
        return $this->hasMany(Graduatoria::class);
    }

    /** Tipo di corso nel catalogo (facoltativo): dà il codice di riferimento per l'esame. */
    public function catalogoCorso(): BelongsTo
    {
        return $this->belongsTo(CatalogoCorso::class, 'catalogo_corso_id');
    }

    public function direttore(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'direttore_socio_id');
    }

    public function presidente(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'presidente_socio_id');
    }

    public function docenti(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'corso_docente', 'corso_id', 'socio_id')->withTimestamps();
    }

    public function esame(): HasOne
    {
        return $this->hasOne(Esame::class);
    }

    /**
     * Punto unico da cui il modulo esami legge direttore, presidente o delegato e docenti.
     *
     * @return array{direttore: ?Socio, presidente: ?Socio, docenti: \Illuminate\Database\Eloquent\Collection<int, Socio>}
     */
    public function staffEsame(): array
    {
        return [
            'direttore'  => $this->direttore,
            'presidente' => $this->presidente,
            'docenti'    => $this->docenti,
        ];
    }

    /**
     * Id dei docenti che non si possono togliere dal corso: hanno schede già compilate
     * (laboratori o itinere) o sono nella commissione dell'esame del corso.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function docentiNonRimovibili(): \Illuminate\Support\Collection
    {
        $esame = $this->esame;
        if (! $esame) {
            return collect();
        }

        return TcriSchedaSemaforo::whereIn('laboratorio_id', $esame->laboratori()->select('id'))->pluck('docente_socio_id')
            ->merge($esame->schedeItinere()->pluck('docente_socio_id'))
            ->merge($esame->colloqui()->where('tipo', TcriColloquio::TIPO_PERSONALE)->pluck('membro_socio_id'))
            ->merge($esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_PERSONALE)->pluck('membro_socio_id'))
            ->merge($esame->docentiCommissione()->pluck('socio_id'))
            ->unique()
            ->values();
    }

    /**
     * Giorni in presenza del corso (`giorni_presenza`, fallback ai giorni evento se non impostati),
     * ordinati. Fonte unica per presenze/pasti: mai il range data_inizio→data_fine, che comprende
     * anche la parte online e i giorni senza lezione.
     */
    public function giorniInPresenza(): \Illuminate\Support\Collection
    {
        return collect($this->giorni_presenza ?: $this->evento?->giorni_evento ?? [])
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->startOfDay())
            ->unique(fn ($d) => $d->format('Y-m-d'))
            ->sortBy(fn ($d) => $d->format('Y-m-d'))
            ->values();
    }

    /**
     * Giorni in presenza in comune con un altro corso (anche di un altro evento). Punto unico della
     * regola di sovrapposizione: due corsi sono in conflitto solo se condividono almeno un giorno in
     * presenza — i range data_inizio→data_fine possono intersecarsi senza che nessun giorno coincida.
     *
     * @return \Illuminate\Support\Collection<int, \Carbon\Carbon> ordinati
     */
    public function giorniInPresenzaInComune(self $altro): \Illuminate\Support\Collection
    {
        $altri = $altro->giorniInPresenza()->map(fn ($d) => $d->format('Y-m-d'))->all();

        return $this->giorniInPresenza()->filter(fn ($d) => in_array($d->format('Y-m-d'), $altri, true))->values();
    }

    public function sovrapponeInPresenza(self $altro): bool
    {
        return $this->giorniInPresenzaInComune($altro)->isNotEmpty();
    }

    public function iscrizioni(): HasMany
    {
        return $this->hasMany(Iscrizione::class, 'corso_id');
    }

    public function iscrizioniConRisposta(): HasMany
    {
        return $this->hasMany(Iscrizione::class, 'corso_id')
            ->whereHas('rispostaPreregistrazione');
    }

    public function iscrizioniAttive(): HasMany
    {
        return $this->hasMany(Iscrizione::class, 'corso_id')
            ->where('stato', '!=', 'ritirato');
    }

    public function iscrizioniAttiveConRisposta(): HasMany
    {
        return $this->hasMany(Iscrizione::class, 'corso_id')
            ->where('stato', '!=', 'ritirato')
            ->whereHas('rispostaPreregistrazione');
    }

    public function isMultigiorno(): bool
    {
        return $this->data_inizio->ne($this->data_fine);
    }

    public function hasDatiCorrelati(): bool
    {
        // esami.corso_id è restrictOnDelete: un corso con esame non si elimina.
        if ($this->esame()->exists()) {
            return true;
        }

        return $this->iscrizioni()
            ->where(function ($q) {
                $q->whereHas('rispostaPreregistrazione')
                  ->orWhereHas('rispostaCheckin')
                  ->orWhereHas('emailInvii');
            })
            ->exists();
    }
}
