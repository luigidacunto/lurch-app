<?php

namespace Tests\Feature;

use App\Esami\PermessiEsame;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriSchedaItinere;
use App\Models\TcriSchedaSemaforo;
use App\Models\TestConsegna;
use App\Models\User;
use App\Services\SocioCorrente;
use Database\Seeders\EsameModelliSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Esami: socio corrente (PWA/pannello) e permessi per ruolo d'esame. DB di test: lurch_test. */
class EsamePermessiTest extends TestCase
{
    use RefreshDatabase;

    private Esame $esame;
    private Corso $corso;
    /** @var array<string, Socio> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsameModelliSeeder::class);

        $now   = now();
        $cr    = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'TCRI', 'attivo' => true]);

        foreach (['direttore', 'presidente', 'docComm', 'docente', 'discente', 'nonAmmesso', 'ritirato', 'estraneo'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k, 'cognome' => 'Test', 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
        }

        $this->corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso TCRI', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id, 'presidente_socio_id' => $this->s['presidente']->id,
            'catalogo_corso_id' => $catalogo->id,
        ]);
        $this->corso->docenti()->attach([$this->s['docComm']->id, $this->s['docente']->id]);
        $this->iscrivi('discente');
        $this->iscrivi('nonAmmesso', ['non_ammesso' => true, 'non_ammesso_motivo' => 'assenze']);
        $this->iscrivi('ritirato', ['stato' => 'ritirato']);

        $this->esame = Esame::creaPerCorso($this->corso, 'Esame', 60);
        $this->esame->docentiCommissione()->create(['socio_id' => $this->s['docComm']->id]);
    }

    private function iscrivi(string $k, array $extra = []): void
    {
        Iscrizione::create($extra + ['corso_id' => $this->corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(), 'stato' => 'pending']);
    }

    private function permessi(string $k): PermessiEsame
    {
        return PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id);
    }

    private function semaforo(string $docente, ?Esame $esame = null): TcriSchedaSemaforo
    {
        return TcriSchedaSemaforo::create([
            'laboratorio_id'    => ($esame ?? $this->esame)->laboratori()->first()->id,
            'discente_socio_id' => $this->s['discente']->id,
            'docente_socio_id'  => $this->s[$docente]->id,
        ]);
    }

    private function itinere(string $docente): TcriSchedaItinere
    {
        return TcriSchedaItinere::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['discente']->id, 'docente_socio_id' => $this->s[$docente]->id]);
    }

    private function attiva(): void
    {
        $this->esame->attiva();
    }

    public function test_ruoli_letti_da_corso_ed_esame(): void
    {
        $this->assertEqualsCanonicalizing(['direttore', 'docente'], $this->permessi('direttore')->ruoli());
        $this->assertSame(['presidente'], $this->permessi('presidente')->ruoli());
        $this->assertEqualsCanonicalizing(['docente_commissione', 'docente'], $this->permessi('docComm')->ruoli());
        $this->assertSame(['docente'], $this->permessi('docente')->ruoli());
        $this->assertSame(['discente'], $this->permessi('discente')->ruoli());
        $this->assertSame(['discente'], $this->permessi('nonAmmesso')->ruoli());
        $this->assertSame([], $this->permessi('ritirato')->ruoli());
        $this->assertSame([], $this->permessi('estraneo')->ruoli());
        $this->assertSame([], PermessiEsame::per($this->esame, null)->ruoli());

        foreach (['direttore', 'presidente', 'docComm'] as $k) {
            $this->assertTrue($this->permessi($k)->eCommissario(), $k);
        }
        foreach (['docente', 'discente', 'estraneo'] as $k) {
            $this->assertFalse($this->permessi($k)->eCommissario(), $k);
        }
    }

    public function test_bozza_invisibile_poi_visibile_e_scope_sql_coincide(): void
    {
        $tutti = array_keys($this->s);

        foreach ($tutti as $k) {
            $this->assertFalse($this->permessi($k)->vedeEsame(), "bozza: $k");
            $this->assertFalse(Esame::query()->visibiliAlSocio($this->s[$k]->id)->exists(), "bozza sql: $k");
        }

        $this->attiva();
        // Discenti esclusi: vedranno l'esame solo dall'avvio del test per loro.
        $attesi = ['direttore', 'presidente', 'docComm', 'docente'];
        foreach ($tutti as $k) {
            $php = $this->permessi($k)->vedeEsame();
            $sql = Esame::query()->visibiliAlSocio($this->s[$k]->id)->exists();
            $this->assertSame(in_array($k, $attesi, true), $php, "php: $k");
            $this->assertSame($php, $sql, "php e sql discordano: $k");
        }
        $this->assertFalse(Esame::query()->visibiliAlSocio(null)->exists());
    }

    /** Il discente AMMESSO con il test avviato online vede l'esame (e solo quello); regola PHP e SQL coincidono per ogni ruolo. */
    public function test_discente_con_test_avviato_vede_l_esame_e_php_e_sql_coincidono(): void
    {
        $this->attiva();
        $avvio = fn (string $k, array $extra = []) => \App\Models\TestAvvio::create($extra + [
            'esame_id' => $this->esame->id, 'discente_socio_id' => $this->s[$k]->id, 'modalita' => 'online',
            'avviato_il' => now(), 'token' => (string) Str::uuid(),
        ]);
        $avvio('discente');
        $avvio('nonAmmesso');
        $avvio('ritirato');

        foreach (array_keys($this->s) as $k) {
            $php = $this->permessi($k)->vedeEsameInPwa();
            $sql = Esame::query()->visibiliAlSocio($this->s[$k]->id)->exists();
            $this->assertSame(in_array($k, ['direttore', 'presidente', 'docComm', 'docente', 'discente'], true), $php, "php: $k");
            $this->assertSame($php, $sql, "php e sql discordano: $k");
        }
        // vedeEsame() resta la regola del personale: il discente avviato non passa mai da lì (porta delle pagine delle schede)
        $this->assertFalse($this->permessi('discente')->vedeEsame());
        $this->assertTrue($this->permessi('discente')->vedeTestComeDiscente());
        $this->assertFalse($this->permessi('discente')->vedeTestScritto());

        // disattivato, cartaceo o esame in bozza: niente
        \App\Models\TestAvvio::where('discente_socio_id', $this->s['discente']->id)->update(['avviato_il' => null]);
        $this->assertFalse($this->permessi('discente')->vedeEsameInPwa());
        $this->assertFalse(Esame::query()->visibiliAlSocio($this->s['discente']->id)->exists());
        \App\Models\TestAvvio::where('discente_socio_id', $this->s['discente']->id)->update(['avviato_il' => now(), 'modalita' => 'cartaceo', 'motivo_cartaceo' => 'DSA']);
        $this->assertFalse($this->permessi('discente')->vedeEsameInPwa());
        $this->assertFalse(Esame::query()->visibiliAlSocio($this->s['discente']->id)->exists());
    }

