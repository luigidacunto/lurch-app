<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Piè di pagina dei moduli pubblici: versioni del server e ambiente mai in produzione. */
class FormFooterTest extends TestCase
{
    public function test_in_produzione_niente_versioni_del_server(): void
    {
        $this->app['env'] = 'production';
        $html = Blade::render('<x-form-footer />');

        $this->assertStringContainsString('L.U.R.C.H.', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertStringNotContainsString('Ambiente', $html);
    }

    public function test_fuori_produzione_versioni_visibili(): void
    {
        $this->app['env'] = 'local';

        $this->assertStringContainsString('Ambiente: ', Blade::render('<x-form-footer />'));
    }
}
