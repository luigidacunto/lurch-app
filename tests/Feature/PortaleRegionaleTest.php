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
