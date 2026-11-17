<?php

namespace Tests\Feature;

use App\Models\Socio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppResetSessioniTest extends TestCase
{
    use RefreshDatabase;

    private function socio(): Socio
    {
        return Socio::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80E12H501Z',
            'email' => 'mario@example.invalid', 'data_nascita' => '1980-05-12',
        ]);
    }

    public function test_cookie_senza_timestamp_valido_finche_non_c_e_un_reset_e_registra_attivita(): void
    {
        $socio = $this->socio();

        $this->withCookie('app_remember', (string) $socio->id)
            ->get(route('app.home'))->assertOk();

        $this->assertNotNull($socio->fresh()->ultima_attivita_app);
    }

    public function test_dopo_il_reset_sessione_e_cookie_emessi_prima_non_valgono(): void
    {
        $socio = $this->socio();
        $prima = now()->subMinute()->getTimestamp();
        $socio->resetSessioni();

        $this->withSession(['app_socio_id' => $socio->id, 'app_login_at' => $prima])
            ->get(route('app.home'))->assertRedirect(route('app.login'));

        $this->flushSession();
        $this->withCookie('app_remember', $socio->id . '.' . $prima)
            ->get(route('app.home'))->assertRedirect(route('app.login'));

        $this->withCookie('app_remember', (string) $socio->id)
            ->get(route('app.home'))->assertRedirect(route('app.login'));
    }

    public function test_dopo_il_reset_un_nuovo_login_e_valido(): void
    {
        $socio = $this->socio();
        $socio->resetSessioni();

        $this->withSession(['app_socio_id' => $socio->id, 'app_login_at' => now()->addSecond()->getTimestamp()])
            ->get(route('app.home'))->assertOk();
    }
}
