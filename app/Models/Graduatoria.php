<?php

namespace App\Models;

use App\Casts\ArrayOrCsvString;
use App\Traits\TracksUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Graduatoria extends Model
{
    use TracksUpdatedBy;

    protected $table = 'graduatorie';

    protected $fillable = [
        'titolo',
        'codice_protocollo',
        'corso_id',
        'comitato_regionale_id',
        'comitato_locale_id',
        'data_apertura',
        'data_chiusura',
        'testo_email_conferma',
        'richiede_corso_sicurezza',
        'preferenza_diversifica_comitato',
        'punteggio_diversifica_comitato',
        'catalogo_corso_diversifica_id',
        'selezione_a_rotazione_comitati',
        'rotazione_multi_turno',
        'priorita_eta_a_parita_punteggio',
        'qualifiche_propedeutiche',
        'posti_disponibili',
        'richiede_attestazione_presidente',
        'punteggio_attestazione_presidente',
        'richiede_diploma',
        'richiede_autocertificazione',
        'dicitura_autocertificazione',
    ];

    protected function casts(): array
    {
        return [
            'data_apertura' => 'datetime',
            'data_chiusura' => 'datetime',
            'richiede_corso_sicurezza' => 'boolean',
            'preferenza_diversifica_comitato' => 'boolean',
            'punteggio_diversifica_comitato' => 'decimal:2',
            'selezione_a_rotazione_comitati' => 'boolean',
            'rotazione_multi_turno' => 'boolean',
            'priorita_eta_a_parita_punteggio' => 'boolean',
            'qualifiche_propedeutiche' => ArrayOrCsvString::class,
            'posti_disponibili' => 'integer',
            'richiede_attestazione_presidente' => 'boolean',
            'punteggio_attestazione_presidente' => 'decimal:2',
            'richiede_diploma' => 'boolean',
            'richiede_autocertificazione' => 'boolean',
        ];
    }

    // Esclude 0/O/1/I/L: caratteri facilmente confondibili quando il codice viene letto/dettato a voce.
    private const ALFABETO_CODICE_PUBBLICO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        static::creating(function (Graduatoria $graduatoria) {
            if (blank($graduatoria->codice_pubblico)) {
                $graduatoria->codice_pubblico = static::generaCodicePubblico();
            }
        });

        static::saved(function (Graduatoria $graduatoria) {
            if (! $graduatoria->wasChanged('data_chiusura') || ! $graduatoria->corso_id) {
                return;
            }
            $corso = $graduatoria->corso;
            if ($corso && (! $corso->scadenza_domande || ! $graduatoria->data_chiusura->eq($corso->scadenza_domande))) {
                $corso->update(['scadenza_domande' => $graduatoria->data_chiusura]);
            }
        });
    }

    /**
     * Codice pubblico breve, indipendente dal corso/protocollo (che possono cambiare nel
     * tempo) — identifica stabilmente l'URL pubblico della graduatoria. Generato una sola
     * volta alla creazione, mai rigenerato.
     */
    public static function generaCodicePubblico(): string
    {
        do {
            $codice = collect(range(1, 10))
                ->map(fn () => self::ALFABETO_CODICE_PUBBLICO[random_int(0, strlen(self::ALFABETO_CODICE_PUBBLICO) - 1)])
                ->implode('');
        } while (static::where('codice_pubblico', $codice)->exists());

        return $codice;
    }

    public function corso(): BelongsTo
    {
        return $this->belongsTo(Corso::class);
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
     * Corso del catalogo qualifiche "in oggetto" per il criterio "diversifica comitato" —
     * sostituisce, dal 25/08/2026, la vecchia lettura del 3° blocco di codice_protocollo
     * (rimossa perché il protocollo non è più un campo affidabile: opzionale, non più
     * legato a un singolo corso). Esplicito invece che dedotto da una stringa.
     */
    public function catalogoCorsoDiversifica(): BelongsTo
    {
        return $this->belongsTo(CatalogoCorso::class, 'catalogo_corso_diversifica_id');
    }

    public function operatori(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'graduatoria_operatore')
            ->withPivot('created_at');
    }

    public function candidati(): HasMany
    {
        return $this->hasMany(GraduatoriaCandidato::class);
    }

    public function qualifichePreferenziali(): HasMany
    {
        return $this->hasMany(GraduatoriaQualificaPreferenziale::class);
    }

    /**
     * Candidati con un socio agganciato ma ancora senza ID profilo GAIA — usato dal bridge
     * userscript "LURCH - GAIA Bridge" (endpoint
     * /api/v1/graduatorie/{graduatoria}/gaia-persona-id/da-cercare). Includati anche i
     * ritirati: l'aggancio a GAIA è un dato del socio, non della candidatura, quindi resta
     * utile anche dopo un ritiro (es. per la lettura curriculum dalla scheda socio).
     */
    public function candidatiDaCercareSuGaia(): \Illuminate\Support\Collection
    {
        return $this->candidati()
            ->whereNotNull('socio_id')
            ->with('socio')
            ->get()
            ->filter(fn (GraduatoriaCandidato $c) => $c->socio && blank($c->socio->gaia_persona_id))
            ->map(fn (GraduatoriaCandidato $c) => ['socio_id' => $c->socio_id, 'cf' => $c->socio->codice_fiscale])
            ->values();
    }

    /**
     * Un'altra graduatoria ha già lo stesso titolo (case-insensitive)? Usato per
     * suggerire (non bloccare) di aggiungere anno/periodo al titolo quando una
     * graduatoria viene riproposta — vedi Create/EditGraduatoria.
     */
    public static function titoloDuplicato(string $titolo, ?int $ignoraId = null): bool
    {
        return static::whereRaw('LOWER(titolo) = ?', [mb_strtolower(trim($titolo))])
            ->when($ignoraId, fn ($q) => $q->where('id', '!=', $ignoraId))
            ->exists();
    }

    public function isAperta(): bool
    {
        $ora = now();
        return $ora->between($this->data_apertura, $this->data_chiusura);
    }

    public function puoGestire(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperadmin()) {
            return true;
        }
        if ($user->isAdmin()) {
            return $this->comitato_regionale_id === $user->comitato_regionale_id;
        }
        if ($user->isAdminLocale()) {
            return $this->comitato_locale_id !== null
                && $this->comitato_locale_id === $user->comitato_locale_id;
        }
        if ($user->isOperatore()) {
            return $user->can('graduatorie.manage')
                && $this->operatori->contains('id', $user->id);
        }
        return false;
    }

    /**
     * Codici reali dei corsi che compongono l'aggregatore interno "AGGR-SIC" (Salute e
     * Sicurezza). L'aggregatore stesso non va mai mostrato/nominato al candidato: è una
     * convenzione interna al pannello per gli operatori, non un codice ufficiale CRI.
     */
    public function corsiGruppoSicurezza(): \Illuminate\Support\Collection
    {
        $aggregatore = CatalogoCorso::where('codice', 'AGGR-SIC')->first();
        if (! $aggregatore) {
            return collect();
        }

        return CatalogoCorso::where('equivalente_a_id', $aggregatore->id)->pluck('codice');
    }

    /**
     * Codici corso di tutte le qualifiche coinvolte nel calcolo di questa graduatoria
     * (sicurezza + propedeutiche + preferenziali) — usato per il disclaimer "ricontrolla
     * l'import GAIA prima di chiudere" nella pagina Gestione, così l'operatore sa
     * esattamente quali qualifiche ri-esportare/importare.
     */
    public function codiciQualificheRilevanti(): \Illuminate\Support\Collection
    {
        $codici = collect();

        if ($this->richiede_corso_sicurezza) {
            $codici = $codici->merge($this->corsiGruppoSicurezza());
        }

        $codici = $codici->merge($this->propedeuticheEffettive());
        $codici = $codici->merge($this->qualifichePreferenziali->pluck('codici_corso')->flatten());

        return $codici->unique()->sort()->values();
    }

    /**
     * Normalizza i codici configurati in "qualifiche_propedeutiche" scartando i duplicati
     * per equivalenza: se in configurazione compaiono sia un corso "figlio" (con
     * equivalente_a_id valorizzato) sia il suo corso target, o più figli dello stesso
     * target, resta un solo codice per gruppo (il target, se presente in lista; altrimenti
     * il primo figlio incontrato). Rete di sicurezza in caso di configurazione ridondante
     * (es. "S2030, A-S2030" dove S2030 è equivalente ad A-S2030): senza questo, il
     * requisito verrebbe verificato due volte (righe duplicate in elencoPrerequisiti/
     * statoRequisiti) invece di una sola, pur senza cambiare l'esito del controllo.
     * Un solo livello, senza ricorsione — coerente con idsConEquivalenti().
     *
     * @return array<int, string>
     */
    private function propedeuticheEffettive(): array
    {
        $codici = collect($this->qualifiche_propedeutiche ?? [])
            ->map(fn ($c) => strtoupper(trim($c)))
            ->filter()
            ->unique()
            ->values();

        if ($codici->isEmpty()) {
            return [];
        }

        $corsi = CatalogoCorso::whereIn('codice', $codici->all())->get()->keyBy('codice');

        $sceltoPerGruppo = [];
        foreach ($codici as $codice) {
            $corso = $corsi->get($codice);
            if (! $corso) {
                continue; // codice non riconosciuto, gestito a parte sotto
            }

            $isTarget = $corso->equivalente_a_id === null;
            $gruppoId = $corso->equivalente_a_id ?? $corso->id;

            if ($isTarget || ! isset($sceltoPerGruppo[$gruppoId])) {
                $sceltoPerGruppo[$gruppoId] = $codice;
            }
        }

        $nonRiconosciuti = $codici->filter(fn ($c) => ! $corsi->has($c))->all();

        return array_values(array_unique(array_merge(array_values($sceltoPerGruppo), $nonRiconosciuti)));
    }

    /**
     * Data entro cui la formazione Salute e Sicurezza deve restare valida: la fine del
     * corso collegato (deve coprire l'intera durata del corso, non solo l'apertura
     * candidature), con fallback alla chiusura candidature se non c'è un corso collegato.
     */
    private function orizzonteSicurezza(): \Carbon\Carbon
    {
        return $this->corso?->data_fine ?? $this->data_chiusura;
    }

    /**
     * Dettaglio di una singola qualifica del socio rispetto al codice corso richiesto,
     * confrontato con la data limite indicata: stato (ok | in_scadenza_entro_limite |
     * scaduta | assente) + la scadenza della riga "migliore" (quella che determina lo
     * stato) + il nome del corso. Considera anche i corsi equivalenti (idsConEquivalenti);
     * tra tutte le righe possedute, ordinando per scadenza decrescente (null = nessuna
     * scadenza, sempre in testa) la prima riga determina esattamente lo stesso stato
     * "migliore" che sceglierebbe un confronto per categoria (ok > in_scadenza > scaduta).
     *
     * @return array{stato:string, scadenza:?\Carbon\Carbon, nome:string}
     */
    private function dettaglioQualifica(Socio $socio, string $codice, \Carbon\Carbon $entro): array
    {
        $corso = CatalogoCorso::where('codice', strtoupper($codice))->first();
        if (! $corso) {
            return ['stato' => 'assente', 'scadenza' => null, 'nome' => $codice];
        }

        $ids = CatalogoCorso::idsConEquivalenti($corso->id);
        $qualifiche = QualificaSocio::where('socio_id', $socio->id)
            ->whereIn('catalogo_corso_id', $ids)
            ->get();

        if ($qualifiche->isEmpty()) {
            return ['stato' => 'assente', 'scadenza' => null, 'nome' => $corso->nome];
        }

        $migliore = $qualifiche->sortByDesc(fn (QualificaSocio $q) => $q->scadenza_calcolata?->timestamp ?? PHP_INT_MAX)->first();

        $stato = match (true) {
            $migliore->scadenza_calcolata === null => 'ok',
            $migliore->scadenza_calcolata->lt(now()) => 'scaduta',
            $migliore->scadenza_calcolata->lt($entro) => 'in_scadenza_entro_limite',
            default => 'ok',
        };

        return ['stato' => $stato, 'scadenza' => $migliore->scadenza_calcolata, 'nome' => $corso->nome];
    }

    /**
     * Stato di una singola qualifica del socio (solo etichetta) — vedi dettaglioQualifica().
     */
    private function statoQualifica(Socio $socio, string $codice, \Carbon\Carbon $entro): string
    {
        return $this->dettaglioQualifica($socio, $codice, $entro)['stato'];
    }

    /**
     * Elenco dettagliato dei prerequisiti (sicurezza + propedeutici) per un socio, con
     * nome, stato e scadenza per riga — usato dalla modale "Dettagli candidatura" in
     * pannello. Vuoto se il socio non è ancora agganciato (nulla da verificare).
     *
     * @return array<int, array{nome:string, stato:string, scadenza:?\Carbon\Carbon}>
     */
    public function elencoPrerequisiti(?Socio $socio, ?GraduatoriaCandidato $candidato = null): array
    {
        $righe = [];

        if ($socio) {
            if ($this->richiede_corso_sicurezza) {
                $d = $this->dettaglioQualifica($socio, 'AGGR-SIC', $this->orizzonteSicurezza());
                $righe[] = ['nome' => 'Salute e Sicurezza', 'stato' => $d['stato'], 'scadenza' => $d['scadenza']];
            }

            foreach ($this->propedeuticheEffettive() as $codice) {
                $d = $this->dettaglioQualifica($socio, $codice, $this->data_chiusura);
                $righe[] = ['nome' => $d['nome'], 'stato' => $d['stato'], 'scadenza' => $d['scadenza']];
            }
        }

        // Il titolo di studio è autodichiarato dal candidato e verificato manualmente
        // dall'operatore su GAIA: dipende dalla candidatura, non dal socio, quindi resta
        // visibile anche senza un socio agganciato (a differenza di sicurezza/propedeutiche).
        if ($this->richiede_diploma) {
            $righe[] = [
                'nome'     => 'Titolo di studio superiore (autodichiarato)',
                'stato'    => $candidato?->diplomaVerificatoAt() ? 'ok' : 'non_verificato',
                'scadenza' => null,
            ];
        }

        // Anche l'autocertificazione dipende dalla candidatura (documento caricato +
        // verificato manualmente), non dal socio — visibile anche senza socio agganciato.
        if ($this->richiede_autocertificazione) {
            $righe[] = [
                'nome'     => 'Autocertificazione attività',
                'stato'    => $candidato?->autocertificazione_verificata ? 'ok' : 'non_verificato',
                'scadenza' => null,
            ];
        }

        return $righe;
    }

    /**
     * Elenco di TUTTE le condizioni che possono dare punteggio extra secondo la
     * configurazione della graduatoria — qualifiche preferenziali, "diversifica
     * comitato" e "attestazione presidente" — non solo quelle possedute. Usato dalla
     * modale "Dettagli candidatura" per mostrare il quadro completo dei criteri
     * configurati, coerente con calcolaClassifica() (stessa logica, mai duplicata).
     * `$candidato` serve solo per l'attestazione presidente (per-candidatura, non
     * per-socio). Senza `$socio` le voci socio-dipendenti restano in elenco ma
     * marcate `verificabile=false` (nessun dato da cui leggere le qualifiche).
     *
     * @return array<int, array{label:string, punteggio:string, ottenuta:bool, verificabile:bool, scadenza:?\Carbon\Carbon}>
     */
    public function elencoCondizioniPunteggio(?Socio $socio, ?GraduatoriaCandidato $candidato = null): array
    {
        $righe = [];

        foreach ($this->qualifichePreferenziali as $riga) {
            $codici   = $riga->codici_corso ?? [];
            $ottenuta = false;
            $scadenza = null;

            if ($socio) {
                foreach ($codici as $codice) {
                    $d = $this->dettaglioQualifica($socio, $codice, $this->data_chiusura);
                    if ($d['stato'] !== 'assente') {
                        $ottenuta = true;
                        $scadenza = $d['scadenza'];
                        break;
                    }
                }
            }

            $righe[] = [
                'label'        => implode(', ', $codici),
                'punteggio'    => '+' . $riga->punteggio,
                'ottenuta'     => $ottenuta,
                'verificabile' => (bool) $socio,
                'scadenza'     => $scadenza,
            ];
        }

        if ($this->preferenza_diversifica_comitato) {
            $corso    = $socio ? $this->catalogoCorsoDiversifica : null;
            $ottenuta = $corso && $this->diversificaComitato($corso, $socio);

            $righe[] = [
                'label'        => 'Assenza di figure formate nel comitato',
                'punteggio'    => $this->punteggio_diversifica_comitato !== null
                    ? '+' . $this->punteggio_diversifica_comitato
                    : 'Priorità assoluta',
                'ottenuta'     => $ottenuta,
                'verificabile' => (bool) $socio,
                'scadenza'     => null,
            ];
        }

        if ($this->richiede_attestazione_presidente && $this->punteggio_attestazione_presidente !== null) {
            $righe[] = [
                'label'        => 'Attestazione presidente verificata',
                'punteggio'    => '+' . $this->punteggio_attestazione_presidente,
                'ottenuta'     => (bool) $candidato?->attestazione_presidente_verificata,
                'verificabile' => true,
                'scadenza'     => null,
            ];
        }

        return $righe;
    }

    /**
     * Candidati in graduatoria (ammissibili, non ritirati) nell'ordine di classifica —
     * stessa query/ordinamento di CandidatiRelationManager::table(), usata da
     * GraduatoriaCandidatiExport e dall'anteprima HTML per non duplicare la logica.
     */
    public function candidatiClassificati(): Collection
    {
        return $this->candidati()
            ->where(fn ($q) => $q->whereNull('ammissibile')->orWhere('ammissibile', true))
            ->whereNull('ritirato_at')
            ->with(['socio.comitatoLocale', 'socio.comitatoRegionale', 'socio.comitatoLocaleDipendente', 'socio.comitatoRegionaleDipendente', 'comitatoLocale'])
            ->orderByRaw('posizione_classifica IS NULL')
            ->orderBy('posizione_classifica')
            ->orderBy('cognome')
            ->get();
    }

    /**
     * Candidati esclusi (non ammissibili o ritirati) con il motivo già formattato — stessa
     * logica di CandidatiEsclusiRelationManager::table(), usata dal foglio "Esclusi"
     * dell'export Excel.
     *
     * @return Collection<int, array{candidato: GraduatoriaCandidato, motivo: string}>
     */
    public function candidatiEsclusiConMotivo(): Collection
    {
        return $this->candidati()
            ->where(fn ($q) => $q->where('ammissibile', false)->orWhereNotNull('ritirato_at'))
            ->with(['socio.comitatoLocale', 'socio.comitatoRegionale', 'socio.comitatoLocaleDipendente', 'socio.comitatoRegionaleDipendente', 'comitatoLocale'])
            ->orderBy('cognome')
            ->get()
            ->map(fn (GraduatoriaCandidato $c) => [
                'candidato' => $c,
                'motivo' => $c->ritirato_at
                    ? 'Ritirato il ' . $c->ritirato_at->format('d/m/Y') . ($c->motivo_ritiro ? ' — ' . $c->motivo_ritiro : '')
                    : ($this->riepilogoProblemiRequisiti($c->socio, $c) ?? 'Socio non trovato in archivio'),
            ]);
    }

    /**
     * True se almeno un candidato (ammesso o escluso) non è collegato a un socio
     * esistente — anomalia da sistemare prima di esportare/pubblicare la lista, perché
     * per quei candidati non si può risalire a comitato di appartenenza/dipendente.
     * Blocca l'export Excel (vedi ExportGraduatoriaCandidatiExcelController).
     */
    public function haCandidatiNonCollegati(): bool
    {
        return $this->candidati()->whereNull('socio_id')->exists();
    }

    /** Timestamp dell'ultimo calcolaClassifica() eseguito su un qualsiasi candidato di questa graduatoria. */
    public function ultimoCalcoloClassifica(): ?\Carbon\Carbon
    {
        $max = $this->candidati()->max('calcolato_at');

        return $max ? \Carbon\Carbon::parse($max) : null;
    }

    /**
     * Stato grezzo dei requisiti propedeutici per un socio — base condivisa da cui
     * derivano sia i messaggi pubblici (avvisiRequisiti) sia la colonna admin
     * (coloreRequisiti/riepilogoProblemiRequisiti).
     *
     * @return array{sicurezza: ?string, propedeutici: array<string,string>, diploma: ?string, autocertificazione: ?string}
     */
    public function statoRequisiti(?Socio $socio, ?GraduatoriaCandidato $candidato = null): array
    {
        // Il titolo di studio e l'autocertificazione dipendono dalla candidatura
        // (autodichiarazione/upload + verifica manuale), non dal socio — valutabili
        // anche senza un socio agganciato.
        $diploma = $this->richiede_diploma
            ? ($candidato?->diplomaVerificatoAt() ? 'ok' : 'non_verificato')
            : null;

        $autocertificazione = $this->richiede_autocertificazione
            ? ($candidato?->autocertificazione_verificata ? 'ok' : 'non_verificato')
            : null;

        if (! $socio) {
            return ['sicurezza' => null, 'propedeutici' => [], 'diploma' => $diploma, 'autocertificazione' => $autocertificazione];
        }

        $sicurezza = $this->richiede_corso_sicurezza
            ? $this->statoQualifica($socio, 'AGGR-SIC', $this->orizzonteSicurezza())
            : null;

        $propedeutici = [];
        foreach ($this->propedeuticheEffettive() as $codice) {
            $propedeutici[$codice] = $this->statoQualifica($socio, $codice, $this->data_chiusura);
        }

        return ['sicurezza' => $sicurezza, 'propedeutici' => $propedeutici, 'diploma' => $diploma, 'autocertificazione' => $autocertificazione];
    }

    /**
     * Messaggi da mostrare al candidato in pagina "grazie". Non bloccanti: la
     * candidatura viene sempre accettata, questo è solo un avviso. Ogni propedeutico
     * problematico (compreso "assente") genera un messaggio dedicato.
     */
    public function avvisiRequisiti(Socio $socio): array
    {
        $stato  = $this->statoRequisiti($socio);
        $avvisi = [];

        if ($stato['sicurezza'] !== null && $stato['sicurezza'] !== 'ok') {
            $codici = $this->corsiGruppoSicurezza();
            $suffisso = $codici->isNotEmpty() ? ' (richiesto uno tra: ' . $codici->implode(', ') . ')' : '';

            $avvisi[] = match ($stato['sicurezza']) {
                'assente' => "Non ci risulta caricato correttamente su GAIA un corso di formazione Salute e Sicurezza valido a tuo nome{$suffisso}. È un requisito di legge: verifica prima con il tuo delegato territoriale alla formazione e, se necessario, contatta la segreteria di formazione regionale prima della chiusura delle iscrizioni.",
                'scaduta' => "La tua formazione Salute e Sicurezza{$suffisso} risulta scaduta su GAIA. Verifica prima con il tuo delegato territoriale alla formazione e, se necessario, contatta la segreteria di formazione regionale prima della chiusura delle iscrizioni.",
                default   => "La tua formazione Salute e Sicurezza{$suffisso} scade prima della conclusione del corso. Verifica prima con il tuo delegato territoriale alla formazione e, se necessario, contatta la segreteria di formazione regionale affinché il rinnovo sia registrato su GAIA in tempo.",
            };
        }

        foreach ($stato['propedeutici'] as $codice => $s) {
            if ($s === 'ok') {
                continue;
            }

            $nome = CatalogoCorso::where('codice', strtoupper($codice))->value('nome') ?? $codice;

            $avvisi[] = match ($s) {
                'assente' => "Il prerequisito \"{$nome}\" non ci risulta caricato correttamente su GAIA. Verifica prima con il tuo delegato territoriale alla formazione e, se necessario, contatta la segreteria di formazione regionale prima della chiusura delle iscrizioni; se lo stai ancora frequentando, assicurati che si concluda in tempo.",
                default   => "Il prerequisito \"{$nome}\" risulta scaduto o scade prima della chiusura delle iscrizioni su GAIA. Verifica prima con il tuo delegato territoriale alla formazione e, se necessario, contatta la segreteria di formazione regionale prima della chiusura.",
            };
        }

        if (! empty($avvisi)) {
            $avvisi[] = 'In caso contrario la candidatura sarà a rischio in fase di calcolo della graduatoria.';
        }

        return $avvisi;
    }

    /**
     * Colore aggregato per la colonna "Prerequisiti" in tabella Candidati: null se non
     * c'è un socio da verificare o se la graduatoria non ha requisiti configurati.
     */
    public function coloreRequisiti(?Socio $socio, ?GraduatoriaCandidato $candidato = null): ?string
    {
        $haRequisitiSocio = $this->richiede_corso_sicurezza || filled($this->qualifiche_propedeutiche);
        if ((! $socio || ! $haRequisitiSocio) && ! $this->richiede_diploma && ! $this->richiede_autocertificazione) {
            return null;
        }

        $stato = $this->statoRequisiti($socio, $candidato);
        $tutti = array_filter(array_merge([$stato['sicurezza'], $stato['diploma'], $stato['autocertificazione']], array_values($stato['propedeutici'])));

        if (in_array('assente', $tutti, true) || in_array('non_verificato', $tutti, true)) return 'danger';
        if (array_intersect(['scaduta', 'in_scadenza_entro_limite'], $tutti)) return 'warning';
        return 'success';
    }

    /** Riepilogo breve (tooltip admin) delle sole voci non a posto. */
    public function riepilogoProblemiRequisiti(?Socio $socio, ?GraduatoriaCandidato $candidato = null): ?string
    {
        if (! $socio && ! $this->richiede_diploma && ! $this->richiede_autocertificazione) {
            return null;
        }

        $stato   = $this->statoRequisiti($socio, $candidato);
        $etichette = ['scaduta' => 'scaduta', 'in_scadenza_entro_limite' => 'in scadenza', 'assente' => 'assente', 'non_verificato' => 'non verificato'];
        $righe = [];

        if ($stato['sicurezza'] !== null && $stato['sicurezza'] !== 'ok') {
            $righe[] = 'Sicurezza: ' . $etichette[$stato['sicurezza']];
        }

        foreach ($stato['propedeutici'] as $codice => $s) {
            if ($s !== 'ok') {
                $righe[] = "{$codice}: {$etichette[$s]}";
            }
        }

        if ($stato['diploma'] !== null && $stato['diploma'] !== 'ok') {
            $righe[] = 'Titolo di studio: ' . $etichette[$stato['diploma']];
        }

        if ($stato['autocertificazione'] !== null && $stato['autocertificazione'] !== 'ok') {
            $righe[] = 'Autocertificazione: ' . $etichette[$stato['autocertificazione']];
        }

        return $righe ? implode(' · ', $righe) : null;
    }

    /**
     * Calcola punteggio, ammissibilità e classifica per tutti i candidati confermati
     * (non ritirati) di questa graduatoria, applicando i "Criteri di selezione"
     * configurati, e persiste il risultato su ciascun GraduatoriaCandidato. Sovrascrive
     * qualsiasi calcolo precedente. Ritorna un riepilogo per la notifica in pannello.
     *
     * Chi risulta non ammissibile (manca un requisito obbligatorio: sicurezza o
     * propedeutica) ottiene comunque un punteggio calcolato (per trasparenza), ma
     * nessuna posizione in classifica — va mostrato nella griglia "Candidati esclusi"
     * separata, non in quella principale.
     *
     * @return array{totale:int, ammissibili:int, ammessi:int, esclusi:int}
     */
    public function calcolaClassifica(): array
    {
        $candidati = $this->candidati()
            ->where('stato', 'confermato')
            ->whereNull('ritirato_at')
            ->with(['socio.titoliStudioVerificati', 'punteggiManuali'])
            ->get();

        $righePreferenziali  = $this->qualifichePreferenziali;
        $catalogoRiferimento = $this->catalogoCorsoDiversifica;

        $risultati = $candidati->map(function (GraduatoriaCandidato $candidato) use ($righePreferenziali, $catalogoRiferimento) {
            $socio            = $candidato->socio;
            $ammissibile      = true;
            $dettaglio        = [];
            $punteggio        = 0.0;
            $prioritaAssoluta = false;

            if (! $socio) {
                $ammissibile = false;
            } else {
                $statoReq = $this->statoRequisiti($socio);

                if ($statoReq['sicurezza'] !== null && $statoReq['sicurezza'] !== 'ok') {
                    $ammissibile = false;
                }
                foreach ($statoReq['propedeutici'] as $s) {
                    if ($s !== 'ok') {
                        $ammissibile = false;
                        break;
                    }
                }

                foreach ($righePreferenziali as $riga) {
                    $codici = $riga->codici_corso ?? [];
                    if (collect($codici)->contains(fn ($c) => $this->possiedeQualifica($socio, $c))) {
                        $punteggio  += (float) $riga->punteggio;
                        $dettaglio[] = 'Qualifiche preferenziali (' . implode(', ', $codici) . "): +{$riga->punteggio}";
                    }
                }

                if ($this->preferenza_diversifica_comitato && $catalogoRiferimento
                    && $this->diversificaComitato($catalogoRiferimento, $socio)) {
                    if ($this->punteggio_diversifica_comitato !== null) {
                        $punteggio  += (float) $this->punteggio_diversifica_comitato;
                        $dettaglio[] = "Assenza di figure formate nel comitato: +{$this->punteggio_diversifica_comitato}";
                    } else {
                        $prioritaAssoluta = true;
                        $dettaglio[]      = 'Assenza di figure formate nel comitato: priorità assoluta';
                    }
                }
            }

            if ($this->richiede_attestazione_presidente
                && $candidato->attestazione_presidente_verificata
                && $this->punteggio_attestazione_presidente !== null) {
                $punteggio  += (float) $this->punteggio_attestazione_presidente;
                $dettaglio[] = "Attestazione presidente verificata: +{$this->punteggio_attestazione_presidente}";
            }

            // Titolo di studio: requisito bloccante (non un bonus). Verificato per questa
            // candidatura, oppure già verificato in passato per il socio (permanente, vedi
            // GraduatoriaCandidato::diplomaVerificatoAt()) — va comunque controllato fuori
            // dal blocco socio come l'attestazione sopra, perché resta valutabile anche
            // senza un socio agganciato.
            if ($this->richiede_diploma && ! $candidato->diplomaVerificatoAt()) {
                $ammissibile = false;
                $dettaglio[] = 'Titolo di studio superiore non verificato';
            }

            // Autocertificazione attività: requisito bloccante come il diploma, ma con
            // documento reale caricato (come l'attestazione presidente) invece di una
            // spunta autodichiarata — valutabile anche senza socio agganciato.
            if ($this->richiede_autocertificazione && ! $candidato->autocertificazione_verificata) {
                $ammissibile = false;
                $dettaglio[] = 'Autocertificazione attività non verificata';
            }

            // Aggiustamenti manuali (+/-) inseriti da un operatore per fattori non
            // verificabili automaticamente — permanenti, si riapplicano ad ogni
            // ricalcolo, indipendentemente dall'ammissibilità (trasparenza).
            foreach ($candidato->punteggiManuali as $manuale) {
                $punteggio  += (float) $manuale->valore;
                $segno       = $manuale->valore >= 0 ? '+' : '';
                $dettaglio[] = "Punteggio manuale ({$manuale->motivo}): {$segno}{$manuale->valore}";
            }

            return compact('candidato', 'socio', 'punteggio', 'ammissibile', 'prioritaAssoluta', 'dettaglio');
        });

        $ammissibili = $risultati->filter(fn ($r) => $r['ammissibile'])
            ->sort(function ($a, $b) {
                if ($a['prioritaAssoluta'] !== $b['prioritaAssoluta']) {
                    return $b['prioritaAssoluta'] <=> $a['prioritaAssoluta'];
                }
                if ($a['punteggio'] !== $b['punteggio']) {
                    return $b['punteggio'] <=> $a['punteggio'];
                }
                if ($this->priorita_eta_a_parita_punteggio) {
                    $etaA = $a['socio']?->data_nascita?->age;
                    $etaB = $b['socio']?->data_nascita?->age;
                    if ($etaA !== null && $etaB !== null && $etaA !== $etaB) {
                        return $etaA <=> $etaB;
                    }
                }
                return 0;
            })->values();

        $esclusi = $risultati->reject(fn ($r) => $r['ammissibile'])->values();

        // La posizione in classifica deve riflettere l'ORDINE DI SELEZIONE reale, non solo
        // il punteggio: con la rotazione a comitati attiva, chi viene ammesso non è
        // necessariamente "i primi N per punteggio" (un candidato con punteggio più basso
        // può passare prima di uno più alto dello stesso comitato già rappresentato). Senza
        // questo allineamento, "posizione 21 su 20 posti" non coinciderebbe col primo escluso
        // reale. `ordinaConRotazione()` restituisce quindi l'ordine COMPLETO (non troncato ai
        // posti disponibili) da cui si derivano sia le posizioni sia gli ammessi.
        $ordinePosizioni = match (true) {
            $this->selezione_a_rotazione_comitati && $this->rotazione_multi_turno => $this->ordinaConRotazione($ammissibili),
            $this->selezione_a_rotazione_comitati => $this->ordinaConRotazioneSingola($ammissibili),
            default => $ammissibili->pluck('candidato.id')->all(),
        };

        $ammessiIds = $this->posti_disponibili === null
            ? $ordinePosizioni
            : array_slice($ordinePosizioni, 0, $this->posti_disponibili);

        DB::transaction(function () use ($ammissibili, $esclusi, $ordinePosizioni, $ammessiIds) {
            $posizioni = array_flip($ordinePosizioni);

            foreach ($ammissibili as $r) {
                $r['candidato']->update([
                    'punteggio_finale' => round($r['punteggio'], 2),
                    'ammissibile' => true,
                    'ammesso' => in_array($r['candidato']->id, $ammessiIds, true),
                    'posizione_classifica' => $posizioni[$r['candidato']->id] + 1,
                    'dettaglio_punteggio' => $r['dettaglio'],
                    'calcolato_at' => now(),
                ]);
            }
            foreach ($esclusi as $r) {
                $r['candidato']->update([
                    'punteggio_finale' => round($r['punteggio'], 2),
                    'ammissibile' => false,
                    'ammesso' => null,
                    'posizione_classifica' => null,
                    'dettaglio_punteggio' => $r['dettaglio'],
                    'calcolato_at' => now(),
                ]);
            }
        });

        return [
            'totale' => $risultati->count(),
            'ammissibili' => $ammissibili->count(),
            'ammessi' => count($ammessiIds),
            'esclusi' => $esclusi->count(),
        ];
    }

    /** Il socio possiede almeno una riga in qualifiche_soci per il codice indicato (o un suo equivalente)? */
    private function possiedeQualifica(Socio $socio, string $codice): bool
    {
        $corso = CatalogoCorso::where('codice', strtoupper(trim($codice)))->first();
        if (! $corso) {
            return false;
        }

        $ids = CatalogoCorso::idsConEquivalenti($corso->id);

        return QualificaSocio::where('socio_id', $socio->id)->whereIn('catalogo_corso_id', $ids)->exists();
    }

    /**
     * Criterio "diversifica comitato": vero se NESSUN ALTRO socio appartenente
     * direttamente allo stesso comitato locale del candidato (volontari
     * comitato_locale_id + dipendenti comitato_locale_dipendente_id, escluse le
     * estensioni) possiede già la qualifica del corso in oggetto.
     */
    private function diversificaComitato(CatalogoCorso $corso, Socio $socio): bool
    {
        $comitatoId = $socio->comitato_locale_id ?? $socio->comitato_locale_dipendente_id;
        if (! $comitatoId) {
            return false;
        }

        $ids = CatalogoCorso::idsConEquivalenti($corso->id);

        $altriConQualifica = QualificaSocio::whereIn('catalogo_corso_id', $ids)
            ->where('socio_id', '!=', $socio->id)
            ->whereHas('socio', function ($query) use ($comitatoId) {
                $query->where('comitato_locale_id', $comitatoId)
                    ->orWhere('comitato_locale_dipendente_id', $comitatoId);
            })
            ->exists();

        return ! $altriConQualifica;
    }

    /**
     * Ordine a rotazione tra comitati: raggruppa gli ammissibili (già ordinati per
     * punteggio) per comitato diretto del socio, poi ad ogni giro prende il prossimo
     * candidato non ancora selezionato da ciascun gruppo, finché non esaurisce tutti i
     * gruppi. Restituisce l'ordine COMPLETO (non troncato ai posti disponibili): la
     * posizione in classifica di ogni candidato deriva da questo elenco, non dal solo
     * punteggio, altrimenti "posizione N" non coinciderebbe col reale ordine di ammissione.
     *
     * @param  Collection<int, array{candidato: GraduatoriaCandidato, socio: ?Socio}>  $ammissibiliOrdinati
     * @return array<int>
     */
    private function ordinaConRotazione(Collection $ammissibiliOrdinati): array
    {
        $gruppi = $ammissibiliOrdinati
            ->groupBy(fn ($r) => $this->chiaveComitato($r))
            ->map->values();

        $puntatori    = $gruppi->map(fn () => 0);
        $chiaviGruppi = $gruppi->keys()->all();
        $ordine       = [];

        while (true) {
            $presoAlmenoUno = false;

            foreach ($chiaviGruppi as $chiave) {
                $lista = $gruppi[$chiave];
                $idx   = $puntatori[$chiave];

                if ($idx < $lista->count()) {
                    $ordine[]           = $lista[$idx]['candidato']->id;
                    $puntatori[$chiave] = $idx + 1;
                    $presoAlmenoUno     = true;
                }
            }

            if (! $presoAlmenoUno) {
                break;
            }
        }

        return $ordine;
    }

    /** Chiave di raggruppamento comitato condivisa da ordinaConRotazione() e ordinaConRotazioneSingola(). */
    private function chiaveComitato(array $r): string
    {
        $socio = $r['socio'];
        return $socio?->comitato_locale_id
            ?? $socio?->comitato_locale_dipendente_id
            ?? 'candidato_' . $r['candidato']->id;
    }

    /**
     * Rotazione a "singolo turno": garantisce un posto a ciascun comitato solo al primo
     * incontro (rispettando l'ordine di punteggio globale, senza mai promuovere nessuno
     * davanti a punteggi più alti), poi tutti i candidati successivi dello stesso
     * comitato vengono rinviati in coda, mantenendo tra loro il medesimo ordine di
     * punteggio. A differenza di ordinaConRotazione() (multi-turno) qui non si ripete il
     * giro: un comitato "debole" può restare fuori dai posti disponibili.
     *
     * @param  Collection<int, array{candidato: GraduatoriaCandidato, socio: ?Socio}>  $ammissibiliOrdinati
     * @return array<int>
     */
    private function ordinaConRotazioneSingola(Collection $ammissibiliOrdinati): array
    {
        $comitatiVisti = [];
        $primoTurno    = [];
        $rinviati      = [];

        foreach ($ammissibiliOrdinati as $r) {
            $chiave = $this->chiaveComitato($r);
            if (! isset($comitatiVisti[$chiave])) {
                $comitatiVisti[$chiave] = true;
                $primoTurno[] = $r['candidato']->id;
            } else {
                $rinviati[] = $r['candidato']->id;
            }
        }

        return array_merge($primoTurno, $rinviati);
    }
}
