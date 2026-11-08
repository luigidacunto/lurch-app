<?php

namespace Tests\Feature;

use App\Models\Corso;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Traits\HasGaiaPaste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Esami TCRI: direttore, presidente e docenti sul corso; non ammissione sull'iscrizione. DB di test: lurch_test. */
class CorsoDirettoreDocentiTest extends TestCase
{
    use RefreshDatabase;

    private int $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $cr = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR Test', 'created_at' => $now, 'updated_at' => $now]);
        $this->evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function corso(array $extra = []): Corso
    {
        return Corso::create($extra + [
            'evento_id' => $this->evento, 'nome' => 'Corso', 'numero_protocollo' => 'X/2026/T/1',
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
        ]);
    }

    private function socio(string $nome, string $cognome, string $cf): Socio
    {
        return Socio::create(['nome' => $nome, 'cognome' => $cognome, 'codice_fiscale' => $cf]);
    }

    public function test_colonne_nuove_non_toccano_i_corsi_esistenti(): void
    {
        $corso = $this->corso(['direttore_corso' => 'Mario Vecchio']);

        $corso->refresh();
        $this->assertNull($corso->direttore_socio_id);
        $this->assertNull($corso->presidente_socio_id);
        $this->assertSame('Mario Vecchio', $corso->direttore_corso);

        // Un corso vecchio con il solo testo si salva com'è.
        $corso->update(['descrizione' => 'x']);
        $this->assertSame('Mario Vecchio', $corso->fresh()->direttore_corso);
    }

    public function test_scelta_del_direttore_valorizza_il_testo_e_il_cambio_lo_aggiorna(): void
    {
        $a = $this->socio('Anna', 'Rossi', 'RSSNNA80A41H501X');
        $b = $this->socio('Luca', 'Bianchi', 'BNCLCU80A01H501Y');
        $corso = $this->corso(['direttore_corso' => 'Testo vecchio']);

        $corso->update(['direttore_socio_id' => $a->id]);
        $this->assertSame('Anna Rossi', $corso->fresh()->direttore_corso);
        $this->assertSame($a->id, $corso->fresh()->staffEsame()['direttore']->id);

        $corso->update(['direttore_socio_id' => $b->id]);
        $this->assertSame('Luca Bianchi', $corso->fresh()->direttore_corso);
    }

    public function test_ricerca_soglia_quattro_caratteri_e_nessun_codice_fiscale_nelle_etichette(): void
    {
        $s = $this->socio('Anna', 'Rossi', 'RSSNNA80A41H501X');
        $this->socio('Luca', 'Bianchi', 'BNCLCU80A01H501Y');

        $this->assertSame([], Socio::cercaPerSelezione('Ros'));
        $this->assertSame([], Socio::cercaPerSelezione('   '));

        foreach (['Rossi', 'anna', 'RSSN', 'anna rossi'] as $q) {
            $r = Socio::cercaPerSelezione($q);
            $this->assertSame([$s->id], array_keys($r), "ricerca '{$q}'");
            $this->assertStringNotContainsString('RSSNNA', $r[$s->id]);
            $this->assertStringContainsString('Rossi Anna', $r[$s->id]);
        }

        // Caratteri jolly escapati: "____" non deve corrispondere a tutti.
        $this->assertSame([], Socio::cercaPerSelezione('____'));
        $this->assertSame([], Socio::cercaPerSelezione('%%%%'));
    }

