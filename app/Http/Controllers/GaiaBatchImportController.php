<?php

namespace App\Http\Controllers;

use App\Filament\Resources\SocioResource;
use App\Models\ComitatoLocale;
use App\Models\Graduatoria;
use App\Models\GraduatoriaCandidato;
use App\Models\Socio;
use App\Services\PatenteCriGaiaImport;
use App\Services\QualificaSocioImportWriter;
use App\Services\QualificheSociImportParser;
use App\Services\QualificheSociQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint chiamati dallo userscript "LURCH - GAIA Bridge" (resources/userscripts/) per l'import
 * batch multi-socio delle qualifiche GAIA (a differenza di GaiaCurriculumController che opera su
 * un socio alla volta). Due contenitori possibili — ComitatoLocale (pagina Qualifiche soci,
 * aperto anche ad admin_locale sul proprio comitato dal 2026-08-13) e Graduatoria (pagina
 * Candidati, scoping via Graduatoria::puoGestire()) — con la stessa identica logica di
 * analisi/scrittura (container-agnostica, gated solo da SocioResource::canEditQualifiche() per riga),
 * fattorizzata in eseguiAnalisiRighe()/eseguiImportazioneRighe() e riusata da entrambi: solo
 * l'autorizzazione a monte e la risoluzione della lista soci sono specifiche per contenitore.
 */
class GaiaBatchImportController extends Controller
{
    /**
     * Soglia di ruolo da User::puoUsareGaiaBridge()
     * più lo scoping al comitato specifico: superadmin su tutti, admin
     * (regionale) sui comitati della propria regione, admin_locale SOLO sul proprio comitato
     * esatto (match su id, non basta la regione — a differenza del ramo admin qui sopra).
     */
    private function autorizzaComitato(ComitatoLocale $comitato): bool
    {
        $user = auth()->user();

        if (! $user?->puoUsareGaiaBridge()) {
            return false;
        }
        if ($user->isSuperadmin()) {
            return true;
        }
        if ($user->isAdminLocale()) {
            return $comitato->id === $user->comitato_locale_id;
        }

        return $comitato->comitato_regionale_id === $user->comitato_regionale_id; // admin (regionale)
    }

    /**
     * Stessa soglia di ruolo del comitato, più lo scoping territoriale della graduatoria via
     * Graduatoria::puoGestire() (superadmin su tutte, admin regionale sulla propria regione,
     * admin_locale solo sulle graduatorie con comitato_locale_id valorizzato e uguale al
     * proprio — una graduatoria regionale resta fuori portata per lui, operatore mai idoneo).
     */
    private function autorizzaGraduatoria(Graduatoria $graduatoria): bool
    {
        return (bool) auth()->user()?->puoUsareGaiaBridge() && $graduatoria->puoGestire(auth()->user());
    }

    /**
     * Appartenenza al comitato in una qualsiasi delle tre forme — primaria, estensione o
     * dipendente: usata per la LETTURA (elenco soci, analisi/anteprima). La scrittura è comunque
     * gated per-riga da SocioResource::canEditQualifiche() (vedi eseguiImportazioneRighe()), che
     * dal 2026-08-27 include anche l'estensione — coerente con questa lista.
     */
    private function apparteneAlComitato(Socio $socio, ComitatoLocale $comitatoLocale): bool
    {
        return $socio->comitato_locale_id === $comitatoLocale->id
            || $socio->comitato_locale_estensione_id === $comitatoLocale->id
            || $socio->comitato_locale_dipendente_id === $comitatoLocale->id;
    }

    /**
     * Un solo parser per l'intero batch: buildIndiceCorsi() viene pagato una volta sola
     * (analizzaCorsoSingolo() è già disaccoppiato dall'indice CF costoso, usato solo dal flusso
     * Excel). $sociById è già filtrato/autorizzato dal chiamante (per comitato o per
     * graduatoria) — qui si scartano solo le righe il cui socio_id non è nell'insieme ammesso
     * (payload manomesso).
     *
     * Oltre alle righe analizzate, per ciascun socio del payload aggiunge le eventuali righe
     * sintetiche "rimossa" (QualificheSociImportParser::trovaQualificheRimosse()) — il client
     * manda ogni socio il cui curriculum è stato letto con successo in questo giro, anche con
     * righe: [] (curriculum senza "Qualifica CRI" trovate), proprio per permettere questa
     * riconciliazione anche quando un socio non ha più nessuna qualifica GAIA confermata.
     */
    private function eseguiAnalisiRighe(Collection $sociById, array $payloadSoci): Collection
    {
        $parser = new QualificheSociImportParser();

        return collect($payloadSoci)
            ->filter(fn (array $s) => $sociById->has($s['socio_id']))
            ->flatMap(function (array $s) use ($parser, $sociById) {
                $socio = $sociById[$s['socio_id']];
                $righe = collect($s['righe'])->map(
                    fn (array $r) => $parser->analizzaCorsoSingolo($socio, $r['nome_corso'], $r['dates'])
                );
                $trovatiIds = $righe->pluck('catalogo_corso_id')->filter()->unique();

                // Righe "Patente CRI" del curriculum (stessa forma tipo_riga='patente' del flusso
                // singolo-socio — vedi PatenteCriGaiaImport), incluse nella stessa collezione.
                $patenti = PatenteCriGaiaImport::analizza($socio, $s['patenti'] ?? []);

                return $righe->concat($parser->trovaQualificheRimosse($socio, $trovatiIds))->concat($patenti);
            })
            ->values();
    }

