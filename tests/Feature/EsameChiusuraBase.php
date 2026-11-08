<?php

namespace Tests\Feature;

use App\Models\TcriColloquio;
use App\Models\TcriItinereDecisione;
use App\Models\TcriProvaPratica;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Models\TestDomanda;
use App\Models\TestRisposta;
use Illuminate\Support\Str;

/**
 * Base dei test di chiusura, esito ed export: come la base del test scritto più le scritture dirette delle schede
 * FINALI (le stesse righe che producono pannello e servizi) per costruire in fretta i casi numerici.
 */
abstract class EsameChiusuraBase extends EsameTestScrittoBase
{
    /** Finali d'area, colloquio e prova pratica (verdi/gialli, il resto rosso su 28 voci) di un discente. */
    protected function finali(string $k, array $aree = [4, 3, 3.5, 4], string $colloquio = '4', int $verdi = 20, int $gialli = 4): void
    {
        $socio = $this->s[$k]->id;
        $codici = array_column($this->esame->modello_snapshot['itinere']['aree'], 'codice');
        foreach ($codici as $i => $codice) {
            TcriItinereDecisione::updateOrCreate(
                ['esame_id' => $this->esame->id, 'discente_socio_id' => $socio, 'area_codice' => $codice],
                ['punteggio' => number_format($aree[$i], 1, '.', ''), 'deciso_da' => $this->s['direttore']->id],
            );
        }
        TcriColloquio::updateOrCreate(
            ['esame_id' => $this->esame->id, 'discente_socio_id' => $socio, 'tipo' => TcriColloquio::TIPO_FINALE],
            ['punteggio' => $colloquio, 'inserito_da' => $this->s['direttore']->id],
        );
        $prova = TcriProvaPratica::updateOrCreate(
            ['esame_id' => $this->esame->id, 'discente_socio_id' => $socio, 'tipo' => TcriProvaPratica::TIPO_FINALE],
            ['inserito_da' => $this->s['direttore']->id],
        );
        $voci = \App\Esami\StrutturaSemaforo::per($this->esame)['codici'];
        foreach ($voci as $n => $voce) {
            $valore = $n < $verdi ? '1.00' : ($n < $verdi + $gialli ? '0.25' : '0.00');
            $prova->voci()->updateOrCreate(['voce_codice' => $voce], ['valore' => $valore]);
        }
    }

    /**
     * Test inviato (online) del discente: le prime $giuste risposte corrette, poi $sbagliate errate, il resto vuote.
     * Richiede le domande già create.
     */
    protected function testInviato(string $k, int $giuste, int $sbagliate = 0): void
    {
        $socio = $this->s[$k]->id;
        TestAvvio::updateOrCreate(
            ['esame_id' => $this->esame->id, 'discente_socio_id' => $socio],
            ['modalita' => TestAvvio::ONLINE, 'avviato_il' => now(), 'token' => (string) Str::uuid(), 'avviato_da' => $this->s['direttore']->id],
        );
        $consegna = TestConsegna::create([
            'esame_id' => $this->esame->id, 'discente_socio_id' => $socio, 'origine' => TestConsegna::ORIGINE_ONLINE, 'consegnato_il' => now(),
        ]);
        foreach (TestDomanda::where('esame_id', $this->esame->id)->orderBy('ordine')->get()->makeVisible('corretta') as $i => $d) {
            $opzione = $i < $giuste ? (int) $d->corretta : ($i < $giuste + $sbagliate ? ((int) $d->corretta + 1) % 4 : null);
            TestRisposta::create(['consegna_id' => $consegna->id, 'domanda_id' => $d->id, 'opzione' => $opzione]);
        }
    }

    /** Discente completo con i numeri del Caso 1 del piano (74,1 idoneo): 25 domande, 18 giuste. */
    protected function discenteCompleto(string $k, array $aree = [4, 3, 3.5, 4], string $colloquio = '4', int $verdi = 20, int $gialli = 4, int $giuste = 18, int $sbagliate = 7): void
    {
        $this->finali($k, $aree, $colloquio, $verdi, $gialli);
        $this->testInviato($k, $giuste, $sbagliate);
    }

    protected function riga(array $righe, string $k): array
    {
        foreach ($righe as $r) {
            if ($r['socio']->id === $this->s[$k]->id) {
                return $r;
            }
        }
        $this->fail("Riga di {$k} non trovata");
    }
}
