<?php

namespace Tests\Feature;

use Tests\TestCase;

class UserscriptGaiaBridgeTest extends TestCase
{
    public function test_lo_userscript_usa_l_indirizzo_dell_installazione(): void
    {
        $base = rtrim(url('/'), '/');

        $risposta = $this->get('/tampermonkey/lurch-gaia-bridge.user.js');

        $risposta->assertOk();
        $this->assertStringContainsString('text/javascript', $risposta->headers->get('Content-Type'));
        $risposta->assertSee("@match        {$base}/admin/*", false);
        $risposta->assertSee("@updateURL    {$base}/tampermonkey/lurch-gaia-bridge.user.js", false);
        $risposta->assertDontSee('__LURCH_URL__', false);
    }
}
