<?php

declare(strict_types=1);

namespace Tests\Unit\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\CalcoloTcri;
use App\Esami\Calcolo\CalcoloTestScritto;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalcoloTcriTest extends TestCase
{
    private array $snap;

    private array $snapV1;

    private array $voci = [];

    protected function setUp(): void
    {
        $this->snap = json_decode(
            file_get_contents(dirname(__DIR__, 3).'/database/data/esame_modelli/tcri_v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->snapV1 = $this->snap;
        // Fixture a 27 voci: gli esempi storici della specifica sono calcolati su 27.
        // Il motore legge le voci dallo snapshot, quindi il v1 reale (28) è coperto dai test in fondo.
        array_pop($this->snap['semaforo']['competenze'][0]['voci']);
        foreach ($this->snap['semaforo']['competenze'] as $c) {
            foreach ($c['voci'] as $v) {
                $this->voci[] = $v['codice'];
            }
        }
    }

    public function test_v1_reale_28_voci_massimo_verde_giallo_rosso(): void
    {
        $voci = [];
        foreach ($this->snapV1['semaforo']['competenze'] as $c) {
            array_push($voci, ...array_column($c['voci'], 'codice'));
        }
        $this->assertCount(28, $voci);
        $scheda = fn (string $v) => array_fill_keys($voci, $v);
        $punti = fn (array $s) => $this->calcola($this->dati(['prova_pratica_finale' => $s]), $this->snapV1)['dettaglio']['prova_pratica'];

        $this->assertSame('28/1', $punti($scheda('1'))['punti']);
        $this->assertSame('28/1', $punti($scheda('1'))['massimo']);
        $this->assertSame('7/1', $punti($scheda('0.25'))['punti']);
        $this->assertSame('0/1', $punti($scheda('0'))['punti']);

        $incompleta = $scheda('1');
        array_pop($incompleta);
        $r = $this->calcola($this->dati(['prova_pratica_finale' => $incompleta]), $this->snapV1);
        $this->assertSame(['semaforo_incompleto'], $this->codiciAvvisi($r));
    }

    /** Scheda finale: $verdi verdi, poi $gialli gialli, il resto rosso. */
    private function semaforo(int $verdi, int $gialli): array
    {
        $o = [];
        foreach ($this->voci as $i => $codice) {
            $o[$codice] = $i < $verdi ? '1' : ($i < $verdi + $gialli ? '0.25' : '0');
        }

        return $o;
    }

    /** Test con $tot domande da 1 punto, le prime $giuste corrette. */
    private function test(int $tot, int $giuste): array
    {
        $d = [];
        $r = [];
        for ($i = 1; $i <= $tot; $i++) {
            $d[$i] = ['corretta' => 0];
            $r[$i] = $i <= $giuste ? 0 : 1;
        }

        return ['domande' => $d, 'risposte' => $r];
    }

    private function aree(array $v): array
    {
        return array_combine(['team_work', 'comunicazione', 'problem_solving', 'crescita'], $v);
    }

    private function dati(array $sovrascrivi = []): array
    {
        return $sovrascrivi + [
            'lap' => 60,
            'itinere' => $this->aree(['3', '3', '3', '3']),
            'colloquio' => 3,
            'prova_pratica_finale' => $this->semaforo(18, 0),
            'test' => $this->test(15, 7),
        ];
    }

    private function calcola(array $dati, ?array $snap = null): array
    {
        return CalcoloEsameFactory::per('TCRI')->calcola($snap ?? $this->snap, $dati);
    }

    // ---- Esempi della specifica

    public function test_esempio_1_specifica_75_2_idoneo(): void
    {
        $r = $this->calcola($this->dati([
            'itinere' => $this->aree(['4', '3', '3.5', '4']),
            'colloquio' => 4,
            'prova_pratica_finale' => $this->semaforo(20, 4), // 20 + 4×0,25 = 21
            'test' => $this->test(25, 18),
        ]));
        $this->assertSame('13547/180', $r['finale_esatto']); // 75,2611…
        $this->assertSame('75.2', $r['finale']);
        $this->assertTrue($r['idoneo']);
        $this->assertSame('21.75', $r['contributi']['itinere']['troncato2']);
        $this->assertSame('8.00', $r['contributi']['colloquio']['troncato2']);
        $this->assertSame('31.11', $r['contributi']['prova_pratica']['troncato2']);
        $this->assertSame('14.40', $r['contributi']['test_scritto']['troncato2']);
        $this->assertSame([], $r['avvisi']);
    }

    public function test_esempio_2_specifica_59_7_non_idoneo(): void
    {
        $r = $this->calcola($this->dati([
            'itinere' => $this->aree(['3', '3', '3', '3']),
            'colloquio' => 3,
            'prova_pratica_finale' => $this->semaforo(15, 4), // 16
            'test' => $this->test(25, 15),
        ]));
        $this->assertSame('1612/27', $r['finale_esatto']); // 59,7037…
        $this->assertSame('59.7', $r['finale']);
        $this->assertFalse($r['idoneo']);
    }

    // ---- Casi limite

    public function test_semaforo_tutto_giallo_verde_rosso(): void
    {
        $this->assertSame('27/4', $this->calcola($this->dati(['prova_pratica_finale' => $this->semaforo(0, 27)]))['dettaglio']['prova_pratica']['punti']); // 6,75
        $this->assertSame('27/1', $this->calcola($this->dati(['prova_pratica_finale' => $this->semaforo(27, 0)]))['dettaglio']['prova_pratica']['punti']);
        $this->assertSame('0/1', $this->calcola($this->dati(['prova_pratica_finale' => $this->semaforo(0, 0)]))['dettaglio']['prova_pratica']['punti']);
        $this->assertSame('27/1', $this->calcola($this->dati())['dettaglio']['prova_pratica']['massimo']);
    }

    public function test_confine_esatto_60_e_idoneo(): void
    {
        $r = $this->calcola($this->dati()); // 18 + 6 + 26,666… + 9,333… = 60
        $this->assertSame('60/1', $r['finale_esatto']);
        $this->assertSame('60.0', $r['finale']);
        $this->assertTrue($r['idoneo']);

        $r = $this->calcola($this->dati(['test' => $this->test(15, 6)])); // 58,666…
        $this->assertSame('58.6', $r['finale']);
        $this->assertFalse($r['idoneo']);
    }

    public function test_appena_sotto_la_soglia(): void
    {
        $r = $this->calcola($this->dati(['test' => $this->test(300, 139)])); // 59,9333…
        $this->assertSame('59.9', $r['finale']);
        $this->assertFalse($r['idoneo']);

        $r = $this->calcola($this->dati(['test' => $this->test(300, 140)]));
        $this->assertSame('60.0', $r['finale']);
        $this->assertTrue($r['idoneo']);
    }

    public function test_troncamento_non_arrotondamento(): void
    {
        $r = $this->calcola($this->dati(['test' => $this->test(3000, 1399)])); // 59,9933…
        $this->assertSame('8999/150', $r['finale_esatto']);
        $this->assertSame('59.9', $r['finale']); // round() darebbe 60.0 e idoneo
        $this->assertFalse($r['idoneo']);
    }

    public function test_lap_decimale(): void
    {
        $r = $this->calcola($this->dati(['lap' => '60.0', 'test' => $this->test(300, 139)]));
        $this->assertFalse($r['idoneo']);
        $r = $this->calcola($this->dati(['lap' => '59.9', 'test' => $this->test(300, 139)]));
        $this->assertTrue($r['idoneo']); // 59.9 ≥ 59.9
    }

    public function test_n_domande_diverso_da_25(): void
    {
        // Stesso rapporto giuste/domande (14/30 = 7/15) → stesso finale, qualunque sia N
        $a = $this->calcola($this->dati(['test' => $this->test(30, 14)]));
        $b = $this->calcola($this->dati(['test' => $this->test(15, 7)]));
        $this->assertSame('60/1', $a['finale_esatto']);
        $this->assertSame($a['finale_esatto'], $b['finale_esatto']);
        $this->assertSame('30/1', $a['dettaglio']['test_scritto']['massimo']);

        // N = 10: 10/10 → il test pesa tutto il 20%
        $c = $this->calcola($this->dati(['test' => $this->test(10, 10)]));
        $this->assertSame('10/1', $c['dettaglio']['test_scritto']['massimo']);
        $this->assertSame('20.00', $c['contributi']['test_scritto']['troncato2']);
    }

    // ---- Regressione: cinque casi inventati, verificati a mano

    private function assertCaso(array $dati, string $finale, string $esatto, bool $idoneo, array $contributi): void
    {
        $r = $this->calcola($dati);
        $this->assertSame($finale, $r['finale']);
        $this->assertSame($esatto, $r['finale_esatto']);
        $this->assertSame($idoneo, $r['idoneo']);
        $this->assertSame([], $r['avvisi']);
        foreach ($contributi as $prova => [$esattoProva, $troncato2]) {
            $this->assertSame($esattoProva, $r['contributi'][$prova]['esatto'], $prova);
            $this->assertSame($troncato2, $r['contributi'][$prova]['troncato2'], $prova);
        }
    }

    public function test_regressione_caso_1(): void
    {
        $this->assertCaso(
            $this->dati([
                'itinere' => $this->aree(['3.5', '4.5', '2', '5']),
                'colloquio' => 2,
                'prova_pratica_finale' => $this->semaforo(20, 3), // 4 rossi
                'test' => $this->test(30, 22),
            ]),
            '71.9', '3883/54', true,
            ['itinere' => ['45/2', '22.50'], 'colloquio' => ['4/1', '4.00'], 'prova_pratica' => ['830/27', '30.74'], 'test_scritto' => ['44/3', '14.66']]
        );
    }

    public function test_regressione_caso_2_tutto_al_minimo(): void
    {
        $this->assertCaso(
            $this->dati([
                'itinere' => $this->aree(['1', '1', '1', '1']),
                'colloquio' => 1,
                'prova_pratica_finale' => $this->semaforo(0, 0),
                'test' => $this->test(20, 0),
            ]),
            '8.0', '8/1', false,
            ['itinere' => ['6/1', '6.00'], 'colloquio' => ['2/1', '2.00'], 'prova_pratica' => ['0/1', '0.00'], 'test_scritto' => ['0/1', '0.00']]
        );
    }

    public function test_regressione_caso_3_lap_57_5_sul_confine(): void
    {
        $base = [
            'lap' => '57.5',
            'itinere' => $this->aree(['3', '3', '3', '3']),
            'colloquio' => 3,
            'prova_pratica_finale' => $this->semaforo(18, 0),
        ];
        // 41 giuste su 120: esattamente 57,5 = LAP → idoneo
        $this->assertCaso(
            $base + ['test' => $this->test(120, 41)],
            '57.5', '115/2', true,
            ['itinere' => ['18/1', '18.00'], 'colloquio' => ['6/1', '6.00'], 'prova_pratica' => ['80/3', '26.66'], 'test_scritto' => ['41/6', '6.83']]
        );
        // 40 giuste: 57,333… → 57.3 → non idoneo
        $this->assertCaso(
            $base + ['test' => $this->test(120, 40)],
            '57.3', '172/3', false,
            ['itinere' => ['18/1', '18.00'], 'colloquio' => ['6/1', '6.00'], 'prova_pratica' => ['80/3', '26.66'], 'test_scritto' => ['20/3', '6.66']]
        );
    }

    public function test_regressione_caso_4_test_con_punti_diversi_e_penalita(): void
    {
        $domande = [];
        foreach ([1 => 1, 2 => 2, 3 => 3, 4 => 4] as $id => $punti) {
            $domande[$id] = ['corretta' => 0, 'punti' => $punti, 'penalita_errata' => '0.5', 'penalita_vuota' => '0.25'];
        }
        $base = [
            'lap' => 60,
            'itinere' => $this->aree(['2.5', '2.5', '2.5', '2.5']),
            'colloquio' => 3,
            'prova_pratica_finale' => $this->semaforo(12, 6), // 9 rossi
        ];
        // giusta, errata, vuota, giusta: 1 − 0,5 − 0,25 + 4 = 4,25 su 10
        $r = $this->calcola($base + ['test' => ['domande' => $domande, 'risposte' => [1 => 0, 2 => 1, 3 => null, 4 => 0]]]);
        $this->assertSame('17/4', $r['dettaglio']['test_scritto']['punti']);
        $this->assertSame('10/1', $r['dettaglio']['test_scritto']['massimo']);
        $this->assertCaso(
            $base + ['test' => ['domande' => $domande, 'risposte' => [1 => 0, 2 => 1, 3 => null, 4 => 0]]],
            '49.5', '99/2', false,
            ['itinere' => ['15/1', '15.00'], 'colloquio' => ['6/1', '6.00'], 'prova_pratica' => ['20/1', '20.00'], 'test_scritto' => ['17/2', '8.50']]
        );
        // tutte errate: −2 → portato a 0
        $this->assertCaso(
            $base + ['test' => ['domande' => $domande, 'risposte' => [1 => 1, 2 => 1, 3 => 1, 4 => 1]]],
            '41.0', '41/1', false,
            ['itinere' => ['15/1', '15.00'], 'colloquio' => ['6/1', '6.00'], 'prova_pratica' => ['20/1', '20.00'], 'test_scritto' => ['0/1', '0.00']]
        );
    }

    public function test_regressione_caso_5_colloquio_assente_e_semaforo_a_26_voci(): void
    {
        $scheda = array_slice($this->semaforo(26, 0), 0, 26, true); // manca l'ultima voce
        $r = $this->calcola($this->dati([
            'itinere' => $this->aree(['4', '4', '4', '4']),
            'colloquio' => null,
            'prova_pratica_finale' => $scheda,
            'test' => $this->test(25, 20),
        ]));
        $this->assertNull($r['finale']);
        $this->assertNull($r['finale_esatto']);
        $this->assertNull($r['idoneo']);
        $this->assertSame(['esatto' => '24/1', 'troncato2' => '24.00'], $r['contributi']['itinere']);
        $this->assertNull($r['contributi']['colloquio']);
        $this->assertNull($r['contributi']['prova_pratica']);
        $this->assertSame(['esatto' => '16/1', 'troncato2' => '16.00'], $r['contributi']['test_scritto']);
        $this->assertSame(['colloquio_mancante', 'semaforo_incompleto'], array_column($r['avvisi'], 'codice'));
        $this->assertSame(['semaforo.chiusura_della_lezione.2'], $r['avvisi'][1]['contesto']['voci_mancanti']);
    }

    // ---- Tutto dallo snapshot, niente nel codice

    public function test_pesi_e_massimi_si_leggono_dallo_snapshot(): void
    {
        $snap = $this->snap;
        $snap['pesi'] = ['itinere' => 100, 'colloquio' => 0, 'prova_pratica' => 0, 'test_scritto' => 0];
        $r = $this->calcola($this->dati(['itinere' => $this->aree(['5', '5', '5', '5'])]), $snap);
        $this->assertSame('100.0', $r['finale']);

        $snap = $this->snap;
        $snap['colloquio']['scala']['max'] = 10; // massimo del colloquio diverso: C=5 → 50%
        $snap['pesi'] = ['itinere' => 0, 'colloquio' => 100, 'prova_pratica' => 0, 'test_scritto' => 0];
        $this->assertSame('50.0', $this->calcola($this->dati(['colloquio' => 5]), $snap)['finale']);
    }

    public function test_versione_int_o_stringa_stesso_risultato_altre_rifiutate(): void
    {
        $int = $this->calcola($this->dati());
        $snap = $this->snap;
        $snap['versione'] = '1';
        $this->assertSame($int['finale_esatto'], $this->calcola($this->dati(), $snap)['finale_esatto']);

        foreach ([2, 'x', null, 1.0] as $versione) {
            $snap['versione'] = $versione;
            try {
                $this->calcola($this->dati(), $snap);
                $this->fail('Versione accettata: '.var_export($versione, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_snapshot_di_altro_tipo_o_malformato(): void
    {
        $snap = $this->snap;
        $snap['tipo_codice'] = 'ALTRO';
        $this->expectException(InvalidArgumentException::class);
        $this->calcola($this->dati(), $snap);
    }

    public function test_snapshot_senza_pesi(): void
    {
        $snap = $this->snap;
        unset($snap['pesi']['colloquio']);
        $this->expectException(InvalidArgumentException::class);
        $this->calcola($this->dati(), $snap);
    }

    // ---- Dati incompleti: null + avviso, mai valori inventati

    private function codiciAvvisi(array $r): array
    {
        return array_column($r['avvisi'], 'codice');
    }

    public function test_area_mancante(): void
    {
        $itinere = $this->aree(['3', '3', '3', '3']);
        unset($itinere['crescita']);
        $r = $this->calcola($this->dati(['itinere' => $itinere]));
        $this->assertNull($r['finale']);
        $this->assertNull($r['idoneo']);
        $this->assertNull($r['contributi']['itinere']);
        $this->assertSame(['itinere_area_mancante'], $this->codiciAvvisi($r));
        $this->assertSame(['area' => 'crescita'], $r['avvisi'][0]['contesto']);
        $this->assertNotNull($r['contributi']['colloquio']); // le altre prove restano calcolate
    }

    public function test_colloquio_mancante(): void
    {
        $r = $this->calcola($this->dati(['colloquio' => null]));
        $this->assertNull($r['finale']);
        $this->assertSame(['colloquio_mancante'], $this->codiciAvvisi($r));
    }

    public function test_prova_pratica_mancante_e_semaforo_incompleto(): void
    {
        $r = $this->calcola($this->dati(['prova_pratica_finale' => null]));
        $this->assertNull($r['finale']);
        $this->assertSame(['prova_pratica_mancante'], $this->codiciAvvisi($r));

        $scheda = $this->semaforo(18, 0);
        array_pop($scheda); // 26 voci su 27
        $r = $this->calcola($this->dati(['prova_pratica_finale' => $scheda]));
        $this->assertNull($r['finale']);
        $this->assertNull($r['idoneo']);
        $this->assertSame(['semaforo_incompleto'], $this->codiciAvvisi($r));
        $this->assertSame([$this->voci[26]], $r['avvisi'][0]['contesto']['voci_mancanti']);

        $scheda = $this->semaforo(18, 0);
        $scheda[$this->voci[5]] = null; // voce presente ma vuota = mancante
        $this->assertSame(['semaforo_incompleto'], $this->codiciAvvisi($this->calcola($this->dati(['prova_pratica_finale' => $scheda]))));
    }

    public function test_test_non_consegnato_e_senza_domande(): void
    {
        $r = $this->calcola($this->dati(['test' => null]));
        $this->assertNull($r['finale']);
        $this->assertSame(['test_non_consegnato'], $this->codiciAvvisi($r));

        $r = $this->calcola($this->dati(['test' => ['domande' => [], 'risposte' => []]]));
        $this->assertNull($r['finale']);
        $this->assertSame(['test_senza_domande'], $this->codiciAvvisi($r));
    }

    public function test_tutto_mancante_accumula_gli_avvisi(): void
    {
        $r = $this->calcola(['lap' => 60]);
        $this->assertNull($r['finale']);
        $this->assertSame(
            ['itinere_area_mancante', 'itinere_area_mancante', 'itinere_area_mancante', 'itinere_area_mancante',
                'colloquio_mancante', 'prova_pratica_mancante', 'test_non_consegnato'],
            $this->codiciAvvisi($r)
        );
    }

    public function test_schede_individuali_assenti_non_sono_un_errore(): void
    {
        // Il calcolo non le riceve: dati completi senza alcuna scheda individuale → nessun avviso
        $this->assertSame([], $this->calcola($this->dati())['avvisi']);
    }

    // ---- Eccezioni sui dati non validi

    #[DataProvider('datiNonValidi')]
    public function test_dati_non_validi_lanciano_eccezione(array $sovrascrivi): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calcola($this->dati($sovrascrivi));
    }

    public static function datiNonValidi(): array
    {
        $aree = fn (array $v) => array_combine(['team_work', 'comunicazione', 'problem_solving', 'crescita'], $v);

        return [
            'colloquio con due decimali' => [['colloquio' => '3.55']],
            'colloquio con un quarto' => [['colloquio' => '3.25']],
            'colloquio float' => [['colloquio' => 3.5]],
            'colloquio sopra il massimo' => [['colloquio' => 6]],
            'colloquio sotto il minimo' => [['colloquio' => 0]],
            'area sopra 5,0' => [['itinere' => $aree(['5.1', '3', '3', '3'])]],
            'area sotto 1,0' => [['itinere' => $aree(['0.9', '3', '3', '3'])]],
            'area sconosciuta' => [['itinere' => $aree(['3', '3', '3', '3']) + ['inventata' => '3']]],
            'lap float' => [['lap' => 60.0]],
            'lap sopra 100' => [['lap' => 101]],
            'lap negativo' => [['lap' => -1]],
            'valore semaforo fuori scala' => [['prova_pratica_finale' => ['semaforo.apertura_della_lezione.1' => '0.5']]],
            'valore semaforo float' => [['prova_pratica_finale' => ['semaforo.apertura_della_lezione.1' => 1.0]]],
        ];
    }

    public function test_voce_sconosciuta_nella_prova_pratica(): void
    {
        $scheda = $this->semaforo(18, 0);
        $scheda['semaforo.voce_inventata.1'] = '1';
        $this->expectException(InvalidArgumentException::class);
        $this->calcola($this->dati(['prova_pratica_finale' => $scheda]));
    }

    public function test_manca_il_lap(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calcola(['itinere' => []]);
    }

    // ---- mediaArea (voci vuote ignorate)

    private function media(array $voti, int|string $lap = 60, string $area = 'team_work'): array
    {
        return (new CalcoloTcri(new CalcoloTestScritto()))->mediaArea($this->snap, $area, $voti, $lap);
    }

    public function test_media_area_ignora_le_voci_vuote(): void
    {
        $m = $this->media(['itinere.team_work.1' => 4, 'itinere.team_work.2' => '3', 'itinere.team_work.3' => null, 'itinere.team_work.4' => null]);
        $this->assertSame('7/2', (string) $m['media']); // 3,5 — è un oggetto Frazione
        $this->assertSame('3.50', $m['media']->tronca(2));
        $this->assertFalse($m['forzato']);
        $this->assertSame(2, $m['voti_contati']);
    }

    public function test_media_area_senza_voti_vale_lap_per_5(): void
    {
        foreach ([[], ['itinere.team_work.1' => null]] as $voti) {
            $m = $this->media($voti);
            $this->assertSame('3/1', (string) $m['media']); // 60% di 5
            $this->assertTrue($m['forzato']);
            $this->assertSame(0, $m['voti_contati']);
        }
        $this->assertSame('2.875', $this->media([], '57.5')['media']->tronca(3));
    }

    // ---- sintesiArea e totaleItinere

    private function calcoloTcri(): CalcoloTcri
    {
        return new CalcoloTcri(new CalcoloTestScritto());
    }

    public function test_sintesi_area_media_delle_medie_e_media_di_tutti_i_voti(): void
    {
        // Anna 5,5 (due voti), Bruno 3,3,3,3: 4,0 contro 22/6.
        $s = $this->calcoloTcri()->sintesiArea($this->snap, 'team_work', [
            'anna'  => ['itinere.team_work.1' => 5, 'itinere.team_work.2' => 5],
            'bruno' => ['itinere.team_work.1' => 3, 'itinere.team_work.2' => 3, 'itinere.team_work.3' => 3, 'itinere.team_work.4' => 3],
        ], 60);
        $this->assertSame('4.00', $s['media_delle_medie']->tronca(2));
        $this->assertSame('11/3', (string) $s['media_tutti_i_voti']);
        $this->assertSame('3.66', $s['media_tutti_i_voti']->tronca(2));
        $this->assertFalse($s['forzato']);
        $this->assertSame(2, $s['n_docenti']);
        $this->assertSame('4/1', (string) $s['proposta']);
        $this->assertSame('5/1', (string) $s['docenti']['anna']['media']);
    }

    public function test_sintesi_area_ignora_il_docente_senza_voti_nell_area(): void
    {
        $s = $this->calcoloTcri()->sintesiArea($this->snap, 'team_work', [
            'anna'  => ['itinere.team_work.1' => 4],
            'bruno' => ['itinere.team_work.1' => null],
        ], 60);
        $this->assertSame('4/1', (string) $s['media_delle_medie']);
        $this->assertSame(1, $s['n_docenti']);
        $this->assertNull($s['docenti']['bruno']['media']);
    }

    public function test_sintesi_area_senza_nessun_voto_propone_lap_per_5_forzato(): void
    {
        foreach ([[], ['anna' => []], ['anna' => ['itinere.team_work.1' => null]]] as $voti) {
            $s = $this->calcoloTcri()->sintesiArea($this->snap, 'team_work', $voti, 60);
            $this->assertTrue($s['forzato']);
            $this->assertNull($s['media_delle_medie']);
            $this->assertNull($s['media_tutti_i_voti']);
            $this->assertSame('3/1', (string) $s['proposta']);
        }
    }

    public function test_totale_itinere_completo_incompleto_e_fuori_scala(): void
    {
        $c = $this->calcoloTcri();
        $t = $c->totaleItinere($this->snap, ['team_work' => '4.5', 'comunicazione' => '3.5', 'problem_solving' => '3', 'crescita' => '4']);
        $this->assertSame('15/1', (string) $t['somma']);
        $this->assertSame('20/1', (string) $t['massimo']);
        $this->assertSame([], $t['mancanti']);

        $t = $c->totaleItinere($this->snap, ['team_work' => '4.5', 'comunicazione' => null]);
        $this->assertNull($t['somma'], 'nessun totale parziale');
        $this->assertSame(['comunicazione', 'problem_solving', 'crescita'], $t['mancanti']);

        $this->expectException(InvalidArgumentException::class);
        $c->totaleItinere($this->snap, ['team_work' => '5.1']);
    }

    public function test_totale_itinere_area_sconosciuta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calcoloTcri()->totaleItinere($this->snap, ['inventata' => '3']);
    }

    #[DataProvider('votiNonValidi')]
    public function test_media_area_voti_non_validi(array $voti, string $area = 'team_work'): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->media($voti, 60, $area);
    }

    public static function votiNonValidi(): array
    {
        return [
            'voto 0' => [['itinere.team_work.1' => 0]],
            'voto 6' => [['itinere.team_work.1' => 6]],
            'voto decimale' => [['itinere.team_work.1' => '3.5']],
            'voto float' => [['itinere.team_work.1' => 3.0]],
            'voce di un\'altra area' => [['itinere.comunicazione.1' => 3]],
            'area sconosciuta' => [[], 'inventata'],
        ];
    }

    // ---- Colloquio con 1 decimale e proposte per le schede finali

    public function test_colloquio_finale_con_un_decimale(): void
    {
        $r = $this->calcola($this->dati(['colloquio' => '3.5']));
        $this->assertSame('7/2', $r['dettaglio']['colloquio']['punti']);
        $this->assertSame('7.00', $r['contributi']['colloquio']['troncato2']); // 10 × 3,5 / 5
    }

    public function test_proposta_colloquio_media_esatta(): void
    {
        $c = $this->calcoloTcri();
        $this->assertNull($c->propostaColloquio($this->snap, []));
        $this->assertSame('3.5', $c->propostaColloquio($this->snap, [4, 3])->tronca(1));
        $this->assertSame('3.5', $c->propostaColloquio($this->snap, [4, 4, 3, 4, 3, 4, 3])->tronca(1)); // 25/7 = 3,571…: si tronca, non si arrotonda a 3,6
        $this->assertSame('4.0', $c->propostaColloquio($this->snap, ['4'])->tronca(1));
    }

    #[DataProvider('votiColloquioNonValidi')]
    public function test_proposta_colloquio_voti_non_validi(array $voti): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calcoloTcri()->propostaColloquio($this->snap, $voti);
    }

    public static function votiColloquioNonValidi(): array
    {
        return ['decimale' => [['3.5']], 'zero' => [[0]], 'sei' => [[6]]];
    }

    public function test_proposta_voci_semaforo_maggioranza_e_pareggio_al_piu_basso(): void
    {
        $voci = $this->voci;
        $a = array_fill_keys($voci, '1');
        $b = array_fill_keys($voci, '1');
        $c = array_fill_keys($voci, '1');
        $b[$voci[0]] = '0.25';
        $c[$voci[0]] = '0.25';   // 2 gialli contro 1 verde → giallo
        $a[$voci[1]] = '0';      // pareggio rosso/verde/giallo? a=0, b=1, c=1 → verde (2 su 3)
        $r = $this->calcoloTcri()->propostaVociSemaforo($this->snap, [$a, $b, $c]);
        $this->assertSame('0.25', $r['proposta'][$voci[0]]);
        $this->assertSame('1.00', $r['proposta'][$voci[1]]);
        $this->assertTrue($r['discordanti'][$voci[0]]);
        $this->assertFalse($r['discordanti'][$voci[2]]);

        // Pareggio 1 a 1: vince il valore più basso
        $r = $this->calcoloTcri()->propostaVociSemaforo($this->snap, [array_fill_keys($voci, '1'), array_fill_keys($voci, '0.25')]);
        $this->assertSame('0.25', $r['proposta'][$voci[0]]);
        $this->assertTrue($r['discordanti'][$voci[0]]);
    }

    public function test_proposta_voci_semaforo_senza_schede_o_con_una_sola(): void
    {
        $voci = $this->voci;
        $vuota = $this->calcoloTcri()->propostaVociSemaforo($this->snap, []);
        $this->assertSame([null], array_values(array_unique($vuota['proposta'])));
        $this->assertSame([false], array_values(array_unique($vuota['discordanti'])));

        $una = $this->calcoloTcri()->propostaVociSemaforo($this->snap, [array_fill_keys($voci, '0')]);
        $this->assertSame(['0.00'], array_values(array_unique($una['proposta'])));

        // Voce vuota in una scheda: contano solo i valori presenti
        $parziale = array_fill_keys($voci, '1');
        $parziale[$voci[0]] = null;
        $r = $this->calcoloTcri()->propostaVociSemaforo($this->snap, [$parziale]);
        $this->assertNull($r['proposta'][$voci[0]]);
    }

    public function test_proposta_voci_semaforo_valore_fuori_scala(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calcoloTcri()->propostaVociSemaforo($this->snap, [[$this->voci[0] => '0.5']]);
    }
}
