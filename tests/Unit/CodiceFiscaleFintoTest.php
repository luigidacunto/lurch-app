<?php

namespace Tests\Unit;

use App\Support\CodiceFiscaleFinto;
use PHPUnit\Framework\TestCase;

/**
 * CF finti per l'anonimizzazione del DB dev (comando dev:anonimizza-dati).
 * Funzione pura → test senza DB.
 */
class CodiceFiscaleFintoTest extends TestCase
{
    private const B = CodiceFiscaleFinto::BELFIORE; // 'Z000' — non conforme per definizione

    public function test_struttura_e_belfiore_non_conforme(): void
    {
        $cf = CodiceFiscaleFinto::genera('Rossi', 'Mario', new \DateTime('1980-05-12'));

        $this->assertSame(16, strlen($cf));
        $this->assertMatchesRegularExpression('/^[A-Z]{6}\d{2}[A-Z]\d{2}' . self::B . '[A-Z]$/', $cf);
        $this->assertStringStartsWith('RSSMRA80E12' . self::B, $cf);
        $this->assertTrue(CodiceFiscaleFinto::checksumValido($cf));
    }

    public function test_femmina_giorno_piu_40(): void
    {
        $cf = CodiceFiscaleFinto::genera('Bianchi', 'Anna', new \DateTime('1990-01-03'), femmina: true);

        $this->assertStringStartsWith('BNCNNA90A43' . self::B, $cf); // 3 + 40 = 43
        $this->assertTrue(CodiceFiscaleFinto::checksumValido($cf));
    }

    public function test_nome_con_almeno_4_consonanti_usa_1a_3a_4a(): void
    {
        // Francesco → F R N C S C → F, N, C
        $cf = CodiceFiscaleFinto::genera('Verdi', 'Francesco', new \DateTime('2001-12-25'));

        $this->assertStringStartsWith('VRDFNC01T25' . self::B, $cf);
    }

    public function test_cognome_corto_paddato_con_x(): void
    {
        $cf = CodiceFiscaleFinto::genera('Fo', 'Bo', new \DateTime('1975-08-08'));

        $this->assertStringStartsWith('FOXBOX75M08' . self::B, $cf);
        $this->assertTrue(CodiceFiscaleFinto::checksumValido($cf));
    }

    public function test_belfiore_numerico_mai_conforme(): void
    {
        // Un codice Belfiore reale non ha mai parte numerica '000'.
        $this->assertMatchesRegularExpression('/[A-Z]000$/', CodiceFiscaleFinto::BELFIORE);
    }

    public function test_checksum_invalido_riconosciuto(): void
    {
        $cf = CodiceFiscaleFinto::genera('Rossi', 'Mario', new \DateTime('1980-05-12'));
        $cfRotto = substr($cf, 0, 15) . ($cf[15] === 'A' ? 'B' : 'A');

        $this->assertFalse(CodiceFiscaleFinto::checksumValido($cfRotto));
    }
}