    public function test_docente_semplice_vede_e_modifica_solo_le_proprie_schede(): void
    {
        $this->attiva();
        foreach ([$this->semaforo('docente'), $this->itinere('docente')] as $propria) {
            $this->assertTrue($this->permessi('docente')->vedeScheda($propria));
            $this->assertTrue($this->permessi('docente')->modificaScheda($propria));
        }
        foreach ([$this->semaforo('docComm'), $this->itinere('docComm')] as $altrui) {
            $this->assertFalse($this->permessi('docente')->vedeScheda($altrui));
            $this->assertFalse($this->permessi('docente')->modificaScheda($altrui));
            $this->assertFalse($this->permessi('discente')->vedeScheda($altrui));
        }
    }

    public function test_commissione_vede_tutto_ma_modifica_solo_le_proprie(): void
    {
        $this->attiva();
        $delDocente = $this->semaforo('docente');
        $delDirettore = $this->itinere('direttore');

        foreach (['direttore', 'presidente', 'docComm'] as $k) {
            $this->assertTrue($this->permessi($k)->vedeScheda($delDocente), $k);
            $this->assertFalse($this->permessi($k)->modificaScheda($delDocente), $k);
        }
        $this->assertTrue($this->permessi('direttore')->modificaScheda($delDirettore));
    }

