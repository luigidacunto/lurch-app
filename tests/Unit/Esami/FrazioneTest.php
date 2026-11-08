<?php

declare(strict_types=1);

namespace Tests\Unit\Esami;

use App\Esami\Calcolo\Frazione;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class FrazioneTest extends TestCase
{
    public function test_da_stringa_decimale_esatta(): void
    {
        $this->assertSame('1/4', (string) Frazione::da('0.25'));
        $this->assertSame('1/1', (string) Frazione::da('1.00')); // decimal(6,2) del DB come stringa
        $this->assertSame('115/2', (string) Frazione::da('57.5'));
        $this->assertSame('-3/1', (string) Frazione::da('-3'));
        $this->assertSame('60/1', (string) Frazione::da(60));
    }

    public function test_da_rifiuta_i_float_e_i_formati_strani(): void
    {
        foreach ([0.25, 1.0, true, null, '1,5', 'abc', '', '1e3'] as $valore) {
            try {
                Frazione::da($valore);
                $this->fail('Doveva rifiutare '.var_export($valore, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_aritmetica_esatta(): void
    {
        $terzo = Frazione::di(1, 3);
        $this->assertSame('1/1', (string) $terzo->per(Frazione::di(3)));
        $this->assertSame('1/1', (string) $terzo->somma(Frazione::di(2, 3)));
        $this->assertSame('-1/3', (string) Frazione::di(1, 3)->sottrai(Frazione::di(2, 3)));
        $this->assertSame('2/3', (string) Frazione::di(1, 3)->diviso(Frazione::di(1, 2)));
        $this->assertSame('1/2', (string) Frazione::di(2, -4)->per(Frazione::di(-1)));
        $this->assertSame(0, Frazione::di(2, 4)->confronta(Frazione::di(1, 2)));
        $this->assertSame(-1, Frazione::di(1, 3)->confronta(Frazione::di(1, 2)));
        $this->assertSame(1, Frazione::di(1, 2)->confronta(Frazione::di(1, 3)));
    }

    public function test_divisione_per_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Frazione::di(1)->diviso(Frazione::di(0));
    }

    public function test_tronca_ha_sempre_i_decimali_richiesti_e_non_arrotonda(): void
    {
        $this->assertSame('60.0', Frazione::di(60)->tronca(1));
        $this->assertSame('59.90', Frazione::da('59.9')->tronca(2));
        $this->assertSame('59.9', Frazione::di(8999, 150)->tronca(1)); // 59,9933… → 59.9, non 60.0
        $this->assertSame('0.33', Frazione::di(1, 3)->tronca(2));
        $this->assertSame('0.6', Frazione::di(2, 3)->tronca(1)); // 0,666… → 0.6
        $this->assertSame('0.05', Frazione::di(1, 20)->tronca(2));
        $this->assertSame('7', Frazione::di(15, 2)->tronca(0));
        $this->assertSame('-0.3', Frazione::di(-1, 3)->tronca(1)); // verso zero
        $this->assertSame('0.0', Frazione::di(-1, 30)->tronca(1)); // niente "-0.0"
    }

    public function test_overflow_diventa_eccezione_e_non_float(): void
    {
        $this->expectException(OverflowException::class);
        Frazione::di(PHP_INT_MAX)->per(Frazione::di(2));
    }

    public function test_overflow_nella_somma(): void
    {
        $this->expectException(OverflowException::class);
        Frazione::di(PHP_INT_MAX)->somma(Frazione::di(1));
    }

    public function test_overflow_dentro_tronca(): void
    {
        $this->expectException(OverflowException::class);
        Frazione::di(PHP_INT_MAX)->tronca(2); // num × 100 supera PHP_INT_MAX
    }

    public function test_numero_troppo_grande_da_stringa(): void
    {
        $this->expectException(OverflowException::class);
        Frazione::da('99999999999999999999');
    }

    public function test_da_frazione_legge_la_forma_di_to_string(): void
    {
        $f = Frazione::da('21.75');
        $this->assertTrue(Frazione::daFrazione((string) $f)->confronta($f) === 0);
        $this->assertSame('22.85', Frazione::daFrazione('160/7')->tronca(2));
    }

    public function test_da_frazione_rifiuta_testi_non_validi(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Frazione::daFrazione('3/0');
    }
}
