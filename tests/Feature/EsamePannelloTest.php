<?php

namespace Tests\Feature;

use App\Filament\Resources\CorsoResource;
use App\Filament\Resources\EsameResource;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\EsameGruppo;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\EsameModelliSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Esami: creazione dal corso, visibilità, laboratori/gruppi, attivazione, cancellazione. DB di test: lurch_test. */
class EsamePannelloTest extends TestCase
{
    use RefreshDatabase;

    private int $cr1;
    private int $cr2;
    private int $cl1;
    private int $evento;
    private CatalogoCorso $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsameModelliSeeder::class);

        $now = now();
        $this->cr1 = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR 1', 'created_at' => $now, 'updated_at' => $now]);
        $this->cr2 = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR 2', 'created_at' => $now, 'updated_at' => $now]);
        $this->cl1 = DB::table('comitati_locali')->insertGetId(['comitato_regionale_id' => $this->cr1, 'nome' => 'CL 1', 'created_at' => $now, 'updated_at' => $now]);
        $this->evento = $this->nuovoEvento($this->cr1, $this->cl1);
        $this->catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'Modulo Trasversale Trainer CRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'Modulo TCRI', 'attivo' => true]);
    }

    private function nuovoEvento(int $cr, ?int $cl): int
    {
        $now = now();

        return DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'comitato_locale_id' => $cl, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function socio(string $nome): Socio
    {
        return Socio::create(['nome' => $nome, 'cognome' => 'Test', 'codice_fiscale' => strtoupper(substr(md5($nome), 0, 16))]);
    }

    private function corso(?int $evento = null, array $extra = []): Corso
    {
        return Corso::create($extra + [
            'evento_id' => $evento ?? $this->evento, 'nome' => 'Corso', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
        ]);
    }

    private function corsoPronto(?int $evento = null): Corso
    {
        return $this->corso($evento, ['direttore_socio_id' => $this->socio('Dir' . uniqid())->id, 'catalogo_corso_id' => $this->catalogo->id]);
    }

    private function utente(string $ruolo, array $extra = []): User
    {
        return User::create($extra + ['name' => $ruolo, 'email' => $ruolo . uniqid() . '@t.it', 'password' => 'x', 'ruolo' => $ruolo, 'attivo' => true]);
    }

    private function visibili(User $u): array
    {
        return Esame::query()->visibiliA($u)->pluck('id')->all();
    }

    public function test_creazione_richiede_direttore_e_tipo_e_non_si_ripete(): void
    {
        $senzaDirettore = $this->corso(null, ['catalogo_corso_id' => $this->catalogo->id]);
        $this->assertStringContainsString('direttore', Esame::motivoNonCreabile($senzaDirettore));

        $senzaTipo = $this->corso(null, ['direttore_socio_id' => $this->socio('D')->id]);
        $this->assertStringContainsString('tipo di corso', Esame::motivoNonCreabile($senzaTipo));

        $corso = $this->corsoPronto();
        $this->assertNull(Esame::motivoNonCreabile($corso));

        $esame = Esame::creaPerCorso($corso, 'Esame', 60);
        $this->assertSame('TCRI', $esame->tipo_codice);
        $this->assertSame('bozza', $esame->stato);
        $this->assertSame([1], $esame->laboratori()->pluck('numero')->all());
        $this->assertStringContainsString('già un esame', Esame::motivoNonCreabile($corso->fresh()));
    }

    public function test_tipo_senza_modello_non_permette_la_creazione(): void
    {
        $altro = CatalogoCorso::create(['codice' => 'ALTRO', 'nome' => 'Altro', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'Modulo TCRI', 'attivo' => true]);
        $corso = $this->corso(null, ['direttore_socio_id' => $this->socio('D')->id, 'catalogo_corso_id' => $altro->id]);

        $this->assertStringContainsString('modello', Esame::motivoNonCreabile($corso));
    }

    public function test_visibilita_per_ruolo_e_per_direzione(): void
    {
        $mio    = Esame::creaPerCorso($this->corsoPronto(), 'Mio', 60);
        $altroCr = Esame::creaPerCorso($this->corsoPronto($this->nuovoEvento($this->cr2, null)), 'Altra regione', 60);

        $this->assertEqualsCanonicalizing([$mio->id, $altroCr->id], $this->visibili($this->utente('superadmin')));
        $this->assertSame([$mio->id], $this->visibili($this->utente('admin', ['comitato_regionale_id' => $this->cr1])));
        $this->assertSame([$mio->id], $this->visibili($this->utente('admin_locale', ['comitato_regionale_id' => $this->cr1, 'comitato_locale_id' => $this->cl1])));

        // Operatore non direttore: nessun esame, anche assegnato all'evento.
        $operatore = $this->utente('operatore', ['comitato_regionale_id' => $this->cr1]);
        $operatore->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'corsi.view', 'guard_name' => 'web']));
        DB::table('evento_operatore')->insert(['evento_id' => $this->evento, 'user_id' => $operatore->id]);
        $this->assertSame([], $this->visibili($operatore));

        // Direttore operatore (senza permessi): vede il suo esame; admin di un'altra regione direttore di un corso fuori ambito lo vede.
        $direttore = $this->utente('operatore', ['socio_id' => $mio->corso->direttore_socio_id]);
        $this->assertSame([$mio->id], $this->visibili($direttore));
        $adminDirettore = $this->utente('admin', ['comitato_regionale_id' => $this->cr2, 'socio_id' => $mio->corso->direttore_socio_id]);
        $this->assertEqualsCanonicalizing([$mio->id, $altroCr->id], $this->visibili($adminDirettore));
    }

    public function test_direttore_operatore_vede_il_corso_ma_non_lo_modifica(): void
    {
        $corso = $this->corsoPronto();
        $altro = $this->corsoPronto();
        $this->actingAs($this->utente('operatore', ['socio_id' => $corso->direttore_socio_id]));

        $this->assertTrue(CorsoResource::canViewAny());
        $this->assertSame([$corso->id], CorsoResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(CorsoResource::puoGestireCorso($corso));
        $this->assertFalse(CorsoResource::puoGestireCorso($altro));
        $this->assertFalse(CorsoResource::canEdit($corso));
        $this->assertFalse(CorsoResource::canDelete($corso));
    }

    public function test_operatore_senza_direzione_ne_permessi_non_vede_corsi(): void
    {
        $this->corsoPronto();
        $this->actingAs($this->utente('operatore'));

        $this->assertFalse(CorsoResource::canViewAny());
        $this->assertFalse(EsameResource::canViewAny());
    }

    public function test_discenti_senza_ritirati_e_non_ammessi_segnalati(): void
    {
        $corso  = $this->corsoPronto();
        $esame  = Esame::creaPerCorso($corso, 'E', 60);
        $attivo = $this->socio('Attivo');
        $ritir  = $this->socio('Ritirato');
        $nonAmm = $this->socio('NonAmmesso');
        Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $attivo->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'stato' => 'pending']);
        Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $ritir->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'stato' => 'ritirato']);
        Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $nonAmm->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'stato' => 'pending', 'non_ammesso' => true, 'non_ammesso_motivo' => 'assenze']);

        $ids = $esame->discenti()->pluck('partecipante_id')->all();
        $this->assertEqualsCanonicalizing([$attivo->id, $nonAmm->id], $ids);
    }

    public function test_un_discente_in_un_solo_gruppo_per_laboratorio(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $lab1  = $esame->laboratori()->first();
        $lab2  = $esame->laboratori()->create(['numero' => 2]);
        $s     = $this->socio('Disc');

        $g = EsameGruppo::create(['esame_id' => $esame->id, 'laboratorio_id' => $lab1->id, 'nome' => 'A']);
        $g->discenti()->sync([$s->id]);

        $this->assertSame([$s->id], EsameGruppo::discentiGiaAssegnati($lab1->id, null, [$s->id]));
        $this->assertSame([], EsameGruppo::discentiGiaAssegnati($lab1->id, $g->id, [$s->id]), 'il gruppo stesso è escluso');
        $this->assertSame([], EsameGruppo::discentiGiaAssegnati($lab2->id, null, [$s->id]), 'altro laboratorio libero');
    }

    public function test_opzioni_discenti_escludono_gia_assegnati_e_tengono_i_ritirati_del_gruppo(): void
    {
        $corso = $this->corsoPronto();
        $esame = Esame::creaPerCorso($corso, 'E', 60);
        $lab1  = $esame->laboratori()->first();
        $lab2  = $esame->laboratori()->create(['numero' => 2]);
        [$a, $b, $r] = [$this->socio('Alfa'), $this->socio('Beta'), $this->socio('Ritirato')];
        foreach ([$a, $b, $r] as $s) {
            Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $s->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'stato' => 'pending']);
        }
        $g = EsameGruppo::create(['esame_id' => $esame->id, 'laboratorio_id' => $lab1->id, 'nome' => 'A']);
        $g->discenti()->sync([$a->id, $r->id]);
        Iscrizione::where('partecipante_id', $r->id)->update(['stato' => 'ritirato']);

        $this->assertSame([], EsameGruppo::opzioniDiscenti($esame, 0, null), 'senza laboratorio nessuna opzione');
        $this->assertSame([$b->id], array_keys(EsameGruppo::opzioniDiscenti($esame, $lab1->id, null)), 'nuovo gruppo: escluso chi è già nel lab 1');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_keys(EsameGruppo::opzioniDiscenti($esame, $lab2->id, null)), 'altro laboratorio: tutti tranne il ritirato');

        $proprio = EsameGruppo::opzioniDiscenti($esame, $lab1->id, $g);
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $r->id], array_keys($proprio), 'il gruppo vede i suoi, ritirato compreso');
        $this->assertStringEndsWith('(ritirato)', $proprio[$r->id]);
    }

    public function test_gruppo_senza_docenti_si_salva(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $g = EsameGruppo::create(['esame_id' => $esame->id, 'laboratorio_id' => $esame->laboratori()->first()->id, 'nome' => 'A']);

        $this->assertSame(1, $esame->gruppi()->doesntHave('docenti')->count());
        $this->assertTrue($g->exists);
    }

    public function test_attivazione_crea_snapshot_e_lap_resta_modificabile(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $this->assertNull($esame->motivoNonAttivabile());

        $esame->attiva();
        $esame->refresh();
        $this->assertSame('in_corso', $esame->stato);
        $this->assertSame(1, $esame->modello_snapshot['versione'] ?? $esame->modello_snapshot['versione'] ?? 1);
        $this->assertNotEmpty($esame->modello_snapshot);

        $esame->update(['lap' => 65]);
        $this->assertSame('65.00', $esame->fresh()->lap);
        $this->assertSame('L\'esame è già attivo.', $esame->motivoNonAttivabile());
    }

    public function test_attivazione_richiede_almeno_un_laboratorio(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $esame->laboratori()->delete();

        $this->assertSame('Serve almeno un laboratorio.', $esame->motivoNonAttivabile());
        $this->expectException(\RuntimeException::class);
        $esame->attiva();
    }

    public function test_cancellazione_solo_in_bozza_e_senza_dati_con_configurazione_a_cascata(): void
    {
        $super = $this->utente('superadmin');
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $lab   = $esame->laboratori()->first();
        EsameGruppo::create(['esame_id' => $esame->id, 'laboratorio_id' => $lab->id, 'nome' => 'A']);
        $esame->docentiCommissione()->create(['socio_id' => $this->socio('Doc')->id]);

        $this->actingAs($super);
        $this->assertTrue(EsameResource::canDelete($esame));

        $esame->delete();
        $this->assertSame(0, DB::table('esame_gruppi')->count());
        $this->assertSame(0, DB::table('esame_laboratori')->count());
        $this->assertSame(0, DB::table('esame_docenti_commissione')->count());

        $attivo = Esame::creaPerCorso($this->corsoPronto(), 'E2', 60);
        $attivo->attiva();
        $this->assertFalse(EsameResource::canDelete($attivo->fresh()));
    }

    public function test_corso_con_catalogo_facoltativo_e_corso_vecchio_invariato(): void
    {
        $vecchio = $this->corso();
        $this->assertNull($vecchio->fresh()->catalogo_corso_id);
        $vecchio->update(['descrizione' => 'x']);
        $this->assertNull($vecchio->fresh()->catalogo_corso_id);

        $vecchio->update(['catalogo_corso_id' => $this->catalogo->id]);
        $this->assertSame('TCRI', $vecchio->fresh()->catalogoCorso->codice);
    }

    public function test_le_pagine_dell_esame_si_aprono_per_superadmin_e_per_il_direttore(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $esame->docentiCommissione()->create(['socio_id' => $this->socio('Doc')->id]);
        EsameGruppo::create(['esame_id' => $esame->id, 'laboratorio_id' => $esame->laboratori()->first()->id, 'nome' => 'A']);

        $pagine = [
            EsameResource\Pages\EditEsame::class, EsameResource\Pages\GestioneEsame::class, EsameResource\Pages\DiscentiEsame::class,
            EsameResource\Pages\GestioneLaboratori::class,
        ];

        $direttore = $this->utente('operatore', ['socio_id' => $esame->corso->direttore_socio_id]);
        foreach ([$this->utente('superadmin'), $direttore] as $utente) {
            $this->actingAs($utente);
            \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

            \Livewire\Livewire::test(EsameResource\Pages\ListEsami::class)->assertSuccessful();
            foreach ($pagine as $pagina) {
                \Livewire\Livewire::test($pagina, ['record' => $esame->getRouteKey()])->assertSuccessful();
            }
            \Livewire\Livewire::test(CorsoResource\Pages\GestioneCorso::class, ['record' => $esame->corso])->assertSuccessful();
        }
    }

    public function test_esame_di_un_altro_non_si_apre_per_chi_non_lo_vede(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $this->actingAs($this->utente('operatore', ['socio_id' => $this->socio('Altro')->id]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        \Livewire\Livewire::test(EsameResource\Pages\EditEsame::class, ['record' => $esame->getRouteKey()]);
    }

    public function test_direttore_senza_permessi_non_puo_eseguire_azioni_sul_corso(): void
    {
        $corso = $this->corsoPronto();
        $iscr  = Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $this->socio('Isc')->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'stato' => 'pending']);
        $this->actingAs($this->utente('operatore', ['socio_id' => $corso->direttore_socio_id]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::test(CorsoResource\Pages\GestioneCorso::class, ['record' => $corso])
            ->call('mountAction', 'rimuoviIscritto', ['iscrizione_id' => $iscr->id])
            ->assertNotified('Operazione non consentita al direttore del corso.');
        $this->assertTrue($iscr->fresh()->exists);
    }

    public function test_riporta_in_bozza_solo_senza_dati_e_commissione_dal_modale(): void
    {
        $esame = Esame::creaPerCorso($this->corsoPronto(), 'E', 60);
        $esame->attiva();
        $this->actingAs($this->utente('superadmin'));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $doc = $this->socio('Doc');
        $esame->corso->docenti()->attach($doc->id);
        \Livewire\Livewire::test(EsameResource\Pages\GestioneEsame::class, ['record' => $esame->getRouteKey()])
            ->callAction('commissione', ['docenti' => [$doc->id]]);
        $this->assertSame(1, $esame->docentiCommissione()->count());

        \Livewire\Livewire::test(EsameResource\Pages\EditEsame::class, ['record' => $esame->getRouteKey()])
            ->callAction('riportaInBozza');
        $this->assertTrue($esame->fresh()->isBozza());
        $this->assertNull($esame->fresh()->modello_snapshot);

        $this->assertNotNull($esame->fresh()->motivoNonRiportabileInBozza()); // già in bozza
    }
}