    public function test_docenti_da_zero_a_n_e_protezione_di_chi_ha_gia_schede(): void
    {
        $d1 = $this->socio('Doc', 'Uno', 'DCUNO80A01H501A');
        $d2 = $this->socio('Doc', 'Due', 'DCDUE80A01H501B');
        $corso = $this->corso();
        $this->assertCount(0, $corso->docenti);
        $this->assertCount(0, $corso->docentiNonRimovibili());

        $corso->docenti()->sync([$d1->id, $d2->id]);
        $this->assertCount(2, $corso->fresh()->docenti);
        $corso->docenti()->sync([$d1->id]);
        $this->assertSame([$d1->id], $corso->fresh()->docenti->pluck('id')->all());

        // Senza esame nessuno è bloccato; con l'esame, il docente in commissione no.
        $now = now();
        DB::table('esame_modelli')->insert(['tipo_codice' => 'TCRI', 'versione' => 1, 'struttura' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        $esame = DB::table('esami')->insertGetId([
            'corso_id' => $corso->id, 'titolo' => 'E', 'tipo_codice' => 'TCRI', 'modello_versione' => 1,
            'lap' => 60, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('esame_docenti_commissione')->insert(['esame_id' => $esame, 'socio_id' => $d1->id, 'created_at' => $now, 'updated_at' => $now]);

        $this->assertSame([$d1->id], $corso->fresh()->docentiNonRimovibili()->all());
    }

    public function test_form_di_modifica_salva_direttore_testo_e_docenti_e_blocca_la_rimozione(): void
    {
        $user = \App\Models\User::create(['name' => 'Su', 'email' => 'su@test.local', 'ruolo' => 'superadmin', 'attivo' => true]);
        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $dir = $this->socio('Anna', 'Rossi', 'RSSNNA80A41H501X');
        $pres = $this->socio('Piero', 'Verdi', 'VRDPRI80A01H501Z');
        $d1 = $this->socio('Doc', 'Uno', 'DCUNO80A01H501A');
        $d2 = $this->socio('Doc', 'Due', 'DCDUE80A01H501B');
        $corso = $this->corso(['direttore_corso' => 'Testo vecchio']);

        $pagina = \Livewire\Livewire::test(\App\Filament\Resources\CorsoResource\Pages\EditCorso::class, ['record' => $corso->getRouteKey()]);
        $pagina->assertFormSet(['direttore_corso' => 'Testo vecchio'])
            ->fillForm(['direttore_socio_id' => $dir->id, 'presidente_socio_id' => $pres->id, 'docenti' => [$d1->id, $d2->id]])
            ->assertFormSet(['direttore_corso' => 'Anna Rossi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $corso->refresh();
        $this->assertSame('Anna Rossi', $corso->direttore_corso);
        $this->assertSame($pres->id, $corso->presidente_socio_id);
        $this->assertEqualsCanonicalizing([$d1->id, $d2->id], $corso->docenti()->pluck('socio_id')->all());

        // Togliere un docente senza schede va bene; con commissione/schede no.
        $now = now();
        DB::table('esame_modelli')->insert(['tipo_codice' => 'TCRI', 'versione' => 1, 'struttura' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        $esame = DB::table('esami')->insertGetId([
            'corso_id' => $corso->id, 'titolo' => 'E', 'tipo_codice' => 'TCRI', 'modello_versione' => 1,
            'lap' => 60, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('esame_docenti_commissione')->insert(['esame_id' => $esame, 'socio_id' => $d1->id, 'created_at' => $now, 'updated_at' => $now]);

        $pagina = \Livewire\Livewire::test(\App\Filament\Resources\CorsoResource\Pages\EditCorso::class, ['record' => $corso->getRouteKey()]);
        $pagina->fillForm(['docenti' => [$d2->id]])->call('save')->assertHasFormErrors(['docenti']);
        $this->assertCount(2, $corso->docenti()->get());

        $pagina->fillForm(['docenti' => [$d1->id]])->call('save')->assertHasNoFormErrors();
        $this->assertSame([$d1->id], $corso->docenti()->pluck('socio_id')->all());
    }

    public function test_direttore_obbligatorio_solo_nella_creazione(): void
    {
        $user = \App\Models\User::create(['name' => 'Su', 'email' => 'su@test.local', 'ruolo' => 'superadmin', 'attivo' => true]);
        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::test(\App\Filament\Resources\CorsoResource\Pages\CreateCorso::class)
            ->fillForm(['evento_id' => $this->evento, 'nome' => 'Nuovo', 'numero_protocollo' => 'X/2026/T/2',
                'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'ora_inizio' => '09:00', 'ora_fine' => '18:00'])
            ->call('create')
            ->assertHasFormErrors(['direttore_socio_id' => 'required']);
    }

    public function test_corso_con_esame_non_e_cancellabile(): void
    {
        $corso = $this->corso();
        $this->assertFalse($corso->hasDatiCorrelati());

        $now = now();
        DB::table('esame_modelli')->insert(['tipo_codice' => 'TCRI', 'versione' => 1, 'struttura' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('esami')->insert([
            'corso_id' => $corso->id, 'titolo' => 'E', 'tipo_codice' => 'TCRI', 'modello_versione' => 1,
            'lap' => 60, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->assertTrue($corso->fresh()->hasDatiCorrelati());
    }

    public function test_incolla_gaia_scrive_il_direttore_solo_se_non_e_scelto_un_socio(): void
    {
        $nuovoOggetto = fn (array $data) => new class($data) {
            use HasGaiaPaste;

            public function __construct(public array $data) {}

            public function dispatch(...$args): void {}
        };

        // Nessun socio scelto: il nome incollato resta nel testo, da cercare poi nella select.
        $senzaSocio = $nuovoOggetto(['direttore_corso' => 'invariato', 'direttore_socio_id' => null]);
        $senzaSocio->riempiDaGaia(['corso' => 'Corso GAIA', 'direttore' => 'Pinco Pallino']);
        $this->assertSame('Pinco Pallino', $senzaSocio->data['direttore_corso']);
        $this->assertSame('Corso GAIA', $senzaSocio->data['nome']);

        // Socio già scelto: il testo lo riscrive il socio, l'incolla non lo tocca.
        $conSocio = $nuovoOggetto(['direttore_corso' => 'invariato', 'direttore_socio_id' => 42]);
        $conSocio->riempiDaGaia(['corso' => 'Corso GAIA', 'direttore' => 'Pinco Pallino']);
        $this->assertSame('invariato', $conSocio->data['direttore_corso']);
    }

    public function test_iscrizioni_esistenti_risultano_ammesse(): void
    {
        $corso = $this->corso();
        $socio = $this->socio('Anna', 'Rossi', 'RSSNNA80A41H501X');
        $iscrizione = Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $socio->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'stato' => 'ritirato']);

        $iscrizione->refresh();
        $this->assertFalse($iscrizione->isNonAmmesso());
        $this->assertNull($iscrizione->non_ammesso_motivo);

        $iscrizione->update(['non_ammesso' => true, 'non_ammesso_motivo' => 'Assenze']);
        $this->assertTrue($iscrizione->fresh()->isNonAmmesso());
    }
}
