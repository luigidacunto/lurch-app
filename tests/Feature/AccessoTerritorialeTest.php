<?php

namespace Tests\Feature;

use App\Filament\Resources\CorsoResource;
use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EventoResource;
use App\Filament\Resources\GraduatoriaResource;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\Graduatoria;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\EsameModelliSeeder;
use Database\Seeders\PermessiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ogni pagina o indirizzo che apre un evento, un corso, una graduatoria o un esame per numero
 * deve rispettare il territorio: l'admin di un'altra regione riceve 403/404 anche con il link
 * diretto. DB di test: lurch_test.
 */
class AccessoTerritorialeTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{evento:int, corso:Corso, graduatoria:Graduatoria, esame:Esame}> */
    private array $dati = [];

    private User $superadmin;
    private User $adminA;
    private User $operatoreA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsameModelliSeeder::class);
        $this->seed(PermessiSeeder::class);

        $catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'Modulo TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'Modulo TCRI', 'attivo' => true]);
        $now = now();

        foreach (['A', 'B'] as $r) {
            $cr = DB::table('comitati_regionali')->insertGetId(['nome' => "CR {$r}", 'codice' => "R{$r}", 'created_at' => $now, 'updated_at' => $now]);
            $evento = DB::table('eventi')->insertGetId([
                'comitato_regionale_id' => $cr, 'nome' => "Evento {$r}", 'sede' => 's', 'indirizzo' => 'i',
                'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '["2026-11-01","2026-11-02"]',
                // In produzione lo genera Evento::creating; qui l'inserimento diretto lo salta.
                'candidature_staff_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $direttore = Socio::create(['nome' => 'Dir', 'cognome' => $r, 'codice_fiscale' => "DRXXXX80A01H50{$r}X"]);
            $corso = Corso::create([
                'evento_id' => $evento, 'nome' => "Corso {$r}", 'numero_protocollo' => "X/2026/{$r}/1",
                'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
                'direttore_socio_id' => $direttore->id, 'catalogo_corso_id' => $catalogo->id, 'pagamento_previsto' => true,
            ]);
            $graduatoria = Graduatoria::create([
                'titolo' => "Graduatoria {$r}", 'codice_protocollo' => "PROT-{$r}", 'codice_pubblico' => "GRAD{$r}",
                'comitato_regionale_id' => $cr, 'data_apertura' => now()->subDays(10), 'data_chiusura' => now()->addDays(10),
            ]);

            $this->dati[$r] = [
                'cr' => $cr, 'evento' => $evento, 'corso' => $corso, 'graduatoria' => $graduatoria, 'direttore' => $direttore,
                'esame' => Esame::creaPerCorso($corso, "Esame {$r}", 60),
            ];
        }

        $this->superadmin = $this->utente('superadmin');
        $this->adminA     = $this->utente('admin', ['comitato_regionale_id' => $this->dati['A']['cr']]);

        // Operatore della regione A assegnato al solo evento A, con i permessi di lettura.
        $this->operatoreA = $this->utente('operatore', ['comitato_regionale_id' => $this->dati['A']['cr']]);
        $this->operatoreA->givePermissionTo(['eventi.view', 'eventi.edit', 'corsi.view', 'graduatorie.manage']);
        DB::table('evento_operatore')->insert(['evento_id' => $this->dati['A']['evento'], 'user_id' => $this->operatoreA->id]);
        DB::table('graduatoria_operatore')->insert(['graduatoria_id' => $this->dati['A']['graduatoria']->id, 'user_id' => $this->operatoreA->id]);
    }

    private function utente(string $ruolo, array $extra = []): User
    {
        return User::create($extra + ['name' => $ruolo, 'email' => $ruolo . uniqid() . '@t.it', 'password' => 'x', 'ruolo' => $ruolo, 'attivo' => true]);
    }

    /** Pagine del pannello e indirizzi diretti per i dati della regione $r. */
    /** Pagine dell'esame che si aprono solo a esame attivato (modello_snapshot): provate dopo attiva(). */
    private const PAGINE_ESAME_ATTIVO = ['valutazioni', 'itinere', 'test-scritto', 'esito', 'riepilogo-discenti'];

    private function attivaEsami(): void
    {
        foreach ($this->dati as $d) {
            $d['esame']->attiva();
        }
    }

    private function indirizziEsameAttivo(string $r): array
    {
        $x = $this->dati[$r]['esame'];
        $pagine = [];
        foreach (self::PAGINE_ESAME_ATTIVO as $p) {
            $pagine["esame.{$p}"] = EsameResource::getUrl($p, ['record' => $x]);
        }

        return [$pagine, ['esami.esiti-csv' => route('esami.esiti-csv', $x)]];
    }

    private function indirizzi(string $r): array
    {
        $d = $this->dati[$r];
        $e = $d['evento'];
        $c = $d['corso'];
        $g = $d['graduatoria'];
        $x = $d['esame'];

        $pagine = ['evento.edit' => EventoResource::getUrl('edit', ['record' => $e])];
        foreach (['gestione', 'staff', 'candidature-staff', 'partecipanti', 'presenze-live', 'checkin-risposte', 'presenze-effettive', 'report'] as $p) {
            $pagine["evento.{$p}"] = EventoResource::getUrl($p, ['record' => $e]);
        }
        foreach (['edit', 'gestione', 'gestione-quote'] as $p) {
            $pagine["corso.{$p}"] = CorsoResource::getUrl($p, ['record' => $c]);
        }
        foreach (['edit', 'candidati'] as $p) {
            $pagine["graduatoria.{$p}"] = GraduatoriaResource::getUrl($p, ['record' => $g]);
        }
        foreach (['gestione', 'edit', 'discenti', 'laboratori', 'tutoraggio', 'domande-test'] as $p) {
            $pagine["esame.{$p}"] = EsameResource::getUrl($p, ['record' => $x]);
        }

        $export = [
            'eventi.pernotti-export'             => route('eventi.pernotti-export', $e),
            'eventi.pernottanti-checkin-export'  => route('eventi.pernottanti-checkin-export', $e),
            'eventi.partecipanti-export'         => route('eventi.partecipanti-export', $e),
            'eventi.pasti-griglia-pdf'           => route('eventi.pasti-griglia-pdf', $e),
            'eventi.pasti-dettaglio-pdf'         => route('eventi.pasti-dettaglio-pdf', $e),
            'eventi.pasti-elenco-excel'          => route('eventi.pasti-elenco-excel', $e),
            'eventi.badge-staff'                 => route('eventi.badge-staff', $e),
            'eventi.badge-vuoti'                 => route('eventi.badge-vuoti', $e),
            'corsi.quote-pdf'                    => route('corsi.quote-pdf', $c),
            'corsi.quote-excel'                  => route('corsi.quote-excel', $c),
            'corsi.quote-anteprima'              => route('corsi.quote-anteprima', $c),
            'corsi.discenti-excel'               => route('corsi.discenti-excel', $c),
            'corsi.badge-corsisti'               => route('corsi.badge-corsisti', $c),
            'graduatorie.candidati-anteprima'    => route('graduatorie.candidati-anteprima', $g),
        ];

        return [$pagine, $export];
    }

    /**
     * Stato HTTP di ogni indirizzo della regione: prima con l'esame in bozza, poi attivato
     * per le pagine di valutazione. Un 500 riporta il messaggio dell'errore.
     *
     * @return array<string, int|string>
     */
    private function stati(User $utente, string $regione, bool $soloPagine = false): array
    {
        $this->actingAs($utente);
        $stati = [];
        foreach (['bozza', 'attivo'] as $fase) {
            if ($fase === 'attivo') {
                $this->attivaEsami();
            }
            [$pagine, $export] = $fase === 'bozza' ? $this->indirizzi($regione) : $this->indirizziEsameAttivo($regione);
            foreach ($soloPagine ? $pagine : $pagine + $export as $nome => $url) {
                $risposta = $this->get($url);
                $stati[$nome] = $risposta->getStatusCode() === 500
                    ? 500 . ' ' . $risposta->exception?->getMessage()
                    : $risposta->getStatusCode();
            }
        }

        return $stati;
    }

    public function test_admin_di_un_altra_regione_non_apre_nulla_con_il_link_diretto(): void
    {
        $aperti = array_filter($this->stati($this->adminA, 'B'), fn ($s) => ! in_array($s, [403, 404], true));

        $this->assertSame([], $aperti, 'Indirizzi della regione B aperti dall\'admin della regione A');
    }

    public function test_admin_apre_le_pagine_della_propria_regione(): void
    {
        $bloccati = array_filter($this->stati($this->adminA, 'A', soloPagine: true), fn ($s) => $s !== 200);

        $this->assertSame([], $bloccati, 'Pagine della propria regione non aperte dall\'admin');
    }

    public function test_superadmin_apre_le_pagine_di_ogni_regione(): void
    {
        $bloccati = array_filter($this->stati($this->superadmin, 'B', soloPagine: true), fn ($s) => $s !== 200);

        $this->assertSame([], $bloccati, 'Pagine della regione B non aperte dal superadmin');
    }

    public function test_operatore_apre_solo_eventi_corsi_e_graduatorie_assegnati(): void
    {
        $this->actingAs($this->operatoreA);
        $propri = [
            EventoResource::getUrl('gestione', ['record' => $this->dati['A']['evento']]),
            CorsoResource::getUrl('gestione', ['record' => $this->dati['A']['corso']]),
            GraduatoriaResource::getUrl('candidati', ['record' => $this->dati['A']['graduatoria']]),
        ];
        foreach ($propri as $url) {
            $this->get($url)->assertOk();
        }

        $aperti = array_filter($this->stati($this->operatoreA, 'B'), fn ($s) => ! in_array($s, [403, 404], true));
        $this->assertSame([], $aperti, 'Indirizzi della regione B aperti dall\'operatore della regione A');
    }

    /** Stato HTTP degli export della regione A per l'utente (un 500 riporta il messaggio). */
    private function statiExport(User $utente): array
    {
        $this->actingAs($utente);
        $stati = [];
        foreach ($this->indirizzi('A')[1] as $nome => $url) {
            $risposta = $this->get($url);
            $stati[$nome] = $risposta->getStatusCode() === 500 ? 500 . ' ' . $risposta->exception?->getMessage() : $risposta->getStatusCode();
        }

        return $stati;
    }

    public function test_gli_export_seguono_la_regola_della_pagina_da_cui_partono(): void
    {
        // Operatore assegnato con i permessi: come le pagine, apre tutti gli export.
        // L'elenco pasti Excel risponde 404 «nessun pasto con persone previste» dopo il controllo d'accesso: evento di prova senza iscritti.
        $bloccati = array_filter($this->statiExport($this->operatoreA), fn ($s, $nome) => $s !== 200 && ! ($nome === 'eventi.pasti-elenco-excel' && $s === 404), ARRAY_FILTER_USE_BOTH);
        $this->assertSame([], $bloccati, 'Export negati all\'operatore assegnato con i permessi');

        // Operatore assegnato ma senza permessi: non apre le pagine, quindi nemmeno gli export.
        $senzaPermessi = $this->utente('operatore', ['comitato_regionale_id' => $this->dati['A']['cr']]);
        DB::table('evento_operatore')->insert(['evento_id' => $this->dati['A']['evento'], 'user_id' => $senzaPermessi->id]);
        DB::table('graduatoria_operatore')->insert(['graduatoria_id' => $this->dati['A']['graduatoria']->id, 'user_id' => $senzaPermessi->id]);
        $aperti = array_filter($this->statiExport($senzaPermessi), fn ($s) => $s !== 403);
        $this->assertSame([], $aperti, 'Export aperti all\'operatore senza permessi');

        // Direttore del corso (senza altri ruoli): gli export del suo corso sì, quelli dell'evento no.
        $direttore = $this->utente('operatore', ['socio_id' => $this->dati['A']['direttore']->id]);
        $stati = $this->statiExport($direttore);
        $corso  = array_filter($stati, fn ($s, $nome) => str_starts_with($nome, 'corsi.') && $s !== 200, ARRAY_FILTER_USE_BOTH);
        $altri  = array_filter($stati, fn ($s, $nome) => ! str_starts_with($nome, 'corsi.') && $s !== 403, ARRAY_FILTER_USE_BOTH);
        $this->assertSame([], $corso, 'Export del corso negati al direttore');
        $this->assertSame([], $altri, 'Export dell\'evento o della graduatoria aperti al direttore');
    }

    /** Importa iscritti: l'admin locale lo usa sui corsi del suo comitato; i corsi regionali non li apre proprio. */
    public function test_admin_locale_importa_iscritti_solo_nei_corsi_del_suo_comitato(): void
    {
        $cr     = $this->dati['A']['cr'];
        $locale = \App\Models\ComitatoLocale::create(['comitato_regionale_id' => $cr, 'nome' => 'Loc A', 'codice' => 'LA', 'attivo' => true]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'comitato_locale_id' => $locale->id, 'nome' => 'Evento locale', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '["2026-11-01","2026-11-02"]',
            'candidature_staff_uuid' => (string) \Illuminate\Support\Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $corsoLocale = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso locale', 'numero_protocollo' => 'X/2026/L/1',
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
        ]);
        $adminLocale = $this->utente('admin_locale', ['comitato_regionale_id' => $cr, 'comitato_locale_id' => $locale->id]);

        $this->actingAs($adminLocale);
        $this->assertContains($this->get(CorsoResource::getUrl('gestione', ['record' => $this->dati['A']['corso']]))->getStatusCode(), [403, 404]);
        \Livewire\Livewire::test(CorsoResource\Pages\GestioneCorso::class, ['record' => $corsoLocale])
            ->assertActionVisible('importaIscritti');
    }
}
