<?php

namespace Tests\Unit;

use App\Services\PatenteCriGaiaConverter;
use PHPUnit\Framework\TestCase;

class PatenteCriGaiaConverterTest extends TestCase
{
    public function test_tipi_invariati(): void
    {
        foreach (['1', '2', '3', '4', '5', '6', '7', '8'] as $t) {
            $r = PatenteCriGaiaConverter::converti($t);
            $this->assertTrue($r['convertibile']);
            $this->assertSame($t, $r['tipologia_2024']);
        }
    }

    public function test_conversioni_allegato_c1(): void
    {
        $this->assertSame('2b', PatenteCriGaiaConverter::converti('5b')['tipologia_2024']);
        $this->assertSame('7b', PatenteCriGaiaConverter::converti('4b')['tipologia_2024']);
    }

    public function test_tipi_gia_2024_passano_invariati(): void
    {
        foreach (['2b', '7b', 'PATENTE CRI 7B'] as $t) {
            $r = PatenteCriGaiaConverter::converti($t);
            $this->assertTrue($r['convertibile'], "$t deve essere convertibile");
        }
        $this->assertSame('2b', PatenteCriGaiaConverter::converti('2b')['tipologia_2024']);
        $this->assertSame('7b', PatenteCriGaiaConverter::converti('7b')['tipologia_2024']);
        $this->assertNull(PatenteCriGaiaConverter::converti('7b')['nota']); // nessuna conversione
    }

    public function test_tipo_9_non_convertibile(): void
    {
        $r = PatenteCriGaiaConverter::converti('9');
        $this->assertFalse($r['convertibile']);
        $this->assertNull($r['tipologia_2024']);
        $this->assertNotNull($r['nota']);
    }

    public function test_tipo_sconosciuto_non_convertibile(): void
    {
        $this->assertFalse(PatenteCriGaiaConverter::converti('99z')['convertibile']);
    }

    public function test_normalizza_prefisso_e_case(): void
    {
        $this->assertSame('5b', PatenteCriGaiaConverter::normalizza('PATENTE CRI 5B'));
        $this->assertSame('7', PatenteCriGaiaConverter::normalizza('  Patente CRI 7 '));
        $this->assertSame('2b', PatenteCriGaiaConverter::converti('PATENTE CRI 5B')['tipologia_2024']);
    }
}
