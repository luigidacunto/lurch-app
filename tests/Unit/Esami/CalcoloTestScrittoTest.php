<?php

declare(strict_types=1);

namespace Tests\Unit\Esami;

use App\Esami\Calcolo\CalcoloTestScritto;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalcoloTestScrittoTest extends TestCase
{
    /** N domande da 1 punto, risposta corretta = opzione 0, senza penalità. */
    private function domandeSemplici(int $n): array
    {
        $d = [];
        for ($i = 1; $i <= $n; $i++) {
            $d[$i] = ['corretta' => 0];
        }

        return $d;
    }

    private function risposte(int $n, int $giuste): array
    {
        $r = [];
        for ($i = 1; $i <= $n; $i++) {
            $r[$i] = $i <= $giuste ? 0 : 1;
        }

        return $r;
    }

    public function test_n_diverso_da_25(): void
    {
        $calc = new CalcoloTestScritto();
        foreach ([[30, 21], [10, 7], [25, 18]] as [$n, $giuste]) {
            $e = $calc->calcola($this->domandeSemplici($n), $this->risposte($n, $giuste));
            $this->assertSame((string) $giuste.'/1', (string) $e['punti']);
            $this->assertSame($n.'/1', (string) $e['massimo']);
        }
    }

    public function test_punti_diversi_per_domanda_e_penalita_errata(): void
    {
        $domande = [
            1 => ['corretta' => 0, 'punti' => '2.00', 'penalita_errata' => '1.00'],
            2 => ['corretta' => 0, 'punti' => '1.00', 'penalita_errata' => '1.00'],
            3 => ['corretta' => 0, 'punti' => '3.00', 'penalita_errata' => '1.00'],
        ];
        $e = (new CalcoloTestScritto())->calcola($domande, [1 => 0, 2 => 1, 3 => 0]);
        $this->assertSame('4/1', (string) $e['punti']); // 2 − 1 + 3
        $this->assertSame('6/1', (string) $e['massimo']);
    }

    public function test_esempio_9_penalita_errata_e_vuota(): void
    {
        $domande = [];
        for ($i = 1; $i <= 5; $i++) {
            $domande[$i] = ['corretta' => 0, 'punti' => 1, 'penalita_errata' => '0.25', 'penalita_vuota' => '0.5'];
        }
        $calc = new CalcoloTestScritto();

        // 2 giuste, 2 errate, 1 vuota → 2 − 0,5 − 0,5 = 1
        $e = $calc->calcola($domande, [1 => 0, 2 => 0, 3 => 1, 4 => 2, 5 => null]);
        $this->assertSame('1/1', (string) $e['punti']);
        $this->assertSame('5/1', (string) $e['massimo']);

        // tutte errate: −1,25 → mai sotto 0
        $e = $calc->calcola($domande, [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1]);
        $this->assertSame('0/1', (string) $e['punti']);

        // tutte vuote: −2,5 → 0
        $e = $calc->calcola($domande, array_fill(1, 5, null));
        $this->assertSame('0/1', (string) $e['punti']);
    }

    public function test_senza_penalita_vuota_o_errata_vale_zero(): void
    {
        $e = (new CalcoloTestScritto())->calcola($this->domandeSemplici(4), [1 => 0, 2 => null, 3 => 1, 4 => 0]);
        $this->assertSame('2/1', (string) $e['punti']);
    }

    public function test_non_consegnato_non_e_zero(): void
    {
        $e = (new CalcoloTestScritto())->calcola($this->domandeSemplici(3), null);
        $this->assertNull($e['punti']);
        $this->assertSame('3/1', (string) $e['massimo']);
    }

    public function test_risposta_mancante_e_estranea_sono_incoerenze(): void
    {
        $calc = new CalcoloTestScritto();
        try {
            $calc->calcola($this->domandeSemplici(2), [1 => 0]);
            $this->fail('Risposta mancante accettata');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(InvalidArgumentException::class);
        $calc->calcola($this->domandeSemplici(1), [1 => 0, 99 => 0]);
    }

    public function test_risposta_non_intera_rifiutata_e_senza_n_opzioni_nessun_range(): void
    {
        $calc = new CalcoloTestScritto();
        // Senza `n_opzioni` (chiamanti che non lo conoscono) 99 è semplicemente una risposta errata
        $this->assertSame('0/1', (string) $calc->calcola($this->domandeSemplici(1), [1 => 99])['punti']);
        $this->expectException(InvalidArgumentException::class);
        $calc->calcola($this->domandeSemplici(1), [1 => '0']);
    }

    /** L'app passa sempre n_opzioni = 4 (indici 0..3). */
    private function conOpzioni(array $domande): array
    {
        return array_map(fn (array $d) => $d + ['n_opzioni' => 4], $domande);
    }

    public function test_n_opzioni_scelta_fuori_campo_rifiutata(): void
    {
        $calc = new CalcoloTestScritto();
        $domande = $this->conOpzioni($this->domandeSemplici(1));
        $this->assertSame('1/1', (string) $calc->calcola($domande, [1 => 0])['punti']);
        $this->assertSame('0/1', (string) $calc->calcola($domande, [1 => 3])['punti']);
        foreach ([4, -1] as $fuori) {
            try {
                $calc->calcola($domande, [1 => $fuori]);
                $this->fail("Opzione {$fuori} accettata");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_n_opzioni_corretta_fuori_campo_rifiutata(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalcoloTestScritto())->calcola([1 => ['corretta' => 4, 'n_opzioni' => 4]], null);
    }

    /** Esempio A del piano: senza penalità, 5 domande, corrette 1,0,3,2,1; risposte 1,0,2,2,3 → 3 su 5. */
    public function test_esempio_a_senza_penalita(): void
    {
        $corrette = [1 => 1, 2 => 0, 3 => 3, 4 => 2, 5 => 1];
        $domande = $this->conOpzioni(array_map(fn (int $c) => ['corretta' => $c], $corrette));
        $e = (new CalcoloTestScritto())->calcola($domande, [1 => 1, 2 => 0, 3 => 2, 4 => 2, 5 => 3]);
        $this->assertSame('3/1', (string) $e['punti']);
        $this->assertSame('5/1', (string) $e['massimo']);
    }

    /** Esempio B: corretta 2, sbagliata 1, non risposta 0,5 → 3 giuste, 1 sbagliata, 1 vuota = 4,5 su 10; tutte sbagliate/vuote → 0. */
    public function test_esempio_b_penalita(): void
    {
        $domande = $this->conOpzioni(array_fill(1, 5, ['corretta' => 0, 'punti' => '2.0', 'penalita_errata' => '1.0', 'penalita_vuota' => '0.5']));
        $calc = new CalcoloTestScritto();
        $e = $calc->calcola($domande, [1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => null]);
        $this->assertSame('4.5', $e['punti']->tronca(1));
        $this->assertSame('10.0', $e['massimo']->tronca(1));
        $e = $calc->calcola($domande, [1 => 1, 2 => 1, 3 => 1, 4 => null, 5 => null]);
        $this->assertSame('0.0', $e['punti']->tronca(1)); // −3 −1 = −4 → 0
    }

    /** Esempio C: esattezza con decimali (con i float 3 − 0,6 − 0,1 dà 2,3000000000000003). */
    public function test_esempio_c_decimali_esatti(): void
    {
        $domande = $this->conOpzioni(array_fill(1, 5, ['corretta' => 0, 'punti' => '1.5', 'penalita_errata' => '0.3', 'penalita_vuota' => '0.1']));
        $e = (new CalcoloTestScritto())->calcola($domande, [1 => 0, 2 => 0, 3 => 1, 4 => 2, 5 => null]);
        $this->assertSame('23/10', (string) $e['punti']);
        $this->assertSame('7.5', $e['massimo']->tronca(1));
    }
}
