<?php

namespace Tests\Feature;

use App\Models\CatalogoCorso;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Graduatoria;
use App\Models\GraduatoriaCandidato;
use App\Models\PatenteCri;
use App\Models\Socio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Copre la variante "da graduatoria" di GaiaBatchImportController (sociGraduatoria()/
 * analisiGraduatoria()/importaGraduatoria()): autorizzazione = soglia di ruolo
 * puoUsareGaiaBridge() + Graduatoria::puoGestire() (dal 2026-08-13 admin_locale è autorizzato
 * SOLO sulle graduatorie con comitato_locale_id = il proprio, mai su una graduatoria regionale
 * anche nella propria regione); inclusione dei candidati ritirati nella lista soci; scarto di
 * candidati non iscritti alla graduatoria in analisi(); e che il refactoring di
 * eseguiAnalisiRighe()/eseguiImportazioneRighe() (condivisi col batch comitato) funzioni
 * correttamente anche per questo secondo contenitore.
 */
class GaiaBatchImportGraduatoriaTest extends TestCase
{
    use RefreshDatabase;

    private static int $cfSeq = 0;
    private static int $graduatoriaSeq = 0;

    private function comitatoRegionale(): ComitatoRegionale
    {
        static $seq = 0;
        $seq++;

        return ComitatoRegionale::create([
            'nome' => "Regione {$seq}",
            'codice' => "REG{$seq}",
            'attivo' => true,
        ]);
    }

    private function comitatoLocale(ComitatoRegionale $regionale): ComitatoLocale
    {
        static $seq = 0;
        $seq++;

        return ComitatoLocale::create([
            'comitato_regionale_id' => $regionale->id,
            'nome' => "Locale {$seq}",
            'codice' => "LOC{$seq}",
            'attivo' => true,
        ]);
    }

    private function user(string $ruolo, ?ComitatoRegionale $regionale = null, ?ComitatoLocale $locale = null): User
    {
        static $seq = 0;
        $seq++;

        return User::create([
            'name' => "User {$seq}",
            'email' => "user{$seq}@test.local",
            'ruolo' => $ruolo,
            'comitato_regionale_id' => $regionale?->id,
            'comitato_locale_id' => $locale?->id,
            'attivo' => true,
        ]);
    }

    private function socio(ComitatoLocale $locale): Socio
    {
        self::$cfSeq++;
        $cf = str_pad((string) self::$cfSeq, 16, '0', STR_PAD_LEFT);

        return Socio::create([
            'nome' => 'Nome' . self::$cfSeq,
            'cognome' => 'Cognome' . self::$cfSeq,
            'codice_fiscale' => $cf,
            'comitato_regionale_id' => $locale->comitato_regionale_id,
            'comitato_locale_id' => $locale->id,
        ]);
    }

    private function graduatoria(ComitatoRegionale $regionale, ?ComitatoLocale $locale = null): Graduatoria
    {
        self::$graduatoriaSeq++;
        $n = self::$graduatoriaSeq;

        return Graduatoria::create([
            'titolo' => "Graduatoria {$n}",
            'codice_protocollo' => "PROT-{$n}",
            'codice_pubblico' => "GRAD{$n}",
            'comitato_regionale_id' => $regionale->id,
            'comitato_locale_id' => $locale?->id,
            'data_apertura' => Carbon::now()->subDays(10),
            'data_chiusura' => Carbon::now()->addDays(10),
        ]);
    }

    private function candidato(Graduatoria $graduatoria, ?Socio $socio, bool $ritirato = false): GraduatoriaCandidato
    {
        self::$cfSeq++;

        return GraduatoriaCandidato::create([
            'graduatoria_id' => $graduatoria->id,
            'socio_id' => $socio?->id,
            'codice_fiscale' => $socio?->codice_fiscale ?? str_pad((string) self::$cfSeq, 16, '9', STR_PAD_LEFT),
            'nome' => $socio?->nome ?? 'NomeSenzaSocio' . self::$cfSeq,
            'cognome' => $socio?->cognome ?? 'CognomeSenzaSocio' . self::$cfSeq,
            'email' => 'candidato' . self::$cfSeq . '@test.local',
            'stato' => 'confermato',
            'ritirato_at' => $ritirato ? Carbon::now()->subDay() : null,
        ]);
    }

