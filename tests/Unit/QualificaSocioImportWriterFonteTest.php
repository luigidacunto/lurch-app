<?php

namespace Tests\Unit;

use App\Services\QualificaSocioImportWriter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Gerarchia fonte (manuale < excel < gaia, mai un downgrade) — vedi
 * QualificaSocioImportWriter::fontePuoSovrascrivere(). Metodo privato, testato via reflection
 * per evitare di dover costruire Socio/CatalogoCorso/QualificaSocio solo per verificare una
 * funzione pura di confronto.
 */
class QualificaSocioImportWriterFonteTest extends TestCase
{
    private function fontePuoSovrascrivere(?string $esistente, string $nuova): bool
    {
        $method = new ReflectionMethod(QualificaSocioImportWriter::class, 'fontePuoSovrascrivere');
        $method->setAccessible(true);

        return $method->invoke(null, $esistente, $nuova);
    }

    public function test_gaia_sovrascrive_manuale(): void
    {
        $this->assertTrue($this->fontePuoSovrascrivere('inserimento_manuale', 'importazione_gaia'));
    }

    public function test_excel_sovrascrive_manuale(): void
    {
        $this->assertTrue($this->fontePuoSovrascrivere('inserimento_manuale', 'importazione_excel'));
    }

    public function test_gaia_sovrascrive_excel(): void
    {
        $this->assertTrue($this->fontePuoSovrascrivere('importazione_excel', 'importazione_gaia'));
    }

    public function test_excel_non_declassa_gaia(): void
    {
        $this->assertFalse($this->fontePuoSovrascrivere('importazione_gaia', 'importazione_excel'));
    }

    public function test_manuale_non_declassa_niente(): void
    {
        $this->assertFalse($this->fontePuoSovrascrivere('importazione_excel', 'inserimento_manuale'));
        $this->assertFalse($this->fontePuoSovrascrivere('importazione_gaia', 'inserimento_manuale'));
    }

    public function test_stessa_fonte_si_riscrive(): void
    {
        $this->assertTrue($this->fontePuoSovrascrivere('importazione_gaia', 'importazione_gaia'));
    }

    public function test_riga_nuova_senza_fonte_esistente(): void
    {
        $this->assertTrue($this->fontePuoSovrascrivere(null, 'inserimento_manuale'));
    }

    public function test_fonte_sconosciuta_sempre_sovrascrivibile(): void
    {
        $this->assertTrue($this->fontePuoSovrascrivere('creata_da_aggiornamento', 'inserimento_manuale'));
        $this->assertTrue($this->fontePuoSovrascrivere('calcolato', 'importazione_excel'));
    }
}
