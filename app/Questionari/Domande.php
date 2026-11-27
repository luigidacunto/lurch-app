<?php

namespace App\Questionari;

use InvalidArgumentException;

/**
 * Domande del questionario di gradimento, fisse nel codice e versionate.
 *
 * ⚠️ Una versione già usata da almeno un invio non si modifica (testi, opzioni, tipi, chiavi): serve una nuova
 * versione (`v2()` + VERSIONE_CORRENTE). Fa eccezione `required`, che non cambia il contenuto della domanda.
 * Le chiavi restano stabili tra le versioni se il significato non cambia.
 *
 * Sezione: key, titolo, condizione ('always' o chiave di un flag di FLAG), per_valutato (si ripete per ogni
 * docente/direttore), gruppo/sottotitolo (si mostra come parte di un'altra sezione, vedi blocchi()), domande. Domanda: key, tipo, label (segnaposto {docente}), opzioni, required, show_if.
 */
class Domande
{
    public const VERSIONE_CORRENTE = 1;

    public const SCALA = [1 => 'Per niente', 2 => 'Poco', 3 => 'Sufficientemente', 4 => 'Abbastanza', 5 => 'Molto'];

    /** Sezioni facoltative attivabili a mano sul corso (mai dedotte dai dati del corso). */
    public const FLAG = [
        'logistica_presenza' => 'Logistica in presenza',
        'pasti'              => 'Pasti',
        'pernotto'           => 'Pernotto',
        'sep'                => 'Servizio Psicosociale (Se.P)',
        'esame_finale'       => 'Esame finale',
        'tempo_mandati'      => 'Tempo per la preparazione dei mandati',
    ];

    public const TIPI = ['scale5', 'single_choice', 'single_choice_other', 'yes_no', 'text_long', 'text_short'];

    public const INTRODUZIONE = 'Il modulo è completamente anonimo: ti chiediamo di essere onesto e sincero e di esprimere, in ogni sezione, la tua valutazione sull\'organizzazione del corso e sull\'operato dello staff didattico, compresa una sezione dedicata a ciascuno dei docenti che hai visto durante il corso. Per le valutazioni usa la scala da 1 a 5 indicata.';

    /** @return array<int, array> sezioni nell'ordine di visualizzazione */
    public static function sezioni(int $versione): array
    {
        return match ($versione) {
            1 => self::v1(),
            default => throw new InvalidArgumentException("Versione del questionario sconosciuta: {$versione}"),
        };
    }

    /** Sezioni attive per un invio: sempre presenti + facoltative spuntate. */
    public static function sezioniAttive(int $versione, array $flag): array
    {
        return array_values(array_filter(
            self::sezioni($versione),
            fn (array $s) => $s['condizione'] === 'always' || in_array($s['condizione'], $flag, true),
        ));
    }

    /**
     * Sezioni attive raggruppate per la visualizzazione (modulo e risultati): una sezione con `gruppo` si mostra come
     * parte del riquadro della sezione indicata (es. Esame finale e il commento finale dentro «Monitoraggio»).
     *
     * @return array<int, array{key: string, titolo: string, per_valutato: bool, parti: array<int, array{sottotitolo: ?string, domande: array}>}>
     */
    public static function blocchi(int $versione, array $flag): array
    {
        $blocchi = [];
        foreach (self::sezioniAttive($versione, $flag) as $s) {
            $ultimo = array_key_last($blocchi);
            if ($s['gruppo'] && $ultimo !== null && $blocchi[$ultimo]['key'] === $s['gruppo']) {
                $blocchi[$ultimo]['parti'][] = ['sottotitolo' => $s['sottotitolo'], 'domande' => $s['domande']];
                continue;
            }
            $blocchi[] = ['key' => $s['key'], 'titolo' => $s['titolo'], 'per_valutato' => $s['per_valutato'], 'parti' => [['sottotitolo' => null, 'domande' => $s['domande']]]];
        }

        return $blocchi;
    }

    private static function s(string $key, string $titolo, string $condizione, array $domande, bool $perValutato = false, ?string $gruppo = null, ?string $sottotitolo = null): array
    {
        return ['key' => $key, 'titolo' => $titolo, 'condizione' => $condizione, 'per_valutato' => $perValutato, 'gruppo' => $gruppo, 'sottotitolo' => $sottotitolo, 'domande' => $domande];
    }

