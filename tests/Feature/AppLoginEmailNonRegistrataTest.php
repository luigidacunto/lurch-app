<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Login dell'app: un'email non registrata ha le stesse risposte di una registrata (nessuna enumerazione). */
class AppLoginEmailNonRegistrataTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_non_registrata_porta_alla_pagina_del_codice_senza_inviare_nulla(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('app.login.post'), ['email' => 'nessuno@example.invalid'])
            ->assertRedirect(route('app.verifica'));
        $this->get(route('app.verifica'))->assertOk()->assertSee('nessuno@example.invalid');

        $this->assertSame(0, OtpCode::count());
        Mail::assertNothingSent();

        $this->from(route('app.verifica'))->post(route('app.verifica.post'), ['codice' => '123456'])
            ->assertSessionHasErrors(['codice' => 'Codice errato. Ti rimangono 4 tentativi.']);
        $this->assertNull(session('app_socio_id'));
    }
}
