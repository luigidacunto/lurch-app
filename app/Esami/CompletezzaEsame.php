<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Models\Esame;
use App\Models\TcriColloquio;
use App\Models\TcriProvaPratica;

/**
 * Controllo di completezza: per ogni discente AMMESSO dice se le schede FINALI ci sono
 * (aree itinere decise, colloquio, prova pratica). C'è anche `test_inviato`
 * (solo segnalazione, fuori da `completa`). Le schede personali mancanti sono
 * solo una segnalazione e non rendono mai incompleto un discente. Nessun blocco qui. Il blocco alla chiusura NON usa questa classe:
 * lo decide il motore via App\Esami\RisultatiEsame (discente «incompleto» = finale nullo, test inviato incluso); qui resta la barra di avanzamento.
 */
class CompletezzaEsame
{
    /**
     * @return array<int, array{aree_decise: int, n_aree: int, itinere: bool, colloquio: bool, prova_pratica: bool, completa: bool, test_inviato: bool, personali_mancanti: array{colloquio: int, prova_pratica: int, attese: int}}>
     */
    public static function per(Esame $esame): array
    {
        $snapshot = $esame->modello_snapshot;
        $calcolo = CalcoloEsameFactory::tcri();
        $ammessi = $esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id')->all();

        $decisioni = $esame->decisioniItinere()->get()->groupBy('discente_socio_id');
        $colloquiFinali = $esame->colloqui()->where('tipo', TcriColloquio::TIPO_FINALE)->pluck('discente_socio_id')->flip();
        $proveFinali = $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_FINALE)->with('voci')->get()->keyBy('discente_socio_id');
        $personaliC = $esame->colloqui()->where('tipo', TcriColloquio::TIPO_PERSONALE)->get()->groupBy('discente_socio_id');
        $personaliP = $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_PERSONALE)->whereNotNull('inviata_il')->get()->groupBy('discente_socio_id');
        // Test scritto inviato (online o cartaceo). Solo segnalazione: NON entra in `completa`.
        $testInviati = $esame->testConsegne()->whereNotNull('consegnato_il')->pluck('discente_socio_id')->flip();
        // Compilatori attesi: direttore + docenti di commissione (mai il presidente).
        $attese = $esame->docentiCommissione()->pluck('socio_id')->merge([$esame->corso?->direttore_socio_id])->filter()->unique()->count();

        $risultato = [];
        foreach ($ammessi as $id) {
            $finaliItinere = ($decisioni->get($id) ?? collect())->mapWithKeys(fn ($d) => [$d->area_codice => (string) $d->punteggio])->all();
            $itinere = $calcolo->totaleItinere($snapshot, $finaliItinere);
            $nAree = count($itinere['aree']);
            $colloquio = $colloquiFinali->has($id);
            $prova = isset($proveFinali[$id]) && $proveFinali[$id]->totale($snapshot)['mancanti'] === [];
            $itinereOk = $itinere['mancanti'] === [];

            $risultato[$id] = [
                'aree_decise' => $nAree - count($itinere['mancanti']),
                'n_aree' => $nAree,
                'itinere' => $itinereOk,
                'colloquio' => $colloquio,
                'prova_pratica' => $prova,
                'completa' => $itinereOk && $colloquio && $prova,
                'test_inviato' => $testInviati->has($id),
                // Segnalazione, non errore: non rende mai incompleto un discente.
                'personali_mancanti' => [
                    'colloquio' => max(0, $attese - ($personaliC->get($id)?->count() ?? 0)),
                    'prova_pratica' => max(0, $attese - ($personaliP->get($id)?->count() ?? 0)),
                    'attese' => $attese,
                ],
            ];
        }

        return $risultato;
    }
}