    private function catalogoCorso(string $codice, string $nome): CatalogoCorso
    {
        return CatalogoCorso::forceCreate([
            'codice' => $codice,
            'nome' => $nome,
            'nome_visibile' => $nome,
            'tipo' => 'base',
            'obiettivo' => 'obiettivo test',
            'attivo' => true,
            'scadenza_tipo' => null,
        ]);
    }

    private function callEndpoint(User $user, Graduatoria $target, string $endpoint): TestResponse
    {
        $base = "/api/v1/graduatorie/{$target->id}/gaia-batch/{$endpoint}";

        return match ($endpoint) {
            'soci' => $this->actingAs($user)->getJson($base),
            'analisi' => $this->actingAs($user)->postJson($base, [
                'soci' => [[
                    'socio_id' => 999999,
                    'righe' => [['nome_corso' => 'Corso Inesistente', 'dates' => ['2020-01-01']]],
                ]],
            ]),
            'importa' => $this->actingAs($user)->postJson($base, [
                'righe' => [['socio_id' => 999999]],
            ]),
        };
    }

    // -----------------------------------------------------------------------
    // Autorizzazione sui 3 endpoint
    // -----------------------------------------------------------------------

    public function test_superadmin_autorizzato_su_qualsiasi_graduatoria(): void
    {
        $regionale = $this->comitatoRegionale();
        $graduatoria = $this->graduatoria($regionale);
        $superadmin = $this->user('superadmin');

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($superadmin, $graduatoria, $endpoint)->assertOk();
        }
    }

    public function test_admin_autorizzato_solo_su_graduatoria_della_propria_regione(): void
    {
        $regioneA = $this->comitatoRegionale();
        $regioneB = $this->comitatoRegionale();
        $graduatoriaA = $this->graduatoria($regioneA);
        $graduatoriaB = $this->graduatoria($regioneB);
        $admin = $this->user('admin', $regioneA);

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($admin, $graduatoriaA, $endpoint)->assertOk();
            $this->callEndpoint($admin, $graduatoriaB, $endpoint)->assertForbidden();
        }
    }

    /**
     * Dal 2026-08-13: admin_locale è autorizzato su una graduatoria scoped al proprio comitato
     * locale (comitato_locale_id valorizzato e uguale al proprio — Graduatoria::puoGestire()
     * vero), ma resta escluso da una graduatoria regionale (comitato_locale_id null) anche
     * nella propria regione: puoGestire() per admin_locale richiede sempre un comitato_locale_id
     * che combaci, non basta la regione.
     */
    public function test_admin_locale_autorizzato_solo_su_graduatoria_del_proprio_comitato(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $graduatoriaComitato = $this->graduatoria($regionale, $locale);
        $graduatoriaRegionale = $this->graduatoria($regionale);
        $adminLocale = $this->user('admin_locale', $regionale, $locale);

        $this->assertTrue($graduatoriaComitato->puoGestire($adminLocale));
        $this->assertFalse($graduatoriaRegionale->puoGestire($adminLocale));

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($adminLocale, $graduatoriaComitato, $endpoint)->assertOk();
            $this->callEndpoint($adminLocale, $graduatoriaRegionale, $endpoint)->assertForbidden();
        }
    }

    public function test_operatore_sempre_non_autorizzato(): void
    {
        $regionale = $this->comitatoRegionale();
        $graduatoria = $this->graduatoria($regionale);
        $operatore = $this->user('operatore', $regionale);

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($operatore, $graduatoria, $endpoint)->assertForbidden();
        }
    }

    // -----------------------------------------------------------------------
    // sociGraduatoria()
    // -----------------------------------------------------------------------

    public function test_soci_graduatoria_include_candidati_ritirati(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $graduatoria = $this->graduatoria($regionale);
        $socioAttivo = $this->socio($locale);
        $socioRitirato = $this->socio($locale);
        $this->candidato($graduatoria, $socioAttivo);
        $this->candidato($graduatoria, $socioRitirato, ritirato: true);
        $superadmin = $this->user('superadmin');

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/graduatorie/{$graduatoria->id}/gaia-batch/soci")
            ->assertOk();

        $ids = collect($response->json('soci'))->pluck('socio_id');

        $this->assertTrue($ids->contains($socioAttivo->id));
        $this->assertTrue($ids->contains($socioRitirato->id));
    }

    public function test_soci_graduatoria_esclude_candidati_senza_socio_agganciato(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $graduatoria = $this->graduatoria($regionale);
        $socioAgganciato = $this->socio($locale);
        $this->candidato($graduatoria, $socioAgganciato);
        $this->candidato($graduatoria, null); // candidatura senza socio_id (es. in attesa di conferma)
        $superadmin = $this->user('superadmin');

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/graduatorie/{$graduatoria->id}/gaia-batch/soci")
            ->assertOk();

        $this->assertCount(1, $response->json('soci'));
        $this->assertSame($socioAgganciato->id, $response->json('soci.0.socio_id'));
    }

    // -----------------------------------------------------------------------
    // analisiGraduatoria() / importaGraduatoria()
    // -----------------------------------------------------------------------

    public function test_analisi_graduatoria_scarta_socio_non_iscritto_alla_graduatoria(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $graduatoria = $this->graduatoria($regionale);
        $socioIscritto = $this->socio($locale);
        $socioNonIscritto = $this->socio($locale); // esiste ma non è candidato di questa graduatoria
        $this->candidato($graduatoria, $socioIscritto);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        $superadmin = $this->user('superadmin');

        $payload = [
            'soci' => [
                ['socio_id' => $socioIscritto->id, 'righe' => [['nome_corso' => $corso->nome, 'dates' => ['2020-01-01']]]],
                ['socio_id' => $socioNonIscritto->id, 'righe' => [['nome_corso' => $corso->nome, 'dates' => ['2020-01-01']]]],
            ],
        ];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/graduatorie/{$graduatoria->id}/gaia-batch/analisi", $payload)
            ->assertOk();

        $righe = collect($response->json('righe'));
        $this->assertCount(1, $righe);
        $this->assertSame($socioIscritto->id, $righe->first()['socio_id']);
    }

    public function test_analisi_e_importa_graduatoria_gestiscono_le_patenti(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $graduatoria = $this->graduatoria($regionale);
        $socio = $this->socio($locale);
        $this->candidato($graduatoria, $socio);
        PatenteCri::updateOrCreate(['tipologia' => '2b'], ['descrizione' => 'x', 'ordine' => 0, 'attivo' => true]);
        $superadmin = $this->user('superadmin');

        $analisi = $this->actingAs($superadmin)->postJson("/api/v1/graduatorie/{$graduatoria->id}/gaia-batch/analisi", [
            'soci' => [[
                'socio_id' => $socio->id,
                'righe' => [],
                'patenti' => [['tipologia_raw' => 'PATENTE CRI 5B', 'data_rilascio' => '2020-01-01', 'data_scadenza' => null, 'protocollo' => 'P1']],
            ]],
        ])->assertOk()->json();

        $patRiga = collect($analisi['righe'])->firstWhere('tipo_riga', 'patente');
        $this->assertSame('patente_inserimento', $patRiga['stato']);

        $this->actingAs($superadmin)->postJson("/api/v1/graduatorie/{$graduatoria->id}/gaia-batch/importa", [
            'righe' => $analisi['righe'],
        ])->assertOk()->assertJson(['inseriti' => 1]);

        $this->assertDatabaseHas('socio_patenti', [
            'socio_id' => $socio->id,
            'patente_cri_id' => PatenteCri::where('tipologia', '2b')->value('id'),
            'fonte' => 'importazione_gaia',
            'protocollo' => 'P1',
        ]);
    }

    public function test_importa_graduatoria_scrive_qualifica_socio_con_fonte_gaia(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $graduatoria = $this->graduatoria($regionale);
        $socio = $this->socio($locale);
        $this->candidato($graduatoria, $socio);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        $superadmin = $this->user('superadmin');

        $payload = [
            'righe' => [[
                'socio_id' => $socio->id,
                'catalogo_corso_id' => $corso->id,
                'stato' => 'inserimento',
                'data_acquisizione' => '2020-01-01',
                'data_ultimo_rinnovo' => null,
                'aggiorna_base' => [],
            ]],
        ];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/graduatorie/{$graduatoria->id}/gaia-batch/importa", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('inseriti'));
        $this->assertDatabaseHas('qualifiche_soci', [
            'socio_id' => $socio->id,
            'catalogo_corso_id' => $corso->id,
            'fonte' => 'importazione_gaia',
        ]);
    }
}
