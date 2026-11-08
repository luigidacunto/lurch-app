<?php

namespace Tests\Feature;

use App\Services\PrivacyPlaceholderResolver;
use Tests\TestCase;

class DatiInstallazioneTest extends TestCase
{
    public function test_il_responsabile_privacy_viene_dalla_configurazione(): void
    {
        config(['app.privacy_responsabile' => 'Mario Rossi']);
        $this->assertSame('Gestito da Mario Rossi.', PrivacyPlaceholderResolver::sostituisci('Gestito da {responsabile}.', null));

        config(['app.privacy_responsabile' => null]);
        $this->assertSame('Gestito da <informazione mancante>.', PrivacyPlaceholderResolver::sostituisci('Gestito da {responsabile}.', null));
    }

    public function test_la_pagina_iniziale_mostra_il_contatto_solo_se_configurato(): void
    {
        config(['app.contatto_email' => 'assistenza@esempio.test']);
        $this->get('/')->assertOk()->assertSee('contact-email', false)->assertSee("'assistenza'", false)->assertSee("'esempio.test'", false);

        config(['app.contatto_email' => null]);
        $this->get('/')->assertOk()->assertDontSee('contact-email', false);
    }
}
