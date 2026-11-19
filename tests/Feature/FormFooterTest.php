<?php

namespace Tests\Feature;

use App\Models\ComitatoRegionale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Piè di pagina: versioni del server e ambiente mai in pubblico in produzione, nel pannello solo al superadmin. */
class FormFooterTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_pannello_versioni_solo_al_superadmin(): void
    {
        $regione = ComitatoRegionale::create(['nome' => 'Reg Test', 'codice' => 'RT', 'attivo' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'ruolo' => 'admin', 'attivo' => true, 'comitato_regionale_id' => $regione->id]);
        $super = User::create(['name' => 'Super', 'email' => 'super@test.local', 'ruolo' => 'superadmin', 'attivo' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('L.U.R.C.H.')->assertDontSee('Ambiente: ');
        $this->actingAs($super)->get('/admin')->assertOk()->assertSee('Ambiente: ');
    }
}
