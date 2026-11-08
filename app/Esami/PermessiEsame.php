<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\EsameGruppo;
use App\Models\EsameLaboratorio;
use App\Models\Socio;
use App\Models\TcriSchedaItinere;
use App\Models\TcriSchedaSemaforo;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use Illuminate\Support\Collection;

/**
 * Ruoli e permessi di UN socio su UN esame. Punto unico usato da PWA e pannello;
 * il socio arriva da App\Services\SocioCorrente. Ruoli letti in diretta da corso ed esame, nessuna copia.
 * La versione SQL della stessa regola (lista esami della PWA) è Esame::scopeVisibiliAlSocio():
 * un test verifica che le due coincidano.
 */
class PermessiEsame
{
    public const DIRETTORE = 'direttore';
    public const PRESIDENTE = 'presidente';
    public const DOCENTE_COMMISSIONE = 'docente_commissione';
    public const DOCENTE = 'docente';
    public const DISCENTE = 'discente';

    /** @var list<string> */
    private array $ruoli = [];

    public function __construct(private Esame $esame, private ?int $socioId)
    {
        if (! $socioId) {
            return;
        }
        $corso = $esame->corso;

        $ruoli = [];
        if ($corso?->direttore_socio_id === $socioId) {
            // Il direttore compila le schede come un docente.
            $ruoli[] = self::DIRETTORE;
            $ruoli[] = self::DOCENTE;
        }
        if ($corso?->presidente_socio_id === $socioId) {
            $ruoli[] = self::PRESIDENTE;
        }
        if ($esame->docentiCommissione()->where('socio_id', $socioId)->exists()) {
            $ruoli[] = self::DOCENTE_COMMISSIONE;
        }
        if ($corso?->docenti()->where('soci.id', $socioId)->exists()) {
            $ruoli[] = self::DOCENTE;
        }
        // Iscritti non ritirati; i non ammessi restano discenti.
        if ($esame->discenti()->where('iscrizioni.partecipante_id', $socioId)->exists()) {
            $ruoli[] = self::DISCENTE;
        }

        $this->ruoli = array_values(array_unique($ruoli));
    }

    public static function per(Esame $esame, ?int $socioId): self
    {
        return new self($esame, $socioId);
    }

    /** @return list<string> */
    public function ruoli(): array
    {
        return $this->ruoli;
    }

    public function ha(string $ruolo): bool
    {
        return in_array($ruolo, $this->ruoli, true);
    }

    /** Direttore, presidente o delegato, docente di commissione. */
    public function eCommissario(): bool
    {
        return $this->ha(self::DIRETTORE) || $this->ha(self::PRESIDENTE) || $this->ha(self::DOCENTE_COMMISSIONE);
    }

    public function eDocente(): bool
    {
        return $this->ha(self::DOCENTE);
    }

    public function eDiscente(): bool
    {
        return $this->ha(self::DISCENTE);
    }

    /**
     * L'esame compare al PERSONALE (PWA): un ruolo da docente o commissione e non più in bozza.
     * ⚠️ Il discente non passa mai da qui, nemmeno col test avviato: vedeEsame() è la porta di tutte le pagine
     * delle schede (laboratori, itinere, prova finale…) e un discente non deve nemmeno aprirle. Per lui vale
     * vedeTestComeDiscente() / vedeEsameInPwa().
     */
    public function vedeEsame(): bool
    {
        return ! $this->esame->isBozza() && array_diff($this->ruoli, [self::DISCENTE]) !== [];
    }

    // ---- Test scritto ----

    /** Avvio ONLINE del test attivo per me. Il discente non vede nulla finché il direttore non lo avvia per lui; chi fa il cartaceo non ha niente da fare in app. */
    public function avvioAttivo(): ?TestAvvio
    {
        if (! $this->socioId) {
            return null;
        }

        return TestAvvio::where('esame_id', $this->esame->id)->where('discente_socio_id', $this->socioId)
            ->whereNotNull('avviato_il')->where('modalita', TestAvvio::ONLINE)->first();
    }

    /** Discente AMMESSO (non ritirato, non «non ammesso») con il test avviato online per lui, a esame non in bozza. */
    public function vedeTestComeDiscente(): bool
    {
        return $this->socioId !== null
            && ! $this->esame->isBozza()
            && $this->eDiscente()
            && $this->esame->discentiAmmessi()->where('iscrizioni.partecipante_id', $this->socioId)->exists()
            && $this->avvioAttivo() !== null;
    }

    /** L'esame compare nella PWA: il personale (vedeEsame) oppure il discente avviato. Gemello SQL: Esame::scopeVisibiliAlSocio. */
    public function vedeEsameInPwa(): bool
    {
        return $this->vedeEsame() || $this->vedeTestComeDiscente();
    }

    /** Domande con le risposte corrette e risultati del test scritto, in PWA: solo la commissione. Docenti non in commissione: no. */
    public function vedeTestScritto(): bool
    {
        return $this->vedeEsame() && $this->eCommissario();
    }

