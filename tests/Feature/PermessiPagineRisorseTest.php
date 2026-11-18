<?php

namespace Tests\Feature;

use App\Filament\Resources\CatalogoCorsiResource;
use App\Filament\Resources\EmailTemplateResource;
use App\Filament\Resources\GraduatoriaResource;
use App\Filament\Resources\PrivacyTemplateResource;
use App\Filament\Resources\SocioResource;
use App\Models\CatalogoCorso;
use App\Models\EmailTemplate;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\PermessiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le pagine Nuovo/Modifica/Visualizza delle risorse rispettano i canCreate()/canEdit()/canView()
 * ridefiniti nella risorsa anche con l'indirizzo diretto (tratto RispettaPermessiDellaRisorsa):
 * in Filament 5, senza policy, le pagine li ignorerebbero. DB di test: lurch_test.
 */
class PermessiPagineRisorseTest extends TestCase
{
    use RefreshDatabase;

    private int $regione;
    private CatalogoCorso $corso;
    private EmailTemplate $emailNazionale;
    private PrivacyTemplate $privacyNazionale;
    private Socio $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermessiSeeder::class);

        $this->regione = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR A', 'codice' => 'RA', 'created_at' => now(), 'updated_at' => now()]);
        $this->corso = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'Modulo TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'Modulo TCRI', 'attivo' => true]);
        $this->emailNazionale = EmailTemplate::create(['tipo' => 'iscritti', 'comitato_regionale_id' => null, 'soggetto' => 'S', 'corpo' => 'C', 'attivo' => true]);
        $this->privacyNazionale = PrivacyTemplate::create(['tipo' => 'informativa_dati', 'comitato_regionale_id' => null, 'testo' => 'T', 'versione' => '1', 'attivo' => true]);
        $this->socio = Socio::create(['nome' => 'Socio', 'cognome' => 'A', 'codice_fiscale' => 'SCXXXX80A01H501X', 'comitato_regionale_id' => $this->regione]);
    }

    private function utente(string $ruolo, array $extra = []): User
    {
        return User::create($extra + ['name' => $ruolo, 'email' => $ruolo . uniqid() . '@t.it', 'password' => 'x', 'ruolo' => $ruolo, 'attivo' => true]);
    }

    /** @return array<string, string> indirizzi riservati: nome => url */
    private function indirizziRiservati(): array
    {
        return [
            'catalogo.edit'        => CatalogoCorsiResource::getUrl('edit', ['record' => $this->corso]),
            'catalogo.create'      => CatalogoCorsiResource::getUrl('create'),
            'template-email.edit'  => EmailTemplateResource::getUrl('edit', ['record' => $this->emailNazionale]),
            'template-privacy.edit' => PrivacyTemplateResource::getUrl('edit', ['record' => $this->privacyNazionale]),
        ];
    }

    public function test_l_admin_regionale_non_apre_le_pagine_riservate_al_superadmin(): void
    {
        $this->actingAs($this->utente('admin', ['comitato_regionale_id' => $this->regione]));

        $aperti = [];
        foreach ($this->indirizziRiservati() as $nome => $url) {
            $stato = $this->get($url)->getStatusCode();
            if ($stato !== 403) {
                $aperti[$nome] = $stato;
            }
        }

        $this->assertSame([], $aperti, 'Pagine riservate al superadmin aperte dall\'admin regionale');
    }

    public function test_il_superadmin_le_apre(): void
    {
        $this->actingAs($this->utente('superadmin'));

        foreach ($this->indirizziRiservati() as $nome => $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_l_admin_regionale_non_vede_i_testi_di_un_altra_regione(): void
    {
        $altra = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR B', 'codice' => 'RB', 'created_at' => now(), 'updated_at' => now()]);
        $email = EmailTemplate::create(['tipo' => 'iscritti', 'comitato_regionale_id' => $altra, 'soggetto' => 'Oggetto della regione B', 'corpo' => 'C', 'attivo' => true]);
        $propria = EmailTemplate::create(['tipo' => 'staff', 'comitato_regionale_id' => $this->regione, 'soggetto' => 'Oggetto della regione A', 'corpo' => 'C', 'attivo' => true]);
        \App\Models\IstruzionePagamento::create(['comitato_regionale_id' => $altra, 'testo' => 'IBAN della regione B', 'attivo' => true]);

        $this->actingAs($this->utente('admin', ['comitato_regionale_id' => $this->regione]));

        $this->get(EmailTemplateResource::getUrl('index'))->assertOk()
            ->assertSee('Oggetto della regione A')->assertDontSee('Oggetto della regione B');
        $this->get(\App\Filament\Resources\IstruzionePagamentoResource::getUrl('index'))->assertOk()
            ->assertDontSee('IBAN della regione B');
        $this->assertContains($this->get(EmailTemplateResource::getUrl('edit', ['record' => $email]))->getStatusCode(), [403, 404]);
        $this->get(EmailTemplateResource::getUrl('edit', ['record' => $propria]))->assertOk();
    }

    public function test_l_operatore_senza_permesso_di_modifica_non_apre_la_modifica_del_socio(): void
    {
        $operatore = $this->utente('operatore', ['comitato_regionale_id' => $this->regione]);
        $operatore->givePermissionTo(['soci.view', 'graduatorie.manage']);
        $this->actingAs($operatore);

        $this->get(SocioResource::getUrl('view', ['record' => $this->socio]))->assertOk();
        $this->get(SocioResource::getUrl('edit', ['record' => $this->socio]))->assertForbidden();
        $this->get(GraduatoriaResource::getUrl('create'))->assertForbidden();

        $operatore->givePermissionTo('soci.edit');
        $this->get(SocioResource::getUrl('edit', ['record' => $this->socio]))->assertOk();
    }
}
