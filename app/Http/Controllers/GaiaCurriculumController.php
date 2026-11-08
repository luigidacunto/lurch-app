<?php

namespace App\Http\Controllers;

use App\Filament\Resources\SocioResource;
use App\Models\Socio;
use App\Services\PatenteCriGaiaImport;
use App\Services\QualificaSocioImportWriter;
use App\Services\QualificheSociImportParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint chiamati dallo userscript "LURCH - GAIA Bridge" (resources/userscripts/) dalla scheda
 * socio: il curriculum GAIA viene letto e parsato lato browser (DOMParser, stessa tecnica già
 * usata per l'autocomplete GAIA); qui arriva solo l'elenco già filtrato di righe "Qualifica CRI
 * confermate" da analizzare/importare — stessa identica logica dell'import Excel
 * (QualificheSociImportParser::analizzaCorso() + QualificaSocioImportWriter::scriviRiga()), solo
 * alimentata dal curriculum GAIA invece che da un file.
 */
class GaiaCurriculumController extends Controller
{
    /**
     * Analizza le righe estratte dal curriculum (nome corso + date) contro il catalogo LURCH,
     * restituendo per ciascuna lo stesso stato prodotto dall'import Excel (inserimento,
     * aggiornamento, obsoleta, errore_qualifica, saltata), più eventuali righe sintetiche
     * "rimossa" per qualifiche GAIA/Excel già in Lurch non più presenti in questa lettura
     * (QualificheSociImportParser::trovaQualificheRimosse()) — nessuna scrittura qui.
     */
    public function analisi(Request $request, Socio $socio): JsonResponse
    {
        // Soglia di ruolo User::puoUsareGaiaBridge() + scoping territoriale SocioResource::
        // canEditQualifiche() (include l'estensione).
        abort_unless(auth()->user()?->puoUsareGaiaBridge() && SocioResource::canEditQualifiche($socio), 403);

        $data = $request->validate([
            // 'present' non 'required': un curriculum letto con successo ma senza "Qualifica
            // CRI" confermate manda righe: [] — deve comunque passare la validazione per
            // permettere la riconciliazione (trovaQualificheRimosse()). 'required' rifiuta un
            // array vuoto (count < 1), 'present' richiede solo che la chiave esista.
            'righe'              => ['present', 'array'],
            'righe.*.nome_corso' => ['required', 'string'],
            'righe.*.dates'      => ['required', 'array'],
            'righe.*.dates.*'    => ['date_format:Y-m-d'],
            // Righe "Patente CRI" del curriculum (modelli 138/05 e 138/19), convertite al
            // modello 2024 lato server — vedi PatenteCriGaiaImport/PatenteCriGaiaConverter.
            'patenti'                => ['sometimes', 'array'],
            'patenti.*.tipologia_raw' => ['required', 'string'],
            'patenti.*.data_rilascio' => ['nullable', 'date_format:Y-m-d'],
            'patenti.*.data_scadenza' => ['nullable', 'date_format:Y-m-d'],
            'patenti.*.protocollo'    => ['nullable', 'string'],
        ]);

        $parser = new QualificheSociImportParser();

        $righe = collect($data['righe'])
            ->map(fn (array $r) => $parser->analizzaCorsoSingolo($socio, $r['nome_corso'], $r['dates']))
            ->values();

        // Riconciliazione: la lettura del curriculum è sempre completa (l'intera pagina GAIA),
        // quindi una qualifica GAIA/Excel già in Lurch ma assente da questa lettura è un segnale
        // affidabile di correzione/rimozione fatta su GAIA — vedi trovaQualificheRimosse().
        $trovatiIds = $righe->pluck('catalogo_corso_id')->filter()->unique();
        $righe = $righe->concat($parser->trovaQualificheRimosse($socio, $trovatiIds))->values();

        $patenti = PatenteCriGaiaImport::analizza($socio, $data['patenti'] ?? []);

        return response()->json(['righe' => $righe, 'patenti' => $patenti]);
    }

    /**
     * Scrive le righe già analizzate (identiche a quelle restituite da analisi(), rimandate
     * indietro tal quali dopo la conferma dell'operatore). Le righe "obsoleta" (dati già
     * aggiornati) non vengono riscritte ma vengono comunque passate a
     * aggiornaScadenzaCalcolata(): utile se il catalogo corsi è cambiato dopo l'ultimo import
     * (es. scadenza_tipo modificato) e la scadenza calcolata è rimasta indietro pur essendo le
     * date corrette. Tutto il resto (errore_qualifica, saltata) viene ignorato lato server anche
     * se il client non lo filtrasse.
     */
    public function importa(Request $request, Socio $socio): JsonResponse
    {
        // Soglia di ruolo User::puoUsareGaiaBridge() + scoping territoriale SocioResource::
        // canEditQualifiche() (include l'estensione).
        abort_unless(auth()->user()?->puoUsareGaiaBridge() && SocioResource::canEditQualifiche($socio), 403);

        $data = $request->validate(['righe' => ['required', 'array']]);

        $inseriti   = 0;
        $aggiornati = 0;
        $ricalcolate = 0;
        $rimossi    = 0;
        $errori     = [];

        // Le righe "patente" (tipo_riga === 'patente') e "qualifica" arrivano mescolate nello
        // stesso array _righeAnalisi lato userscript: qui vengono separate e trattate dai
        // rispettivi Services. I contatori delle patenti vengono sommati a quelli delle
        // qualifiche nella risposta (la modale mostra un solo riepilogo).
        $righeQualifiche = collect($data['righe'])->reject(fn ($r) => ($r['tipo_riga'] ?? null) === 'patente');
        $righePatenti    = collect($data['righe'])->filter(fn ($r) => ($r['tipo_riga'] ?? null) === 'patente')->values()->all();

        $patenti = PatenteCriGaiaImport::importa($socio, $righePatenti);
        $inseriti   += $patenti['inseriti'];
        $aggiornati += $patenti['aggiornati'];
        $rimossi    += $patenti['rimossi'];
        $errori      = array_merge($errori, $patenti['errori']);

        foreach ($righeQualifiche as $riga) {
            // La riga deve appartenere al socio della rotta (già autorizzato sopra): un
            // payload manomesso non può dirottare la scrittura su un socio diverso.
            if ((int) ($riga['socio_id'] ?? 0) !== $socio->id) {
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

        return response()->json(compact('inseriti', 'aggiornati', 'ricalcolate', 'rimossi', 'errori'));
    }
}
