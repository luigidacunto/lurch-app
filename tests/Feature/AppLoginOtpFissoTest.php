<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\Socio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppLoginOtpFissoTest extends TestCase
{
    use RefreshDatabase;

    private function richiediCodice(): OtpCode
    {
        Mail::fake();
        Socio::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80E12H501Z',
            'email' => 'mario@example.invalid', 'data_nascita' => '1980-05-12',
        ]);

        // In ambiente production il CSRF è attivo anche nei test: qui interessa solo il codice, non il token.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('app.login.post'), ['email' => 'mario@example.invalid'])->assertRedirect();

        return OtpCode::firstOrFail();
    }

    public function test_con_otp_fisso_configurato_il_codice_e_quello_fisso(): void
    {
        config(['app.otp_fisso' => '123456']);

        $this->assertTrue(Hash::check('123456', $this->richiediCodice()->codice));
    }

    public function test_in_produzione_il_codice_fisso_viene_ignorato(): void
    {
        config(['app.otp_fisso' => '123456']);
        $this->app['env'] = 'production';

        $this->assertFalse(Hash::check('123456', $this->richiediCodice()->codice));
    }
}
