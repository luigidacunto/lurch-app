<?php

namespace Tests\Feature;

use App\Filament\Resources\ComitatoRegionaleResource\Pages\EditComitatoRegionale;
use App\Models\ComitatoRegionale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Configurazione per regione sul comitato regionale: portale e account SMTP. DB di test: lurch_test. */
class ComitatoRegionaleConfigurazioneTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_smtp_cifrata_e_mantenuta_se_lasciata_vuota(): void
    {
        $cr = ComitatoRegionale::create(['nome' => 'CR Test', 'codice' => 'TST']);
        $this->actingAs(User::create(['name' => 's', 'email' => 's@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]));

        Livewire::test(EditComitatoRegionale::class, ['record' => $cr->getRouteKey()])
            ->assertSet('data.mail_password', null)
            ->fillForm([
                'url_portale'       => 'HTTPS://Lurch.Regione.example/',
                'mail_host'         => 'smtp.example',
                'mail_port'         => 587,
                'mail_username'     => 'utente',
                'mail_password'     => 'segreta',
                'mail_from_address' => 'lurch@regione.example',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $cr->refresh();
        $this->assertSame('https://lurch.regione.example', $cr->url_portale);
        $this->assertSame('segreta', $cr->mail_password);
        $this->assertNotSame('segreta', DB::table('comitati_regionali')->where('id', $cr->id)->value('mail_password'));
        $this->assertTrue($cr->haPostaConfigurata());

        // Seconda modifica senza toccare la password: resta quella salvata, mai rimandata al browser.
        Livewire::test(EditComitatoRegionale::class, ['record' => $cr->getRouteKey()])
            ->assertSet('data.mail_password', null)
            ->fillForm(['mail_from_name' => 'CRI Regione'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('segreta', $cr->refresh()->mail_password);
    }

    public function test_password_cifrata_con_un_altra_chiave_non_blocca_la_pagina(): void
    {
        $cr = ComitatoRegionale::create(['nome' => 'CR Test', 'codice' => 'TST', 'mail_host' => 'smtp.example', 'mail_port' => 587, 'mail_username' => 'u', 'mail_from_address' => 'a@b.example']);
        // Valore illeggibile con la chiave corrente (es. salvato da un'altra installazione sullo stesso DB).
        DB::table('comitati_regionali')->where('id', $cr->id)->update(['mail_password' => 'cifrata-altrove']);
        $this->actingAs(User::create(['name' => 's', 'email' => 's@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]));

        $this->get(\App\Filament\Resources\ComitatoRegionaleResource::getUrl('edit', ['record' => $cr]))->assertOk();
        $this->assertTrue($cr->refresh()->haPostaConfigurata());
        $this->assertNull($cr->mail_password, 'Valore illeggibile letto come null');

        // La si può sovrascrivere dal form (prima il salvataggio falliva con «The MAC is invalid»).
        Livewire::test(EditComitatoRegionale::class, ['record' => $cr->getRouteKey()])
            ->fillForm(['mail_password' => 'nuova'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('nuova', $cr->refresh()->mail_password);

        // Lasciando vuoto il campo resta la password salvata.
        Livewire::test(EditComitatoRegionale::class, ['record' => $cr->getRouteKey()])
            ->fillForm(['mail_from_name' => 'Altro nome'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('nuova', $cr->refresh()->mail_password);
    }
}