    /**
     * Gate di scrittura per riga: SocioResource::canEditQualifiche(), stesso di
     * GaiaCurriculumController::importa() — container-agnostico, non dipende dal comitato/
     * graduatoria d'origine. Un payload manomesso non può quindi scrivere su un socio fuori dal
     * territorio dell'utente (primario, dipendente o estensione).
     */
    private function eseguiImportazioneRighe(array $righe): array
    {
        $inseriti = 0;
        $aggiornati = 0;
        $ricalcolate = 0;
        $rimossi = 0;
        $errori = [];

        DB::transaction(function () use ($righe, &$inseriti, &$aggiornati, &$ricalcolate, &$rimossi, &$errori) {
            $socioIds = collect($righe)->pluck('socio_id')->unique();
            $sociById = Socio::whereIn('id', $socioIds)->get()->keyBy('id');

            // Righe patente: stesso gate per-riga (canEditQualifiche), delegate a
            // PatenteCriGaiaImport raggruppate per socio; i contatori confluiscono negli stessi
            // inseriti/aggiornati/rimossi della risposta.
            collect($righe)
                ->filter(fn ($r) => ($r['tipo_riga'] ?? null) === 'patente')
                ->groupBy('socio_id')
                ->each(function ($righeSocio, $socioId) use ($sociById, &$inseriti, &$aggiornati, &$rimossi, &$errori) {
                    $socio = $sociById->get($socioId);
                    if (! $socio || ! SocioResource::canEditQualifiche($socio)) {
                        return;
                    }
                    $res = PatenteCriGaiaImport::importa($socio, $righeSocio->values()->all());
                    $inseriti   += $res['inseriti'];
                    $aggiornati += $res['aggiornati'];
                    $rimossi    += $res['rimossi'];
                    $errori      = array_merge($errori, $res['errori']);
                });

            foreach ($righe as $riga) {
                if (($riga['tipo_riga'] ?? null) === 'patente') {
                    continue; // già gestite sopra
                }

                $socio = $sociById->get($riga['socio_id'] ?? null);

                if (! $socio || ! SocioResource::canEditQualifiche($socio)) {
                    continue;
                }

                try {
                    if ($riga['stato'] === 'obsoleta') {
                        QualificaSocioImportWriter::ricalcolaSeEsistente($riga, 'importazione_gaia');
                        $ricalcolate++;
                        continue;
                    }

                    if ($riga['stato'] === 'rimossa') {
                        QualificaSocioImportWriter::eliminaSeEsistente($riga);
                        $rimossi++;
                        continue;
                    }

                    if (! in_array($riga['stato'] ?? null, ['inserimento', 'aggiornamento'], true)) {
                        continue;
                    }

                    QualificaSocioImportWriter::scriviRiga($riga, 'importazione_gaia');
                    $riga['stato'] === 'inserimento' ? $inseriti++ : $aggiornati++;
                } catch (\Throwable $e) {
                    $errori[] = $e->getMessage();
                }
            }
        });

        return compact('inseriti', 'aggiornati', 'ricalcolate', 'rimossi', 'errori');
    }

    public function soci(ComitatoLocale $comitatoLocale): JsonResponse
    {
        abort_unless($this->autorizzaComitato($comitatoLocale), 403);

        // Riusa QualificheSociQueryBuilder (stesso servizio dietro la tabella Qualifiche) con
        // includiEstensioni/includiDipendenti sempre true: appartenenti, estensione e dipendenti
        // dello stesso comitato sono tutti e tre nel perimetro di chi gestisce quel comitato,
        // niente da distinguere qui (vedi apparteneAlComitato()).
        $soci = (new QualificheSociQueryBuilder())
            ->build(
                user: auth()->user(),
                filtroLocale: $comitatoLocale->id,
                includiEstensioni: true,
                includiDipendenti: true,
            )
            ->select('id', 'cognome', 'nome', 'codice_fiscale', 'gaia_persona_id')
            ->orderBy('cognome')->orderBy('nome')
            ->get()
            ->map(fn (Socio $s) => [
                'socio_id' => $s->id,
                'cognome' => $s->cognome,
                'nome' => $s->nome,
                'cf' => $s->codice_fiscale,
                'gaia_persona_id' => $s->gaia_persona_id,
            ]);

        return response()->json(['soci' => $soci]);
    }

