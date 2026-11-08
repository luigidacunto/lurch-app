<?php

namespace Tests\Feature;

use App\Models\CatalogoCorso;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\QualificaSocio;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Copre GaiaBatchImportController: autorizzazione per comitato sui 3 endpoint (superadmin su
 * tutti, admin sulla propria regione, admin_locale SOLO sul proprio comitato esatto dal
 * 2026-08-13 — vedi User::puoUsareGaiaBridge()/autorizzaComitato()); scoping di lettura
 * (soci()/analisi()) a TUTTI i soci del comitato — primari, in estensione e dipendenti, nessuna
 * distinzione tra le tre forme (apparteneAlComitato()); gate di SCRITTURA in importa() via
 * SocioResource::canEditQualifiche() che dal 2026-08-27 include anche l'estensione (l'anagrafica
 * resta invece gated dal solo canEdit());
 * scarto silenzioso di righe fuori-comitato in analisi(), contatori in importa(), parser
 * condiviso per l'intero batch (una sola interrogazione al catalogo corsi anche con più soci);
 * riconciliazione (2026-08-17): qualifiche fonte importazione_gaia/importazione_excel non più
 * trovate in una lettura vengono segnalate come "rimossa" in analisi() ed eliminate in importa(),
 * mai quelle con altre fonti (via di fuga per proteggere una riga specifica).
 */
class GaiaBatchImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cfSeq = 0;

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

    /** Socio la cui appartenenza al comitato è solo per estensione (non primaria). */
    private function socioEstensione(ComitatoLocale $primario, ComitatoLocale $estensione): Socio
    {
        self::$cfSeq++;
        $cf = str_pad((string) self::$cfSeq, 16, '0', STR_PAD_LEFT);

        return Socio::create([
            'nome' => 'NomeEst' . self::$cfSeq,
            'cognome' => 'CognomeEst' . self::$cfSeq,
            'codice_fiscale' => $cf,
            'comitato_regionale_id' => $primario->comitato_regionale_id,
            'comitato_locale_id' => $primario->id,
            'comitato_locale_estensione_id' => $estensione->id,
        ]);
    }

    /** Socio dipendente (rapporto di lavoro) del comitato indicato, appartenenza primaria altrove. */
    private function socioDipendente(ComitatoLocale $primario, ComitatoLocale $dipendente): Socio
    {
        self::$cfSeq++;
        $cf = str_pad((string) self::$cfSeq, 16, '0', STR_PAD_LEFT);

        return Socio::create([
            'nome' => 'NomeDip' . self::$cfSeq,
            'cognome' => 'CognomeDip' . self::$cfSeq,
            'codice_fiscale' => $cf,
            'comitato_regionale_id' => $primario->comitato_regionale_id,
            'comitato_locale_id' => $primario->id,
            'comitato_locale_dipendente_id' => $dipendente->id,
        ]);
    }

    private function catalogoCorso(string $codice, string $nome): CatalogoCorso
    {
        // nome_visibile non è in $fillable (colonna gestita dal backfill GAIA) ma è NOT
        // NULL a livello DB: forceCreate per valorizzarla nello stesso insert.
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

    /** Payload minimo valido per analisi()/importa(), indipendente dai dati creati nel test. */
    private function callEndpoint(User $user, ComitatoLocale $target, string $endpoint): TestResponse
    {
        $base = "/api/v1/comitati-locali/{$target->id}/gaia-batch/{$endpoint}";

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
    // Autorizzazione per comitato, sui 3 endpoint
    // -----------------------------------------------------------------------

    public function test_superadmin_autorizzato_su_qualsiasi_comitato(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $superadmin = $this->user('superadmin');

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($superadmin, $locale, $endpoint)->assertOk();
        }
    }

    public function test_admin_autorizzato_solo_sulla_propria_regione(): void
    {
        $regioneA = $this->comitatoRegionale();
        $regioneB = $this->comitatoRegionale();
        $localeA = $this->comitatoLocale($regioneA);
        $localeB = $this->comitatoLocale($regioneB);
        $admin = $this->user('admin', $regioneA);

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($admin, $localeA, $endpoint)->assertOk();
            $this->callEndpoint($admin, $localeB, $endpoint)->assertForbidden();
        }
    }

    /**
     * Dal 2026-08-13 admin_locale è autorizzato, ma SOLO sul proprio comitato esatto — non
     * basta la stessa regione (a differenza del ramo admin sopra): autorizzaComitato() fa un
     * match su id, non su comitato_regionale_id.
     */
    public function test_admin_locale_autorizzato_solo_sul_proprio_comitato_esatto(): void
    {
        $regionale = $this->comitatoRegionale();
        $localeProprio = $this->comitatoLocale($regionale);
        $localeAltroStessaRegione = $this->comitatoLocale($regionale);
        $adminLocale = $this->user('admin_locale', $regionale, $localeProprio);

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($adminLocale, $localeProprio, $endpoint)->assertOk();
            $this->callEndpoint($adminLocale, $localeAltroStessaRegione, $endpoint)->assertForbidden();
        }
    }

    public function test_operatore_sempre_non_autorizzato(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $operatore = $this->user('operatore', $regionale);

        foreach (['soci', 'analisi', 'importa'] as $endpoint) {
            $this->callEndpoint($operatore, $locale, $endpoint)->assertForbidden();
        }
    }

    // -----------------------------------------------------------------------
    // soci(): scoping al comitato della URL
    // -----------------------------------------------------------------------

    public function test_soci_ritorna_solo_quelli_del_comitato_richiesto(): void
    {
        $regionale = $this->comitatoRegionale();
        $localeTarget = $this->comitatoLocale($regionale);
        $localeAltro = $this->comitatoLocale($regionale);
        $socioTarget = $this->socio($localeTarget);
        $socioAltro = $this->socio($localeAltro);
        $superadmin = $this->user('superadmin');

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/comitati-locali/{$localeTarget->id}/gaia-batch/soci")
            ->assertOk();

        $ids = collect($response->json('soci'))->pluck('socio_id');

        $this->assertTrue($ids->contains($socioTarget->id));
        $this->assertFalse($ids->contains($socioAltro->id));
    }

    /**
     * Regola generale: chi ha permessi su un comitato locale li ha su TUTTI i soci di quel
     * comitato, senza distinzione tra appartenenza primaria, estensione o dipendente — su
     * GAIA il permesso di lettura curriculum è lo stesso per tutti e tre.
     */
    public function test_soci_include_anche_estensione_e_dipendenti_del_comitato(): void
    {
        $regionale = $this->comitatoRegionale();
        $localeTarget = $this->comitatoLocale($regionale);
        $localePrimario = $this->comitatoLocale($regionale);
        $socioEstensione = $this->socioEstensione($localePrimario, $localeTarget);
        $socioDipendente = $this->socioDipendente($localePrimario, $localeTarget);
        $superadmin = $this->user('superadmin');

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/comitati-locali/{$localeTarget->id}/gaia-batch/soci")
            ->assertOk();

        $ids = collect($response->json('soci'))->pluck('socio_id');

        $this->assertTrue($ids->contains($socioEstensione->id));
        $this->assertTrue($ids->contains($socioDipendente->id));
    }

    // -----------------------------------------------------------------------
    // analisi(): scarto righe fuori-comitato, shape identica al flusso singolo-socio,
    // un solo parser condiviso per il batch
    // -----------------------------------------------------------------------

    public function test_analisi_scarta_righe_di_soci_fuori_dal_comitato(): void
    {
        $regionale = $this->comitatoRegionale();
        $localeTarget = $this->comitatoLocale($regionale);
        $localeAltro = $this->comitatoLocale($regionale);
        $socioDentro = $this->socio($localeTarget);
        $socioFuori = $this->socio($localeAltro);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        $superadmin = $this->user('superadmin');

        $payload = [
            'soci' => [
                [
                    'socio_id' => $socioDentro->id,
                    'righe' => [['nome_corso' => $corso->nome, 'dates' => ['2020-01-01']]],
                ],
                [
                    // payload manomesso: socio non appartenente al comitato richiesto
                    'socio_id' => $socioFuori->id,
                    'righe' => [['nome_corso' => $corso->nome, 'dates' => ['2020-01-01']]],
                ],
            ],
        ];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$localeTarget->id}/gaia-batch/analisi", $payload)
            ->assertOk();

        $righe = collect($response->json('righe'));

        $this->assertCount(1, $righe);
        $riga = $righe->first();

        $this->assertSame($socioDentro->id, $riga['socio_id']);
        $this->assertSame('inserimento', $riga['stato']);
        $this->assertSame($corso->id, $riga['catalogo_corso_id']);
        $this->assertSame('2020-01-01', $riga['data_acquisizione']);
        $this->assertArrayHasKey('data_ultimo_rinnovo', $riga);
        $this->assertArrayHasKey('aggiorna_base', $riga);
    }

    public function test_analisi_usa_un_solo_parser_condiviso_per_lintero_batch(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio1 = $this->socio($locale);
        $socio2 = $this->socio($locale);
        $this->catalogoCorso('C1', 'Corso Uno');
        $this->catalogoCorso('C2', 'Corso Due');
        $superadmin = $this->user('superadmin');

        $payload = [
            'soci' => [
                [
                    'socio_id' => $socio1->id,
                    'righe' => [
                        ['nome_corso' => 'Corso Uno', 'dates' => ['2020-01-01']],
                        ['nome_corso' => 'Corso Due', 'dates' => ['2020-01-01']],
                    ],
                ],
                [
                    'socio_id' => $socio2->id,
                    'righe' => [
                        ['nome_corso' => 'Corso Uno', 'dates' => ['2020-01-01']],
                        ['nome_corso' => 'Corso Due', 'dates' => ['2020-01-01']],
                    ],
                ],
            ],
        ];

        DB::enableQueryLog();

        $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/analisi", $payload)
            ->assertOk();

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'catalogo_corsi'));

        DB::disableQueryLog();

        // buildIndiceCorsi() carica l'intero catalogo con un'unica SELECT: con 2 soci x 2
        // righe (4 analisi totali) una sola query sul catalogo dimostra l'indice condiviso
        // dell'unico parser istanziato dal controller, non una query per riga.
        $this->assertCount(1, $queries);
    }

    // -----------------------------------------------------------------------
    // analisi(): riconciliazione qualifiche non più trovate su GAIA (stato "rimossa")
    // -----------------------------------------------------------------------

    public function test_analisi_segnala_come_rimossa_qualifica_gaia_non_piu_trovata(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio = $this->socio($locale);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        $esistente = QualificaSocio::create([
            'socio_id' => $socio->id,
            'catalogo_corso_id' => $corso->id,
            'data_acquisizione' => '2019-01-01',
            'fonte' => 'importazione_gaia',
        ]);
        $superadmin = $this->user('superadmin');

        // Curriculum letto con successo ma il corso non compare più — righe: [] (non assente:
        // la validazione usa 'present' proprio per questo caso, vedi GaiaBatchImportController).
        $payload = ['soci' => [['socio_id' => $socio->id, 'righe' => []]]];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/analisi", $payload)
            ->assertOk();

        $righe = collect($response->json('righe'));
        $this->assertCount(1, $righe);
        $riga = $righe->first();

        $this->assertSame('rimossa', $riga['stato']);
        $this->assertSame($corso->id, $riga['catalogo_corso_id']);
        $this->assertSame($socio->id, $riga['socio_id']);
    }

    /** Via di fuga: cambiando a mano la fonte in inserimento_manuale, la riga non viene più toccata. */
    public function test_analisi_non_segnala_rimossa_qualifica_inserimento_manuale(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio = $this->socio($locale);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        QualificaSocio::create([
            'socio_id' => $socio->id,
            'catalogo_corso_id' => $corso->id,
            'data_acquisizione' => '2019-01-01',
            'fonte' => 'inserimento_manuale',
        ]);
        $superadmin = $this->user('superadmin');

        $payload = ['soci' => [['socio_id' => $socio->id, 'righe' => []]]];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/analisi", $payload)
            ->assertOk();

        $this->assertCount(0, $response->json('righe'));
    }

    /** La regola include anche la fonte importazione_excel, non solo GAIA. */
    public function test_analisi_segnala_rimossa_anche_per_fonte_importazione_excel(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio = $this->socio($locale);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        QualificaSocio::create([
            'socio_id' => $socio->id,
            'catalogo_corso_id' => $corso->id,
            'data_acquisizione' => '2019-01-01',
            'fonte' => 'importazione_excel',
        ]);
        $superadmin = $this->user('superadmin');

        $payload = ['soci' => [['socio_id' => $socio->id, 'righe' => []]]];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/analisi", $payload)
            ->assertOk();

        $this->assertCount(1, $response->json('righe'));
        $this->assertSame('rimossa', $response->json('righe')[0]['stato']);
    }

    // -----------------------------------------------------------------------
    // importa(): gate SocioResource::canEditQualifiche(), scrittura, contatori
    // -----------------------------------------------------------------------

    public function test_importa_scarta_riga_di_socio_estraneo_al_comitato(): void
    {
        $regioneUtente = $this->comitatoRegionale();
        $regioneAltra = $this->comitatoRegionale();
        $localeUtente = $this->comitatoLocale($regioneUtente);
        $localeAltro = $this->comitatoLocale($regioneAltra);
        $socioFuoriTerritorio = $this->socio($localeAltro);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');

        // admin (regionale) autorizzato sul comitato bersaglio della URL (per superare
        // autorizzaComitato()) ma il socio nella riga non appartiene al comitato bersaglio in
        // nessuna delle tre forme (primario/estensione/dipendente): apparteneAlComitato() deve
        // bloccare comunque la scrittura, anche se l'utente avrebbe altrimenti i permessi generali.
        $admin = $this->user('admin', $regioneUtente);

        $payload = [
            'righe' => [[
                'socio_id' => $socioFuoriTerritorio->id,
                'catalogo_corso_id' => $corso->id,
                'stato' => 'inserimento',
                'data_acquisizione' => '2020-01-01',
                'data_ultimo_rinnovo' => null,
                'aggiorna_base' => [],
            ]],
        ];

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/comitati-locali/{$localeUtente->id}/gaia-batch/importa", $payload)
            ->assertOk();

        $this->assertSame(0, $response->json('inseriti'));
        $this->assertDatabaseCount('qualifiche_soci', 0);
    }

    /**
     * Scelta 2026-08-27: l'import qualifiche da GAIA è gated da SocioResource::canEditQualifiche()
     * (il building block usato da eseguiImportazioneRighe() per ogni riga), che include anche
     * l'estensione — a differenza di canEdit() (anagrafica/salute) che la esclude ancora. Sia
     * estensione che dipendente sono quindi scrivibili per l'admin_locale del comitato ospitante.
     */
    public function test_canedit_qualifiche_include_estensione_e_dipendente_per_admin_locale(): void
    {
        $regionale = $this->comitatoRegionale();
        $localeTarget = $this->comitatoLocale($regionale);
        $localePrimario = $this->comitatoLocale($regionale);
        $socioEstensione = $this->socioEstensione($localePrimario, $localeTarget);
        $socioDipendente = $this->socioDipendente($localePrimario, $localeTarget);
        $adminLocaleTarget = $this->user('admin_locale', $regionale, $localeTarget);

        $this->actingAs($adminLocaleTarget);

        // anagrafica: estensione resta sola lettura
        $this->assertFalse(\App\Filament\Resources\SocioResource::canEdit($socioEstensione));
        // qualifiche GAIA: estensione e dipendente entrambi scrivibili
        $this->assertTrue(\App\Filament\Resources\SocioResource::canEditQualifiche($socioEstensione));
        $this->assertTrue(\App\Filament\Resources\SocioResource::canEditQualifiche($socioDipendente));
    }

    public function test_importa_riga_inserimento_crea_qualifica_socio_con_fonte_gaia(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio = $this->socio($locale);
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
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/importa", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('inseriti'));

        $this->assertDatabaseHas('qualifiche_soci', [
            'socio_id' => $socio->id,
            'catalogo_corso_id' => $corso->id,
            'fonte' => 'importazione_gaia',
        ]);
    }

    public function test_importa_conta_correttamente_stati_misti_su_piu_soci(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio1 = $this->socio($locale);
        $socio2 = $this->socio($locale);
        $corso1 = $this->catalogoCorso('C1', 'Corso Uno');
        $corso2 = $this->catalogoCorso('C2', 'Corso Due');
        $corso3 = $this->catalogoCorso('C3', 'Corso Tre');
        $superadmin = $this->user('superadmin');

        // qualifica preesistente su cui verificare il ramo "obsoleta" (ricalcolo, nessuna scrittura)
        $esistente = QualificaSocio::create([
            'socio_id' => $socio2->id,
            'catalogo_corso_id' => $corso3->id,
            'data_acquisizione' => '2019-01-01',
            'fonte' => 'inserimento_manuale',
        ]);

        $payload = [
            'righe' => [
                [
                    'socio_id' => $socio1->id,
                    'catalogo_corso_id' => $corso1->id,
                    'stato' => 'inserimento',
                    'data_acquisizione' => '2020-01-01',
                    'data_ultimo_rinnovo' => null,
                    'aggiorna_base' => [],
                ],
                [
                    'socio_id' => $socio2->id,
                    'catalogo_corso_id' => $corso2->id,
                    'stato' => 'aggiornamento',
                    'data_acquisizione' => '2020-01-01',
                    'data_ultimo_rinnovo' => null,
                    'aggiorna_base' => [],
                ],
                [
                    'socio_id' => $socio2->id,
                    'catalogo_corso_id' => $corso3->id,
                    'stato' => 'obsoleta',
                ],
            ],
        ];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/importa", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('inseriti'));
        $this->assertSame(1, $response->json('aggiornati'));
        $this->assertSame(1, $response->json('ricalcolate'));
        $this->assertSame([], $response->json('errori'));

        $this->assertDatabaseHas('qualifiche_soci', [
            'socio_id' => $socio1->id,
            'catalogo_corso_id' => $corso1->id,
            'fonte' => 'importazione_gaia',
        ]);
        $this->assertDatabaseHas('qualifiche_soci', [
            'socio_id' => $socio2->id,
            'catalogo_corso_id' => $corso2->id,
            'fonte' => 'importazione_gaia',
        ]);
        // "obsoleta" non riscrive le date, ma promuove comunque la fonte secondo la gerarchia
        // manuale < excel < gaia (QualificaSocioImportWriter::ricalcolaSeEsistente())
        // — GAIA conferma la stessa qualifica già
        // inserita a mano, quindi la fonte passa a 'importazione_gaia'.
        $this->assertDatabaseHas('qualifiche_soci', [
            'id' => $esistente->id,
            'fonte' => 'importazione_gaia',
        ]);
    }

    public function test_importa_elimina_qualifica_con_stato_rimossa(): void
    {
        $regionale = $this->comitatoRegionale();
        $locale = $this->comitatoLocale($regionale);
        $socio = $this->socio($locale);
        $corso = $this->catalogoCorso('C1', 'Corso Uno');
        $esistente = QualificaSocio::create([
            'socio_id' => $socio->id,
            'catalogo_corso_id' => $corso->id,
            'data_acquisizione' => '2019-01-01',
            'fonte' => 'importazione_gaia',
        ]);
        $superadmin = $this->user('superadmin');

        $payload = [
            'righe' => [[
                'socio_id' => $socio->id,
                'catalogo_corso_id' => $corso->id,
                'stato' => 'rimossa',
            ]],
        ];

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/comitati-locali/{$locale->id}/gaia-batch/importa", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('rimossi'));
        $this->assertDatabaseMissing('qualifiche_soci', ['id' => $esistente->id]);
    }
}