    private static function d(string $key, string $tipo, string $label, array $opzioni = [], ?array $showIf = null, bool $required = true): array
    {
        return ['key' => $key, 'tipo' => $tipo, 'label' => $label, 'opzioni' => $opzioni, 'required' => $required, 'show_if' => $showIf];
    }

    private static function v1(): array
    {
        $sc = 'scale5';
        $tl = 'text_long';
        $sino = ['Sì', 'No', 'Parzialmente'];

        return [
            self::s('coinvolgimento', 'Coinvolgimento', 'always', [
                self::d('coinv_dir_prima', $sc, 'Ti sei sentito coinvolto dal direttore prima del corso'),
                self::d('coinv_doc_prima', $sc, 'Ti sei sentito coinvolto dai docenti prima del corso'),
                self::d('coinv_dir_durante', $sc, 'Ti sei sentito coinvolto dal direttore durante il corso'),
                self::d('coinv_doc_durante', $sc, 'Ti sei sentito coinvolto dai docenti durante il corso'),
                self::d('coinv_dir_fine', $sc, 'Ti sei sentito coinvolto dal direttore alla fine del corso'),
                self::d('coinv_doc_fine', $sc, 'Ti sei sentito coinvolto dai docenti alla fine del corso'),
            ]),
            self::s('contenuti', 'Contenuti', 'always', [
                self::d('contenuti_commento', $tl, 'Contenuti – suggerisci eventuali suggerimenti, critiche e argomenti che sarebbero stati da approfondire'),
            ]),
            self::s('organizzazione', 'Organizzazione generale', 'always', [
                self::d('org_ore', $sc, 'Sviluppo delle ore di corso rispetto alle competenze da sviluppare'),
                self::d('org_tempi', $sc, 'Gestione e rispetto dei tempi'),
                self::d('org_calendario', $sc, 'Adeguatezza del calendario delle attività e degli orari'),
                self::d('org_info', $sc, 'Completezza e tempestività delle informazioni di servizio'),
                self::d('org_materiali', $sc, 'Qualità dei materiali didattici'),
            ]),
            self::s('logistica', 'Logistica in presenza', 'logistica_presenza', [
                self::d('log_aula', $sc, 'Funzionalità dell\'aula e delle attrezzature (acustica, temperatura, pulizia, capienza)'),
                self::d('log_raggiungibilita', $sc, 'Raggiungibilità della sede formativa'),
                self::d('log_pause', $sc, 'Quantità e durata delle pause'),
            ]),
            self::s('pasti', 'Pasti', 'pasti', [
                self::d('pasti_cibo', $sc, 'Qualità e quantità del cibo'),
            ]),
            self::s('pernotto', 'Pernotto', 'pernotto', [
                self::d('pern_alloggio', $sc, 'Adeguatezza dell\'alloggio'),
                self::d('pern_pulizia', $sc, 'Pulizia e servizi igienici'),
                self::d('pern_riposo', $sc, 'Possibilità di riposare adeguatamente'),
                self::d('pern_commento', $tl, 'Pernotto – suggerimenti e/o critiche'),
            ]),
            self::s('org_commento', 'Commento su organizzazione e logistica', 'always', [
                self::d('org_commento', $tl, 'Organizzazione e logistica – suggerisci eventuali suggerimenti e/o critiche'),
            ]),
            self::s('sep', 'Servizio Psicosociale (Se.P)', 'sep', [
                self::d('sep_spiegazione', 'single_choice', 'Ti è stata spiegata la funzione del Se.P. e il ruolo nello specifico all\'interno del corso che hai svolto?', $sino),
                self::d('sep_conoscenza', 'single_choice', 'Conoscevi già la figura dell\'operatore Se.P. all\'interno dell\'Associazione?', $sino),
                self::d('sep_utilita', $sc, 'Ritieni che la partecipazione, così proposta, degli operatori del Servizio Psicosociale sia stata utile'),
                self::d('sep_commento', $tl, 'Considerazioni e suggerimenti riguardo la collaborazione con gli operatori del Se.P. o riguardo aspetti che andrebbero migliorati'),
            ]),
            self::s('docenti', 'Valutazione dei docenti', 'always', [
                self::d('doc_competenza', $sc, 'Preparazione e competenza rispetto agli argomenti trattati'),
                self::d('doc_comunicazione', $sc, 'Capacità di comunicare in modo chiaro e comprensibile'),
                self::d('doc_relazione', $sc, 'Capacità di relazione con i discenti'),
                self::d('doc_interesse', $sc, 'Capacità di suscitare interesse e coinvolgere'),
                self::d('doc_esigenze', $sc, 'Attenzione alle esigenze e/o alle richieste dei discenti'),
                self::d('doc_gruppo', $sc, 'Capacità di gestire e coordinare il gruppo'),
                self::d('doc_spunti', $sc, 'Capacità di offrire spunti di riflessione'),
                self::d('doc_ruolo', $sc, 'Utilità delle informazioni fornite per il ruolo che andrai a svolgere'),
                self::d('doc_crescita', $sc, 'Supporto e contributo percepito alla crescita durante il percorso'),
                self::d('doc_suggerimenti', $tl, 'Suggerimenti e/o critiche indirizzate a {docente}'),
                self::d('doc_motivazione', $tl, 'Commenti che motivano la tua valutazione'),
            ], perValutato: true),
            self::s('monitoraggio', 'Monitoraggio, valutazione e feedback', 'always', [
                self::d('mon_adeguato', $sc, 'Ritieni che il monitoraggio sia stato adeguato alla tipologia di corso'),
                self::d('mon_tempi', $sc, 'Ritieni che il monitoraggio sia stato fatto con i tempi giusti'),
                self::d('mon_utile', $sc, 'Ritieni che il monitoraggio sia stato utile'),
                self::d('itinere_adeguata', $sc, 'Ritieni che la valutazione in itinere sia stata adeguata'),
                self::d('itinere_propedeutica', $sc, 'Ritieni che la valutazione in itinere sia stata utile e propedeutica rispetto a quella finale'),
                self::d('feedback_adeguati', $sc, 'Ritieni che i feedback che hai ricevuto siano stati adeguati rispetto alle esigenze della tipologia di corso'),
                self::d('feedback_puntuali', $sc, 'Ritieni che i feedback che hai ricevuto siano stati puntuali e precisi'),
            ]),
            self::s('esame_finale', 'Esame finale', 'esame_finale', [
                self::d('esame_valutazione_adeguata', $sc, 'Ritieni che la valutazione finale sia stata adeguata rispetto agli obiettivi del corso'),
                self::d('esame_coerenza', $sc, 'L\'esame finale era in linea con la presentazione del corso?'),
                self::d('esame_aspettative', 'single_choice', 'La valutazione e il feedback finale ottenuti hanno rispecchiato le tue aspettative?', ['No, mi aspettavo di più', 'Sì, mi aspettavo questo', 'Mi ha piacevolmente stupito']),
                self::d('esame_disagio', 'yes_no', 'Ci sono stati aspetti che ti hanno messo in difficoltà o a disagio durante l\'esame finale?'),
                self::d('esame_disagio_dettaglio', 'text_short', 'Descrivi quali aspetti', showIf: ['esame_disagio', 'Sì']),
            ], gruppo: 'monitoraggio', sottotitolo: 'Esame finale'),
            self::s('tempo_mandati', 'Tempo per la preparazione dei mandati', 'tempo_mandati', [
                self::d('mandati_tempo', 'single_choice_other', 'Il tempo a disposizione per preparare i mandati da presentare in presenza è stato:', ['Insufficiente', 'Adeguato', 'Più del necessario']),
            ], gruppo: 'monitoraggio', sottotitolo: 'Tempo per la preparazione dei mandati'),
            self::s('monitoraggio_commento', 'Monitoraggio, valutazione e feedback – commento', 'always', [
                self::d('mon_commento', $tl, 'Monitoraggio, valutazione e feedback – suggerisci eventuali suggerimenti e/o critiche'),
            ], gruppo: 'monitoraggio'),
            self::s('bilancio', 'Bilancio complessivo', 'always', [
                self::d('bil_critici', $tl, 'Potresti fare un bilancio complessivo del corso segnalandoci gli aspetti critici'),
                self::d('bil_positivi', $tl, 'Potresti fare un bilancio complessivo del corso segnalandoci gli aspetti positivi'),
                self::d('bil_osservazioni', $tl, 'Potresti fare un bilancio complessivo del corso segnalandoci ulteriori osservazioni e suggerimenti'),
            ]),
        ];
    }
}