    public function test_bozza_e_chiuso_bloccano_le_modifiche(): void
    {
        $scheda = $this->semaforo('docente');
        $this->assertFalse($this->permessi('docente')->modificaScheda($scheda), 'bozza');

        $this->attiva();
        $this->assertTrue($this->permessi('docente')->modificaScheda($scheda), 'in corso');

        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);
        $this->assertFalse($this->permessi('docente')->modificaScheda($scheda), 'chiuso');
        $this->assertTrue($this->permessi('docente')->vedeScheda($scheda), 'chiuso: resta leggibile');
    }

    public function test_scheda_di_un_altro_esame_non_conta(): void
    {
        $this->attiva();
        $altroCorso = $this->corso->replicate(['numero_protocollo'])->fill(['numero_protocollo' => 'Y/' . uniqid()]);
        $altroCorso->save();
        $altro  = Esame::creaPerCorso($altroCorso, 'Altro', 60);
        $scheda = $this->semaforo('docComm', $altro);

        $this->assertFalse($this->permessi('presidente')->vedeScheda($scheda));
        $this->assertFalse($this->permessi('docComm')->modificaScheda($scheda));
    }

    public function test_discente_non_vede_mai_le_valutazioni_che_lo_riguardano(): void
    {
        $this->attiva();
        foreach ([$this->semaforo('docente'), $this->itinere('direttore')] as $suLui) {
            $this->assertSame($this->s['discente']->id, $suLui->discente_socio_id);
            $this->assertFalse($this->permessi('discente')->vedeScheda($suLui));
            $this->assertFalse($this->permessi('discente')->modificaScheda($suLui));
        }
    }

    public function test_consegne_del_test(): void
    {
        $this->attiva();
        $mia   = TestConsegna::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['discente']->id, 'consegnato_il' => now()]);
        $altra = TestConsegna::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['nonAmmesso']->id, 'consegnato_il' => now()]);

        // Il discente non vede mai il risultato, nemmeno il proprio (decisione 18/10).
        $this->assertFalse($this->permessi('discente')->vedeConsegnaTest($mia));
        $this->assertFalse($this->permessi('discente')->vedeConsegnaTest($altra));
        $this->assertFalse($this->permessi('docente')->vedeConsegnaTest($mia));
        $this->assertTrue($this->permessi('presidente')->vedeConsegnaTest($altra));
    }

    public function test_socio_corrente_dipende_dal_contesto(): void
    {
        $utente = User::create(['name' => 'u', 'email' => 'u' . uniqid() . '@t.it', 'password' => 'x', 'ruolo' => 'operatore', 'attivo' => true, 'socio_id' => $this->s['direttore']->id]);

        $this->assertNull(SocioCorrente::id());
        $this->actingAs($utente);
        $this->assertSame($this->s['direttore']->id, SocioCorrente::id());

        // Stesso browser: pannello come direttore, PWA come docente semplice → nella PWA vale il docente.
        $this->attiva();
        $this->withSession(['app_socio_id' => $this->s['docente']->id])
            ->get(route('app.esami'))
            ->assertOk()
            ->assertSee('Docente')
            ->assertDontSee('Commissione');
    }

    public function test_pwa_elenco_e_link_in_home(): void
    {
        $this->attiva();

        $this->withSession(['app_socio_id' => $this->s['docente']->id])->get(route('app.home'))->assertSee('I tuoi esami');
        $this->withSession(['app_socio_id' => $this->s['docente']->id])->get(route('app.esami'))->assertSee('Docente')->assertSee('Esame');
        $this->withSession(['app_socio_id' => $this->s['docComm']->id])->get(route('app.esami'))->assertSee('Commissione');

        $this->withSession(['app_socio_id' => $this->s['discente']->id])->get(route('app.home'))->assertDontSee('I tuoi esami');
        $this->withSession(['app_socio_id' => $this->s['estraneo']->id])->get(route('app.home'))->assertDontSee('I tuoi esami');
        $this->withSession(['app_socio_id' => $this->s['estraneo']->id])->get(route('app.esami'))->assertOk()->assertSee('Non hai esami in corso');
    }
}
