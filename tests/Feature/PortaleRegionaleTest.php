<?php

namespace Tests\Feature;

use App\Models\ComitatoRegionale;
use App\Models\Corso;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Services\PortaleRegionale;
use App\Services\TemplatePlaceholders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Link pubblici sul portale della regione del dato. DB di test: lurch_test. */
class PortaleRegionaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_regione_con_portale_usa_il_suo_dominio_altrimenti_l_indirizzo_generale(): void
    {
        config(['app.url' => 'https://lurch.generale.example/']);
        $token = (string) Str::uuid();
        $conPortale   = ComitatoRegionale::create(['nome' => 'A', 'codice' => 'AAA', 'url_portale' => 'https://lurch.regione-a.example']);
        $senzaPortale = ComitatoRegionale::create(['nome' => 'B', 'codice' => 'BBB']);

        // Anche da una richiesta arrivata su un altro dominio.
        $this->get('https://altro.example/');

        $this->assertSame('https://lurch.regione-a.example/form/' . $token, PortaleRegionale::route($conPortale->id, 'form.show', $token));
        $this->assertSame('https://lurch.generale.example/form/' . $token, PortaleRegionale::route($senzaPortale->id, 'form.show', $token));
        $this->assertSame('https://lurch.generale.example/form/' . $token, PortaleRegionale::route(null, 'form.show', $token));
    }

    public function test_regione_riconosciuta_dal_dominio_della_richiesta(): void
    {
        $toscana = ComitatoRegionale::create(['nome' => 'T', 'codice' => 'TTT', 'url_portale' => 'https://toscana.lurch.example']);
        ComitatoRegionale::create(['nome' => 'C', 'codice' => 'CCC', 'url_portale' => 'https://campania.lurch.example']);

        $this->assertTrue($toscana->is(PortaleRegionale::perHost('Toscana.Lurch.example')));
        $this->assertNull(PortaleRegionale::perHost('lurch.example'));
        $this->assertNull(PortaleRegionale::perHost('127.0.0.1:8005'));

        $this->get('https://toscana.lurch.example/');
        $this->assertTrue($toscana->is(PortaleRegionale::daRichiesta()));
    }

    public function test_home_e_login_mostrano_la_regione_del_dominio(): void
    {
        ComitatoRegionale::create(['nome' => 'Comitato Regionale Prova', 'codice' => 'PRV', 'url_portale' => 'https://prova.lurch.example']);

        $this->get('https://prova.lurch.example/')->assertOk()->assertSee('Comitato Regionale Prova');
        $this->get('https://prova.lurch.example/admin-login')->assertOk()->assertSee('Croce Rossa Italiana · Comitato Regionale Prova', false);

        // Con il logo: solo il logo (il nome resta nel testo alternativo), niente nome ripetuto.
        ComitatoRegionale::where('codice', 'PRV')->update(['logo' => 'comitati-regionali/prova.svg']);
        \Illuminate\Support\Once::flush(); // la regione è memorizzata per richiesta: qui le richieste sono nello stesso test
        $this->get('https://prova.lurch.example/')->assertOk()
            ->assertSee('alt="Comitato Regionale Prova"', false)
            ->assertDontSee('<span>Comitato Regionale Prova</span>', false);
        $this->get('https://prova.lurch.example/admin-login')->assertOk()
            ->assertSee('alt="Comitato Regionale Prova"', false)
            ->assertDontSee('Croce Rossa Italiana · Comitato Regionale Prova', false);

        // Pannello: logo della regione nella barra in alto, accanto al marchio L.U.R.C.H.
        $this->actingAs(\App\Models\User::create(['name' => 's', 'email' => 's@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]));
        $this->get('https://prova.lurch.example/admin')->assertOk()
            ->assertSee('class="lurch-logo-regione"', false)
            ->assertSee('lurch-marchio--con-logo-regione', false);
        $this->get('https://lurch.example/admin')->assertOk()->assertDontSee('class="lurch-logo-regione"', false);
        auth()->logout();

        // Indirizzo generale: nessuna regione.
        $this->get('https://lurch.example/')->assertOk()->assertDontSee('Comitato Regionale Prova');
        $this->get('https://lurch.example/admin-login')->assertOk()->assertDontSee('Comitato Regionale Prova');
    }

    public function test_responsabile_privacy_dalla_regione_con_ripiego_generale(): void
    {
        config(['app.privacy_responsabile' => 'Responsabile generale']);
        $conDpo   = ComitatoRegionale::create(['nome' => 'A', 'codice' => 'AAA', 'privacy_responsabile' => 'DPO regione A']);
        $senzaDpo = ComitatoRegionale::create(['nome' => 'B', 'codice' => 'BBB']);
        $locale   = \App\Models\ComitatoLocale::create(['nome' => 'Locale A', 'comitato_regionale_id' => $conDpo->id]);

        $this->assertSame('DPO regione A', \App\Services\PrivacyPlaceholderResolver::sostituisci('{responsabile}', $conDpo));
        $this->assertSame('DPO regione A', \App\Services\PrivacyPlaceholderResolver::sostituisci('{responsabile}', null, $locale));
        $this->assertSame('Responsabile generale', \App\Services\PrivacyPlaceholderResolver::sostituisci('{responsabile}', $senzaDpo));
        $this->assertSame('Responsabile generale', \App\Services\PrivacyPlaceholderResolver::sostituisci('{responsabile}', null));
    }

    public function test_link_form_nelle_mail_usa_la_regione_dell_evento(): void
    {
        $now = now();
        $cr  = ComitatoRegionale::create(['nome' => 'A', 'codice' => 'AAA', 'url_portale' => 'https://lurch.regione-a.example']);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr->id, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso', 'numero_protocollo' => 'X/2026/T/1',
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
        ]);
        $socio = Socio::create(['nome' => 'Mario', 'cognome' => 'Prova', 'codice_fiscale' => 'PRVMRA80A01H501U']);
        $iscrizione = Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $socio->id, 'token' => (string) Str::uuid(), 'stato' => 'pending']);

        $testo = TemplatePlaceholders::risolvi('{link_form}', $iscrizione);

        $this->assertStringContainsString('https://lurch.regione-a.example/form/' . $iscrizione->token, $testo);
    }
}