    /** Unica regola di LETTURA di una scheda personale: la commissione le vede tutte, un docente solo le proprie. */
    public function vedeScheda(TcriSchedaSemaforo|TcriSchedaItinere $scheda): bool
    {
        return $this->vedeEsame() && $this->delMioEsame($scheda) && ($this->eCommissario() || $this->eAutore($scheda));
    }

    /** Solo l'autore e solo a esame in corso: in bozza e dopo la chiusura nessuno. */
    public function modificaScheda(TcriSchedaSemaforo|TcriSchedaItinere $scheda): bool
    {
        return $this->esame->stato === Esame::STATO_IN_CORSO && $this->delMioEsame($scheda) && $this->eAutore($scheda);
    }

    /**
     * Consegne e punteggi del test: solo la commissione. Il discente non vede mai il risultato,
     * nemmeno il proprio: invia le risposte e il test per lui è concluso (decisione 18/10).
     */
    public function vedeConsegnaTest(TestConsegna $consegna): bool
    {
        return $this->eCommissario() && $this->vedeEsame() && $consegna->esame_id === $this->esame->id;
    }

    // ---- Schede dei laboratori: ogni docente lavora solo sui discenti del proprio gruppo ----

    /** Gruppi del laboratorio in cui il socio è assegnato come docente (il presidente non è mai assegnato). */
    public function gruppiMiei(EsameLaboratorio $lab): Collection
    {
        if (! $this->socioId || $lab->esame_id !== $this->esame->id) {
            return collect();
        }

        return EsameGruppo::query()
            ->where('laboratorio_id', $lab->id)
            ->whereHas('docenti', fn ($q) => $q->where('soci.id', $this->socioId))
            ->orderBy('nome')
            ->get();
    }

    /**
     * Discenti su cui il socio lavora nel laboratorio: quelli dei suoi gruppi di adesso più quelli per cui ha già
     * una propria scheda (il gruppo può essere cambiato). Mai i ritirati. Il "non ammesso" qui non conta: vale
     * solo per la prova finale. Vuoto per chi non è docente (o direttore).
     *
     * @return Collection<int, Socio>
     */
    public function discentiProponibili(EsameLaboratorio $lab): Collection
    {
        if (! $this->eDocente() || $lab->esame_id !== $this->esame->id) {
            return collect();
        }

        $inGruppo = \Illuminate\Support\Facades\DB::table('esame_gruppo_discente')
            ->join('esame_gruppi', 'esame_gruppi.id', '=', 'esame_gruppo_discente.gruppo_id')
            ->join('esame_gruppo_docente', 'esame_gruppo_docente.gruppo_id', '=', 'esame_gruppi.id')
            ->where('esame_gruppi.laboratorio_id', $lab->id)
            ->where('esame_gruppo_docente.socio_id', $this->socioId)
            ->pluck('esame_gruppo_discente.socio_id');
        $conScheda = $lab->schedeSemaforo()->where('docente_socio_id', $this->socioId)->pluck('discente_socio_id');
        $attivi = $this->esame->discenti()->pluck('iscrizioni.partecipante_id');

        $ids = $inGruppo->merge($conScheda)->unique()->intersect($attivi)->values();

        return Socio::query()->whereIn('id', $ids)->orderBy('cognome')->orderBy('nome')->get();
    }

    /** Può aprire (anche in sola lettura) la scheda del laboratorio per questo discente. */
    public function vedeSchedaLab(EsameLaboratorio $lab, int $discenteId): bool
    {
        return $this->vedeEsame() && $this->discentiProponibili($lab)->contains('id', $discenteId);
    }

    /**
     * Confronto tra docenti: chi vede la scheda di quel discente in quel laboratorio e ha INVIATO la
     * propria legge le schede inviate degli altri docenti (voci, mai le annotazioni). Deroga voluta alla regola «il docente
     * semplice non vede le schede altrui», solo per i laboratori e solo dopo il proprio invio.
     */
    public function vedeConfrontoLab(EsameLaboratorio $lab, int $discenteId): bool
    {
        return $this->vedeSchedaLab($lab, $discenteId)
            && $lab->schedeSemaforo()->where('discente_socio_id', $discenteId)
                ->where('docente_socio_id', $this->socioId)->whereNotNull('inviata_il')->exists();
    }

    /** Può compilarla: come sopra e solo a esame in corso (bozza e chiuso: mai, 4.4). */
    public function puoCompilareLab(EsameLaboratorio $lab, int $discenteId): bool
    {
        return $this->esame->stato === Esame::STATO_IN_CORSO && $this->vedeSchedaLab($lab, $discenteId);
    }

    // ---- Schede itinere: tutti i docenti, per tutti i discenti non ritirati, senza gruppi ----

    /**
     * Discenti per cui il socio compila l'itinere: tutti i non ritirati dell'esame, se è docente (direttore compreso).
     * Il "non ammesso" non conta: vale solo per la prova finale. Vuoto per presidente/delegato e discenti.
     *
     * @return Collection<int, Socio>
     */
    public function discentiItinere(): Collection
    {
        if (! $this->eDocente()) {
            return collect();
        }
        $attivi = $this->esame->discenti()->pluck('iscrizioni.partecipante_id');

        return Socio::query()->whereIn('id', $attivi)->orderBy('cognome')->orderBy('nome')->get();
    }

