<?php

namespace App\Models;

use App\Services\SocioCorrente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Esame collegato a un corso (uno solo per corso, vincolo univoco su corso_id).
 *
 * Ambito territoriale (comitato) NON duplicato: si eredita da corso → evento → comitato_*.
 * Direttore, presidente o delegato e docenti vivono sul corso; qui solo i docenti
 * di commissione. I discenti si leggono in diretta dalle iscrizioni del corso (nessuna copia).
 */
class Esame extends Model
{
    protected $table = 'esami';

    protected $fillable = [
        'corso_id',
        'titolo',
        'tipo_codice',
        'modello_versione',
        'modello_snapshot',
        'lap',
        'stato',
        'test_penalita_attive',
        'test_mescola',
        'test_punti_corretta',
        'test_penalita_errata',
        'test_penalita_vuota',
        'prova_finale_aperta_il',
        'chiuso_il',
        'chiuso_da',
        'riaperto_da',
        'riaperto_il',
        'riaperto_motivo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'modello_snapshot' => 'array',
            'lap'              => 'decimal:2',
            'test_penalita_attive' => 'boolean',
            'test_mescola'         => 'boolean',
            'test_punti_corretta'  => 'decimal:1',
            'test_penalita_errata' => 'decimal:1',
            'test_penalita_vuota'  => 'decimal:1',
            'prova_finale_aperta_il' => 'datetime',
            'chiuso_il'        => 'datetime',
            'riaperto_il'      => 'datetime',
        ];
    }

    public const STATO_BOZZA = 'bozza';
    public const STATO_IN_CORSO = 'in_corso';
    public const STATO_CHIUSO = 'chiuso'; // App\Esami\ChiusuraEsame (chiudi / riapri)

    /** Dopo la chiusura l'esame si può riaprire (solo superadmin, con motivo) entro questi giorni di calendario; poi mai più. */
    public const RIAPERTURA_GIORNI = 15;

    /**
     * Esami visibili all'utente (punto unico: Resource, pagine e test). I criteri si sommano:
     * superadmin tutto; admin regionale / locale l'ambito del corso (stesso filtro di CorsoResource);
     * qualsiasi utente con socio_id = direttore del corso i corsi che dirige. Gli altri nessuno.
     * ⚠️ Un operatore assegnato a un evento NON vede gli esami (solo se direttore).
     */
    public function scopeVisibiliA(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isSuperadmin()) {
            return $query;
        }

        $socioId = SocioCorrente::perUtente($user);

        return $query->where(function (Builder $q) use ($user, $socioId) {
            $q->whereRaw('1 = 0');
            if ($socioId) {
                $q->orWhereHas('corso', fn ($c) => $c->where('direttore_socio_id', $socioId));
            }
            if ($user->isAdmin()) {
                $q->orWhereHas('corso.evento', fn ($e) => $e->where('comitato_regionale_id', $user->comitato_regionale_id));
            } elseif ($user->isAdminLocale()) {
                $q->orWhereHas('corso.evento', fn ($e) => $e->where('comitato_locale_id', $user->comitato_locale_id));
            }
        });
    }

    /**
     * Esami che l'utente del pannello può LEGGERE (riepilogo itinere): quelli che gestisce (visibiliA)
     * più quelli in cui il suo socio è presidente/delegato o docente di commissione (esame non in bozza).
     * ⚠️ Superset di visibiliA: «chi gestisce» resta visibiliA/canEdit, la lettura è solo questo scope.
     */
    public function scopeLeggibiliA(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isSuperadmin()) {
            return $query;
        }

        $socioId = SocioCorrente::perUtente($user);

        return $query->where(function (Builder $q) use ($user, $socioId) {
            $q->whereIn('esami.id', static::query()->visibiliA($user)->select('esami.id'));
            if ($socioId) {
                $q->orWhere(function (Builder $c) use ($socioId) {
                    $c->where('stato', '!=', self::STATO_BOZZA)
                        ->where(fn (Builder $m) => $m
                            ->whereHas('corso', fn ($k) => $k->where('presidente_socio_id', $socioId))
                            ->orWhereHas('docentiCommissione', fn ($d) => $d->where('socio_id', $socioId)));
                });
            }
        });
    }

    public function isDirettore(?User $user): bool
    {
        $socioId = SocioCorrente::perUtente($user);

        return $socioId && $this->corso?->direttore_socio_id === $socioId;
    }

    public function puoVedereRiepilogoItinere(?User $user): bool
    {
        return $this->modello_snapshot !== null
            && static::query()->leggibiliA($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Unica regola per inserire una scheda FINALE (finale d'area, colloquio, prova pratica): solo il direttore
     * (nemmeno il superadmin) e solo a esame in corso. Nessuna dipendenza dall'interruttore «Apri la prova finale».
     */
    public function puoInserireFinali(?User $user): bool
    {
        return $this->stato === self::STATO_IN_CORSO && $this->modello_snapshot !== null && (bool) $this->isDirettore($user);
    }

    public function puoDecidereItinere(?User $user): bool
    {
        return $this->puoInserireFinali($user);
    }

    /** Il riepilogo della prova finale (colloquio e prova pratica) lo legge chi legge l'Itinere: stessa regola. */
    public function puoVedereProvaFinale(?User $user): bool
    {
        return $this->puoVedereRiepilogoItinere($user);
    }

    // ---- «Apri la prova finale»: interruttore delle sole schede PERSONALI di colloquio e prova pratica ----

    public function provaFinaleAperta(): bool
    {
        return $this->prova_finale_aperta_il !== null;
    }

    /** Lo aziona chi gestisce l'esame (direttore, admin dell'ambito, superadmin: stessa regola di «Attiva esame»), a esame in corso. */
    public function puoGestireProvaFinale(?User $user): bool
    {
        return $this->stato === self::STATO_IN_CORSO && $this->modello_snapshot !== null
            && static::query()->visibiliA($user)->whereKey($this->getKey())->exists();
    }

    /** Motivo per cui l'interruttore non si può azionare, null se si può (tooltip). */
    public function motivoNonAzionabileProvaFinale(): ?string
    {
        if ($this->isChiuso()) {
            return "L'esame è chiuso.";
        }
        if ($this->stato !== self::STATO_IN_CORSO) {
            return "L'esame non è ancora attivo.";
        }

        return null;
    }

    public function apriProvaFinale(): void
    {
        if ($motivo = $this->motivoNonAzionabileProvaFinale()) {
            throw new \RuntimeException($motivo);
        }
        $this->prova_finale_aperta_il ??= now();
        $this->save();
    }

    public function chiudiProvaFinale(): void
    {
        if ($motivo = $this->motivoNonAzionabileProvaFinale()) {
            throw new \RuntimeException($motivo);
        }
        $this->update(['prova_finale_aperta_il' => null]);
    }

    /**
     * Esami che il socio vede nella PWA: ha un ruolo (direttore, presidente, docente di commissione,
     * docente del corso) e l'esame non è in bozza, oppure è un discente ammesso per cui il test scritto è stato
     * avviato.
     * ⚠️ Stessa regola di App\Esami\PermessiEsame::vedeEsameInPwa() in SQL: cambiarle insieme (test EsamePermessiTest).
     */
    public function scopeVisibiliAlSocio(Builder $query, ?int $socioId): Builder
    {
        if (! $socioId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('stato', '!=', self::STATO_BOZZA)
            ->where(function (Builder $q) use ($socioId) {
                $q->whereHas('corso', fn ($c) => $c->where('direttore_socio_id', $socioId)
                    ->orWhere('presidente_socio_id', $socioId)
                    ->orWhereHas('docenti', fn ($d) => $d->where('soci.id', $socioId)))
                    ->orWhereHas('docentiCommissione', fn ($d) => $d->where('socio_id', $socioId))
                    ->orWhere(fn (Builder $d) => $d
                        ->whereHas('testAvvii', fn ($a) => $a->where('discente_socio_id', $socioId)->whereNotNull('avviato_il')->where('modalita', TestAvvio::ONLINE))
                        ->whereHas('discentiAmmessi', fn ($i) => $i->where('iscrizioni.partecipante_id', $socioId)));
            });
    }

    public function isChiuso(): bool
    {
        return $this->stato === self::STATO_CHIUSO;
    }

    /** True se esistono dati di valutazione/test/esiti: l'esame allora non si cancella. */
    public function hasDatiCorrelati(): bool
    {
        return TcriSchedaSemaforo::whereIn('laboratorio_id', $this->laboratori()->select('id'))->exists()
            || $this->schedeItinere()->exists()
            || $this->decisioniItinere()->exists()
            || $this->colloqui()->exists()
            || $this->provePratiche()->exists()
            || $this->testDomande()->exists()
            || $this->testAvvii()->exists()
            || $this->testConsegne()->exists()
            || $this->esiti()->exists();
    }

    public function isBozza(): bool
    {
        return $this->stato === self::STATO_BOZZA;
    }

    /** Motivo per cui l'esame non si può attivare, null se si può. */
    public function motivoNonAttivabile(): ?string
    {
        if (! $this->isBozza()) {
            return "L'esame è già attivo.";
        }
        if (! $this->corso?->direttore_socio_id) {
            return 'Il corso non ha un direttore collegato a un socio.';
        }
        if (! $this->laboratori()->exists()) {
            return 'Serve almeno un laboratorio.';
        }

        return null;
    }

    /** bozza → in_corso: copia il modello in modello_snapshot (si congela solo il modello). */
    public function attiva(): void
    {
        if ($motivo = $this->motivoNonAttivabile()) {
            throw new \RuntimeException($motivo);
        }

        \Illuminate\Support\Facades\DB::transaction(function () {
            $modello = $this->modello()->firstOrFail();
            $this->update([
                'modello_snapshot' => $modello->struttura,
                'stato'            => self::STATO_IN_CORSO,
            ]);
        });
    }

    /**
     * Motivo per cui l'esame non si può rimettere in bozza, null se si può (errore di attivazione).
     * Solo finché nessuna scheda/test/esito esiste: stessa soglia dell'eliminazione.
     */
    public function motivoNonRiportabileInBozza(): ?string
    {
        if ($this->stato !== self::STATO_IN_CORSO) {
            return "L'esame è già in bozza o è chiuso.";
        }
        if ($this->hasDatiCorrelati()) {
            return 'Ci sono già schede, test o esiti compilati: non si può tornare in bozza.';
        }

        return null;
    }

    /** in_corso → bozza: scarta lo snapshot del modello (si ricopia alla prossima attivazione). */
    public function riportaInBozza(): void
    {
        if ($motivo = $this->motivoNonRiportabileInBozza()) {
            throw new \RuntimeException($motivo);
        }

        $this->update(['stato' => self::STATO_BOZZA, 'modello_snapshot' => null, 'prova_finale_aperta_il' => null]);
    }

    /**
     * Crea l'esame per un corso: tipo dal catalogo collegato, ultima versione del modello, Laboratorio 1.
     * Lancia se manca direttore/tipo o il tipo non ha un modello.
     */
    public static function creaPerCorso(Corso $corso, string $titolo, string|int|float $lap): self
    {
        if ($motivo = self::motivoNonCreabile($corso)) {
            throw new \RuntimeException($motivo);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($corso, $titolo, $lap) {
            $tipo    = $corso->catalogoCorso->codice;
            $modello = EsameModello::where('tipo_codice', $tipo)->orderByDesc('versione')->firstOrFail();

            $esame = self::create([
                'corso_id'         => $corso->id,
                'titolo'           => $titolo,
                'tipo_codice'      => $tipo,
                'modello_versione' => $modello->versione,
                'lap'              => $lap,
                'stato'            => self::STATO_BOZZA,
                'created_by'       => auth()->id(),
                'updated_by'       => auth()->id(),
            ]);
            $esame->laboratori()->create(['numero' => 1]);

            return $esame;
        });
    }

    /** Motivo per cui dal corso non si può creare l'esame, null se si può. */
    public static function motivoNonCreabile(Corso $corso): ?string
    {
        if ($corso->esame()->exists()) {
            return 'Il corso ha già un esame.';
        }
        if (! $corso->direttore_socio_id) {
            return 'Il corso non ha un direttore scelto tra i soci: modifica il corso per collegarlo.';
        }
        if (! $corso->catalogo_corso_id) {
            return 'Il corso non ha il tipo di corso (catalogo) collegato: modifica il corso per sceglierlo.';
        }
        $tipo = $corso->catalogoCorso?->codice;
        if (! $tipo || ! EsameModello::where('tipo_codice', $tipo)->exists()) {
            return "Per il tipo di corso scelto non esiste ancora un modello d'esame (oggi solo TCRI).";
        }

        return null;
    }

    /** Discenti: iscrizioni non ritirate del corso, lette in diretta (nessuna copia). */
    public function discenti(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Iscrizione::class, Corso::class, 'id', 'corso_id', 'corso_id', 'id')
            ->where('iscrizioni.stato', '!=', 'ritirato');
    }

    /**
     * Discenti ammessi alla prova finale: non ritirati e non «non ammessi». Unica fonte per gli
     * elenchi di colloquio e prova pratica (schede personali, finali, riepilogo, completezza). I dati di un discente
     * segnato non ammesso dopo la compilazione restano sul DB ma non compaiono.
     */
    public function discentiAmmessi(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->discenti()->where('iscrizioni.non_ammesso', false);
    }

    public function corso(): BelongsTo
    {
        return $this->belongsTo(Corso::class);
    }

    public function modello(): BelongsTo
    {
        return $this->belongsTo(EsameModello::class, 'modello_versione', 'versione')
            ->where('tipo_codice', $this->tipo_codice);
    }

    public function chiusoDa(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'chiuso_da');
    }

    public function riapertoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'riaperto_da');
    }

    public function docentiCommissione(): HasMany
    {
        return $this->hasMany(EsameDocenteCommissione::class);
    }

    public function gruppi(): HasMany
    {
        return $this->hasMany(EsameGruppo::class);
    }

    public function laboratori(): HasMany
    {
        return $this->hasMany(EsameLaboratorio::class);
    }

    public function tutoraggi(): HasMany
    {
        return $this->hasMany(EsameTutoraggio::class);
    }

    public function schedeItinere(): HasMany
    {
        return $this->hasMany(TcriSchedaItinere::class);
    }

    public function decisioniItinere(): HasMany
    {
        return $this->hasMany(TcriItinereDecisione::class);
    }

    public function colloqui(): HasMany
    {
        return $this->hasMany(TcriColloquio::class);
    }

    public function provePratiche(): HasMany
    {
        return $this->hasMany(TcriProvaPratica::class);
    }

    public function testDomande(): HasMany
    {
        return $this->hasMany(TestDomanda::class);
    }

    public function testConsegne(): HasMany
    {
        return $this->hasMany(TestConsegna::class);
    }

    public function testAvvii(): HasMany
    {
        return $this->hasMany(TestAvvio::class);
    }

    // ---- Test scritto: stato derivato e regole del pannello ----

    public const TEST_BOZZA = 'bozza';
    public const TEST_AVVIATO = 'avviato';
    public const TEST_CON_CONSEGNE = 'con_consegne';

    /** Stato derivato (nessuna colonna): bozza = nessun avvio attivo; con consegne = almeno un test Inviato. */
    public function statoTest(): string
    {
        if ($this->testConsegne()->whereNotNull('consegnato_il')->exists()) {
            return self::TEST_CON_CONSEGNE;
        }

        return $this->testAvvii()->whereNotNull('avviato_il')->exists() ? self::TEST_AVVIATO : self::TEST_BOZZA;
    }

    /**
     * Da chiamare DENTRO la transazione di ogni scrittura che alimenta il calcolo (finali, invio del test): rilegge l'esame
     * con FOR SHARE, così non può scrivere «a cavallo» di ChiusuraEsame::chiudi (che prende FOR UPDATE). Lancia se non è in corso.
     */
    public static function verificaInCorsoConBlocco(int $esameId): void
    {
        $stato = static::query()->sharedLock()->whereKey($esameId)->value('stato');
        if ($stato !== self::STATO_IN_CORSO) {
            throw new \RuntimeException("L'esame non è in corso: sola lettura.");
        }
    }

    /** Ultimo istante utile per riaprire (fine del 15° giorno di calendario dopo la chiusura), null se non chiuso. */
    public function riapribileFinoAl(): ?\Illuminate\Support\Carbon
    {
        return $this->chiuso_il?->copy()->addDays(self::RIAPERTURA_GIORNI)->endOfDay();
    }

    /**
     * Commissione, modifica base, laboratori, gruppi: chi gestisce l'esame, solo finché non è chiuso.
     * Unica regola per `visible()`/tooltip E per i controlli server-side delle azioni.
     */
    public function puoModificareConfigurazione(?User $user): bool
    {
        return $this->esamePreparabile() && $this->eGestore($user);
    }

    /** La commissione la impostano gli admin dell'ambito e il superadmin, NON il direttore. */
    public function puoImpostareCommissione(?User $user): bool
    {
        if (! $user || ! $this->puoModificareConfigurazione($user)) {
            return false;
        }
        if ($user->isSuperadmin()) {
            return true;
        }
        $evento = $this->corso?->evento;

        return ($user->isAdmin() && $evento?->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento?->comitato_locale_id === $user->comitato_locale_id);
    }

    /** Chi gestisce l'esame (direttore, admin dell'ambito, superadmin): la stessa regola di «Attiva esame» e «Apri la prova finale». */
    private function eGestore(?User $user): bool
    {
        return static::query()->visibiliA($user)->whereKey($this->getKey())->exists();
    }

    private function esamePreparabile(): bool
    {
        return in_array($this->stato, [self::STATO_BOZZA, self::STATO_IN_CORSO], true);
    }

    /** Domande e configurazione del punteggio: gestori, esame non chiuso, test ancora in bozza. */
    public function puoPreparareTest(?User $user): bool
    {
        return $this->esamePreparabile() && $this->statoTest() === self::TEST_BOZZA && $this->eGestore($user);
    }

    /** Dal primo avvio restano ammesse solo le correzioni di refusi (testo di domande e opzioni). */
    public function puoCorreggereRefusiTest(?User $user): bool
    {
        return $this->esamePreparabile() && $this->statoTest() !== self::TEST_BOZZA && $this->eGestore($user);
    }

    /** Il PDF cartaceo lo chiede chi gestisce l'esame, in qualsiasi stato: non produce effetti. */
    public function puoScaricareTestCartaceo(?User $user): bool
    {
        return $this->eGestore($user);
    }

    /** Lettura del test scritto (domande con le corrette, risultati): la stessa dell'Itinere. */
    public function puoVedereTestScritto(?User $user): bool
    {
        return $this->puoVedereRiepilogoItinere($user);
    }

    /** Pagina Domande (configurazione): i gestori la aprono anche in bozza, prima dell'attivazione; chi solo legge, come il test scritto. */
    public function puoVedereDomandeTest(?User $user): bool
    {
        return $this->eGestore($user) || $this->puoVedereTestScritto($user);
    }

    /** Avvio, disattivazione, cartaceo e inserimento a mano: solo il direttore (stessa regola delle schede finali), a test valido. */
    public function puoAvviareTest(?User $user): bool
    {
        return $this->puoInserireFinali($user) && $this->motivoTestNonAvviabile() === null;
    }

    /** Motivo per cui il test non si può avviare (tooltip), null se si può. */
    public function motivoTestNonAvviabile(): ?string
    {
        if ($this->isChiuso()) {
            return "L'esame è chiuso.";
        }
        if ($this->stato !== self::STATO_IN_CORSO) {
            return "L'esame non è ancora attivo.";
        }

        return $this->motivoTestIncompleto();
    }

    /** Il test non è pronto: nessuna domanda, domanda senza 4 opzioni compilate, configurazione incoerente. */
    public function motivoTestIncompleto(): ?string
    {
        $domande = $this->testDomande()->get(['id', 'testo', 'opzioni', 'corretta']);
        if ($domande->isEmpty()) {
            return 'Il test non ha domande.';
        }
        foreach ($domande as $d) {
            $opzioni = is_array($d->opzioni) ? $d->opzioni : [];
            if (trim((string) $d->testo) === '' || count($opzioni) !== 4 || collect($opzioni)->contains(fn ($o) => trim((string) $o) === '')) {
                return 'Ogni domanda deve avere il testo e 4 opzioni compilate.';
            }
        }
        if ($this->test_penalita_attive && (float) $this->test_penalita_errata <= 0 && (float) $this->test_penalita_vuota <= 0) {
            return 'Con le penalità attive serve almeno una penalità maggiore di zero.';
        }

        return null;
    }

    public function esiti(): HasMany
    {
        return $this->hasMany(Esito::class);
    }
}
