<?php

namespace Tests\Unit;

use App\Support\TestoConLink;
use PHPUnit\Framework\TestCase;

class TestoConLinkTest extends TestCase
{
    public function test_indirizzo_diventa_link_con_dominio_e_pulsante_copia(): void
    {
        $html = (string) TestoConLink::html('Modulo: https://docs.example.com/d/abc?x=1&y=2 (scaricalo).');

        $this->assertStringContainsString('href="https://docs.example.com/d/abc?x=1&amp;y=2"', $html);
        $this->assertStringContainsString('Apri il link (docs.example.com)', $html);
        $this->assertStringContainsString('data-url="https://docs.example.com/d/abc?x=1&amp;y=2"', $html);
        $this->assertStringContainsString('Copia indirizzo', $html);
        $this->assertStringStartsWith('Modulo: <a ', $html);
        $this->assertStringEndsWith('</button> (scaricalo).', $html);
    }

    public function test_punteggiatura_finale_resta_fuori_dal_link(): void
    {
        $html = (string) TestoConLink::html('Vedi https://example.com/a.');

        $this->assertStringContainsString('href="https://example.com/a"', $html);
        $this->assertStringEndsWith('</button>.', $html);
    }

    public function test_il_resto_del_testo_e_escapato(): void
    {
        $html = (string) TestoConLink::html('<b>grassetto</b> e <script>x</script>');

        $this->assertSame('&lt;b&gt;grassetto&lt;/b&gt; e &lt;script&gt;x&lt;/script&gt;', $html);
    }

    public function test_testo_vuoto(): void
    {
        $this->assertSame('', (string) TestoConLink::html(null));
    }
}