    /**
     * Il discente è nel MIO elenco di compilazione itinere (la pagina con la mia scheda). NON è una regola di lettura
     * delle schede altrui: quella è vedeScheda() e vedeRiepilogoItinere().
     */
    public function inElencoItinere(int $discenteId): bool
    {
        return $this->vedeEsame() && $this->discentiItinere()->contains('id', $discenteId);
    }

    /** Può compilarla: come sopra e solo a esame in corso (bozza e chiuso: mai, 4.4). */
    public function puoCompilareItinere(int $discenteId): bool
    {
        return $this->esame->stato === Esame::STATO_IN_CORSO && $this->inElencoItinere($discenteId);
    }

    // ---- Riepilogo itinere e scheda finale d'area ----

    /** Riepilogo di tutte le schede personali + finali d'area: sola lettura per tutta la commissione. */
    public function vedeRiepilogoItinere(): bool
    {
        return $this->vedeEsame() && $this->eCommissario();
    }

    /** La finale d'area la decide solo il direttore, a esame in corso (chiuso: sola lettura). */
    public function puoDecidereItinere(): bool
    {
        return $this->ha(self::DIRETTORE) && $this->esame->stato === Esame::STATO_IN_CORSO;
    }

    // ---- Colloquio e prova pratica: schede PERSONALI dalla PWA, solo direttore e docenti di commissione ----

    /** Compilano le schede personali di colloquio e prova pratica: direttore e docenti di commissione. MAI il presidente o delegato. */
    public function eCompilatoreProvaFinale(): bool
    {
        return $this->ha(self::DIRETTORE) || $this->ha(self::DOCENTE_COMMISSIONE);
    }

    /** Interruttore «Apri la prova finale»: aperto = si compila e si modifica; chiuso = le schede personali restano visibili, in sola lettura. */
    public function provaFinaleAperta(): bool
    {
        return $this->esame->provaFinaleAperta();
    }

    /** Il socio ha già almeno una propria scheda personale di colloquio o prova pratica (resta consultabile a prova chiusa). */
    public function haSchedeProvaPersonali(): bool
    {
        if (! $this->socioId) {
            return false;
        }

        return $this->esame->colloqui()->where('tipo', 'personale')->where('membro_socio_id', $this->socioId)->exists()
            || $this->esame->provePratiche()->where('tipo', 'personale')->where('membro_socio_id', $this->socioId)->exists();
    }

    /** Entra nelle pagine personali: compilatore, a prova aperta oppure (sola lettura) se ha già delle proprie schede. */
    public function accedeProvaPersonale(): bool
    {
        return $this->vedeEsame() && $this->eCompilatoreProvaFinale() && ($this->provaFinaleAperta() || $this->haSchedeProvaPersonali());
    }

    /**
     * Discenti della mia lista di colloquio e prova pratica: ammessi alla prova finale (non ritirati, non «non ammessi»).
     * Vuoto per chi non può accedere. A prova chiusa la lista c'è, ma le schede sono in sola lettura.
     *
     * @return Collection<int, Socio>
     */
    public function discentiProvaFinale(): Collection
    {
        if (! $this->accedeProvaPersonale()) {
            return collect();
        }
        $ammessi = $this->esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id');

        return Socio::query()->whereIn('id', $ammessi)->orderBy('cognome')->orderBy('nome')->get();
    }

    public function inElencoProvaFinale(int $discenteId): bool
    {
        return $this->discentiProvaFinale()->contains('id', $discenteId);
    }

    /** Può compilare/modificare la propria scheda personale: nell'elenco, a esame in corso E prova finale aperta. */
    public function puoCompilareProvaPersonale(int $discenteId): bool
    {
        return $this->esame->stato === Esame::STATO_IN_CORSO && $this->provaFinaleAperta() && $this->inElencoProvaFinale($discenteId);
    }

    /** Riepilogo della prova finale (finali e personali; le personali sono in sola lettura a prova chiusa): sola lettura per tutta la commissione. */
    public function vedeRiepilogoProvaFinale(): bool
    {
        return $this->vedeRiepilogoItinere();
    }

    /** Le schede personali di colloquio e prova pratica le legge tutta la commissione, anche a prova finale chiusa. */
    public function vedeSchedeProvaPersonali(): bool
    {
        return $this->vedeRiepilogoProvaFinale();
    }

    private function eAutore(TcriSchedaSemaforo|TcriSchedaItinere $scheda): bool
    {
        return $this->socioId !== null && $scheda->docente_socio_id === $this->socioId;
    }

    /** La scheda semaforo appartiene all'esame tramite il laboratorio, quella itinere direttamente. */
    private function delMioEsame(TcriSchedaSemaforo|TcriSchedaItinere $scheda): bool
    {
        $esameId = $scheda instanceof TcriSchedaSemaforo ? $scheda->laboratorio?->esame_id : $scheda->esame_id;

        return $esameId === $this->esame->id;
    }
}
