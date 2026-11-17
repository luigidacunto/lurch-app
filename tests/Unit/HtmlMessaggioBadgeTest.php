<?php

namespace Tests\Unit;

use App\Services\HtmlMessaggioBadge;
use Tests\TestCase;

class HtmlMessaggioBadgeTest extends TestCase
{
    public function test_restano_solo_le_formattazioni_dell_editor_senza_attributi(): void
    {
        $casi = [
            '<p>Ciao <strong>bravo</strong> <em>e</em> <u>u</u></p><ol><li>uno</li></ol><ul><li>due</li></ul>'
                => '<p>Ciao <strong>bravo</strong> <em>e</em> <u>u</u></p><ol><li>uno</li></ol><ul><li>due</li></ul>',
            '<script>alert(1)</script><p>x</p>'                      => '<p>x</p>',
            // Il parser HTML5 sposta <style> fuori dal corpo: il tag sparisce, il contenuto resta testo inerte.
            '<style>body{display:none}</style>ok'                    => 'body{display:none}ok',
            '<img src="https://esterno.example/p.gif" onerror="alert(1)">testo' => 'testo',
            '<p style="position:fixed" onclick="x()">a <a href="javascript:alert(1)">link</a></p>' => '<p>a link</p>',
            '<div><span>dentro</span></div>'                         => 'dentro',
            '&lt;b&gt;'                                              => '&lt;b&gt;',
        ];

        foreach ($casi as $ingresso => $atteso) {
            $this->assertSame($atteso, HtmlMessaggioBadge::pulisci($ingresso), $ingresso);
        }
    }

    public function test_svg_e_iframe_non_lasciano_nulla_di_eseguibile(): void
    {
        foreach (['<svg onload="alert(1)"><text>t</text></svg>', '<iframe src="https://x.example"></iframe>', '<a href="javascript:alert(1)">x</a>'] as $ingresso) {
            $uscita = HtmlMessaggioBadge::pulisci($ingresso);
            $this->assertDoesNotMatchRegularExpression('/<(svg|iframe|a|script)\b|on\w+=|javascript:/i', $uscita, $ingresso);
        }
    }

    public function test_messaggio_senza_testo_visibile_e_vuoto(): void
    {
        $this->assertTrue(HtmlMessaggioBadge::vuoto(HtmlMessaggioBadge::pulisci('<p><br></p><script>x</script>')));
        $this->assertFalse(HtmlMessaggioBadge::vuoto(HtmlMessaggioBadge::pulisci('<p>ok</p>')));
    }
}