    public function analisi(Request $request, ComitatoLocale $comitatoLocale): JsonResponse
    {
        abort_unless($this->autorizzaComitato($comitatoLocale), 403);

        $data = $request->validate([
            'soci' => ['required', 'array'],
            'soci.*.socio_id' => ['required', 'integer'],
            // 'present' non 'required': un socio letto con successo ma senza qualifiche
            // trovate manda righe: [] — deve passare la validazione per permettere la
            // riconciliazione (trovaQualificheRimosse(), vedi eseguiAnalisiRighe()).
            'soci.*.righe' => ['present', 'array'],
            'soci.*.righe.*.nome_corso' => ['required', 'string'],
            'soci.*.righe.*.dates' => ['required', 'array'],
            'soci.*.righe.*.dates.*' => ['date_format:Y-m-d'],
            'soci.*.patenti' => ['sometimes', 'array'],
            'soci.*.patenti.*.tipologia_raw' => ['required', 'string'],
            'soci.*.patenti.*.data_rilascio' => ['nullable', 'date_format:Y-m-d'],
            'soci.*.patenti.*.data_scadenza' => ['nullable', 'date_format:Y-m-d'],
            'soci.*.patenti.*.protocollo' => ['nullable', 'string'],
        ]);

        $socioIds = collect($data['soci'])->pluck('socio_id')->unique();
        $sociById = Socio::whereIn('id', $socioIds)->get()
            ->filter(fn (Socio $s) => $this->apparteneAlComitato($s, $comitatoLocale)) // scarta id estranei al comitato
            ->keyBy('id');

        return response()->json(['righe' => $this->eseguiAnalisiRighe($sociById, $data['soci'])]);
    }

    public function importa(Request $request, ComitatoLocale $comitatoLocale): JsonResponse
    {
        abort_unless($this->autorizzaComitato($comitatoLocale), 403);

        $data = $request->validate(['righe' => ['required', 'array']]);

        return response()->json($this->eseguiImportazioneRighe($data['righe']));
    }

    /**
     * Lista dei candidati iscritti alla graduatoria (con socio agganciato), inclusi i ritirati
     * (ritirato_at valorizzato) — stessa filosofia di Graduatoria::candidatiDaCercareSuGaia():
     * l'aggancio GAIA e le qualifiche sono un dato del socio, non della candidatura, e restano
     * utili anche dopo un ritiro. A differenza di candidatiDaCercareSuGaia() qui si vuole
     * rileggere il curriculum di TUTTI (non solo cercare gli ID mancanti), quindi nessun filtro
     * su gaia_persona_id.
     */
    public function sociGraduatoria(Graduatoria $graduatoria): JsonResponse
    {
        abort_unless($this->autorizzaGraduatoria($graduatoria), 403);

        $soci = $graduatoria->candidati()
            ->whereNotNull('socio_id')
            ->with('socio')
            ->get()
            ->filter(fn (GraduatoriaCandidato $c) => $c->socio)
            ->map(fn (GraduatoriaCandidato $c) => [
                'socio_id' => $c->socio->id,
                'cognome' => $c->socio->cognome,
                'nome' => $c->socio->nome,
                'cf' => $c->socio->codice_fiscale,
                'gaia_persona_id' => $c->socio->gaia_persona_id,
            ])
            ->unique('socio_id')
            ->values();

        return response()->json(['soci' => $soci]);
    }

    public function analisiGraduatoria(Request $request, Graduatoria $graduatoria): JsonResponse
    {
        abort_unless($this->autorizzaGraduatoria($graduatoria), 403);

        $data = $request->validate([
            'soci' => ['required', 'array'],
            'soci.*.socio_id' => ['required', 'integer'],
            // 'present' non 'required': un socio letto con successo ma senza qualifiche
            // trovate manda righe: [] — deve passare la validazione per permettere la
            // riconciliazione (trovaQualificheRimosse(), vedi eseguiAnalisiRighe()).
            'soci.*.righe' => ['present', 'array'],
            'soci.*.righe.*.nome_corso' => ['required', 'string'],
            'soci.*.righe.*.dates' => ['required', 'array'],
            'soci.*.righe.*.dates.*' => ['date_format:Y-m-d'],
            'soci.*.patenti' => ['sometimes', 'array'],
            'soci.*.patenti.*.tipologia_raw' => ['required', 'string'],
            'soci.*.patenti.*.data_rilascio' => ['nullable', 'date_format:Y-m-d'],
            'soci.*.patenti.*.data_scadenza' => ['nullable', 'date_format:Y-m-d'],
            'soci.*.patenti.*.protocollo' => ['nullable', 'string'],
        ]);

        $socioIdsAmmessi = $graduatoria->candidati()->whereNotNull('socio_id')->pluck('socio_id');
        $sociById = Socio::whereIn('id', $socioIdsAmmessi)->get()->keyBy('id');

        return response()->json(['righe' => $this->eseguiAnalisiRighe($sociById, $data['soci'])]);
    }

    public function importaGraduatoria(Request $request, Graduatoria $graduatoria): JsonResponse
    {
        abort_unless($this->autorizzaGraduatoria($graduatoria), 403);

        $data = $request->validate(['righe' => ['required', 'array']]);

        return response()->json($this->eseguiImportazioneRighe($data['righe']));
    }
}
