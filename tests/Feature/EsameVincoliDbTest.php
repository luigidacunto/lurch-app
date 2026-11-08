<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vincoli a livello di database del modulo Esami (migrazioni 2026_10_15_1*). Si usano insert
 * diretti per non passare dagli hook dei modelli esistenti. DB di test: lurch_test (phpunit.xml).
 */
class EsameVincoliDbTest extends TestCase
{
    use RefreshDatabase;

    private int $corso;
    private int $esame;
    private array $soci = [];

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $comitato = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR Test', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $comitato, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->corso = $this->nuovoCorso($evento);
        foreach (['A', 'B', 'C'] as $i => $n) {
            $this->soci[] = DB::table('soci')->insertGetId([
                'nome' => $n, 'cognome' => 'Socio', 'codice_fiscale' => 'CF' . $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('esame_modelli')->insert(['tipo_codice' => 'TCRI', 'versione' => 1, 'struttura' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        $this->esame = $this->nuovoEsame($this->corso);
    }

    private function nuovoCorso(int $evento): int
    {
        return DB::table('corsi')->insertGetId([
            'evento_id' => $evento, 'nome' => 'Corso ' . uniqid(), 'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00',
            'data_fine' => '2026-11-02', 'ora_fine' => '18:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function nuovoEsame(int $corso, string $tipo = 'TCRI', int $versione = 1): int
    {
        return DB::table('esami')->insertGetId([
            'corso_id' => $corso, 'titolo' => 'Esame', 'tipo_codice' => $tipo, 'modello_versione' => $versione,
            'lap' => 60, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function prova(string $tipo, ?int $discente = null, ?int $membro = null): int
    {
        return DB::table('tcri_prove_pratiche')->insertGetId([
            'esame_id' => $this->esame, 'discente_socio_id' => $discente ?? $this->soci[0], 'tipo' => $tipo,
            'membro_socio_id' => $membro, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Il vincolo deve rifiutare: savepoint annidato, così il test prosegue dopo l'errore PostgreSQL. */
    private function assertRifiutato(Closure $insert, string $vincolo): void
    {
        try {
            DB::transaction($insert);
        } catch (QueryException $e) {
            $this->assertStringContainsString($vincolo, $e->getMessage());

            return;
        }
        $this->fail("Atteso il rifiuto da parte di {$vincolo}.");
    }

    public function test_una_sola_finale_per_esame_e_discente(): void
    {
        $this->prova('finale');

        $this->assertRifiutato(fn () => $this->prova('finale'), 'tcri_prove_pratiche_finale_unique');
        $this->prova('finale', $this->soci[1]); // altro discente: ok
        $this->assertSame(2, DB::table('tcri_prove_pratiche')->count());
    }

    public function test_una_sola_personale_per_membro(): void
    {
        $this->prova('personale', $this->soci[0], $this->soci[1]);
        $this->prova('personale', $this->soci[0], $this->soci[2]); // altro membro: ok

        $this->assertRifiutato(fn () => $this->prova('personale', $this->soci[0], $this->soci[1]), 'tcri_prove_pratiche_personale_unique');
    }

    public function test_check_coerenza_tipo_membro(): void
    {
        $this->assertRifiutato(fn () => $this->prova('finale', null, $this->soci[1]), 'tcri_prove_pratiche_tipo_membro_check');
        $this->assertRifiutato(fn () => $this->prova('personale'), 'tcri_prove_pratiche_tipo_membro_check');
    }

    private function colloquio(string $tipo, ?int $discente = null, ?int $membro = null, string $punteggio = '4'): int
    {
        return DB::table('tcri_colloqui')->insertGetId([
            'esame_id' => $this->esame, 'discente_socio_id' => $discente ?? $this->soci[0], 'tipo' => $tipo,
            'membro_socio_id' => $membro, 'punteggio' => $punteggio, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_colloquio_una_sola_finale_e_una_sola_personale_per_membro(): void
    {
        $this->colloquio('finale', null, null, '3.5');
        $this->assertRifiutato(fn () => $this->colloquio('finale'), 'tcri_colloqui_finale_unique');
        $this->colloquio('finale', $this->soci[1]); // altro discente: ok

        $this->colloquio('personale', null, $this->soci[1]);
        $this->colloquio('personale', null, $this->soci[2]); // altro membro: ok
        $this->assertRifiutato(fn () => $this->colloquio('personale', null, $this->soci[1]), 'tcri_colloqui_personale_unique');
        $this->assertEquals(3.5, DB::table('tcri_colloqui')->where('tipo', 'finale')->where('discente_socio_id', $this->soci[0])->value('punteggio'));
    }

    public function test_colloquio_check_coerenza_tipo_membro(): void
    {
        $this->assertRifiutato(fn () => $this->colloquio('finale', null, $this->soci[1]), 'tcri_colloqui_tipo_membro_check');
        $this->assertRifiutato(fn () => $this->colloquio('personale'), 'tcri_colloqui_tipo_membro_check');
    }

    public function test_una_sola_consegna_del_test_per_discente(): void
    {
        $consegna = fn () => DB::table('test_consegne')->insert([
            'esame_id' => $this->esame, 'discente_socio_id' => $this->soci[0], 'consegnato_il' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $consegna();

        $this->assertRifiutato($consegna, 'test_consegne_esame_id_discente_socio_id_unique');
    }

    private function domanda(array $extra = []): int
    {
        return DB::table('test_domande')->insertGetId($extra + [
            'esame_id' => $this->esame, 'ordine' => 1, 'testo' => 'D?', 'opzioni' => json_encode(['a', 'b', 'c', 'd']),
            'corretta' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function avvio(string $modalita = 'online', ?string $motivo = null, ?int $discente = null): int
    {
        return DB::table('test_avvii')->insertGetId([
            'esame_id' => $this->esame, 'discente_socio_id' => $discente ?? $this->soci[0], 'modalita' => $modalita,
            'motivo_cartaceo' => $motivo, 'token' => (string) \Illuminate\Support\Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_test_domande_check_corretta_e_quattro_opzioni(): void
    {
        $this->domanda();
        $this->assertRifiutato(fn () => $this->domanda(['ordine' => 2, 'corretta' => 4]), 'test_domande_corretta_check');
        $this->assertRifiutato(fn () => $this->domanda(['ordine' => 2, 'opzioni' => json_encode(['a', 'b', 'c'])]), 'test_domande_opzioni_check');
        $this->assertRifiutato(fn () => $this->domanda(['ordine' => 2, 'opzioni' => json_encode(['x' => 1])]), 'test_domande_opzioni_check');
    }

    public function test_un_solo_avvio_per_discente_e_motivo_obbligatorio_se_cartaceo(): void
    {
        $this->avvio();
        $this->assertRifiutato(fn () => $this->avvio(), 'test_avvii_esame_id_discente_socio_id_unique');
        $this->assertRifiutato(fn () => $this->avvio('cartaceo', null, $this->soci[1]), 'test_avvii_motivo_check');
        $this->assertRifiutato(fn () => $this->avvio('cartaceo', '  ', $this->soci[1]), 'test_avvii_motivo_check');
        $this->assertRifiutato(fn () => $this->avvio('altro', null, $this->soci[1]), 'test_avvii_modalita_check');
        $this->avvio('cartaceo', 'DSA', $this->soci[1]);
    }

    public function test_consegna_cartacea_richiede_chi_inserisce_e_l_online_no(): void
    {
        $inserisci = fn (string $origine, ?int $da) => fn () => DB::table('test_consegne')->insert([
            'esame_id' => $this->esame, 'discente_socio_id' => $this->soci[0], 'origine' => $origine, 'inserito_da' => $da,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertRifiutato($inserisci('cartaceo', null), 'test_consegne_origine_check');
        $this->assertRifiutato($inserisci('online', $this->soci[1]), 'test_consegne_origine_check');
        $this->assertRifiutato($inserisci('fax', null), 'test_consegne_origine_check');
        $inserisci('online', null)(); // bozza di compilazione: consegnato_il NULL è ammesso
        $this->assertNull(DB::table('test_consegne')->value('consegnato_il'));
    }

    public function test_risposta_opzione_fuori_campo_rifiutata(): void
    {
        $domanda = $this->domanda();
        $consegna = DB::table('test_consegne')->insertGetId([
            'esame_id' => $this->esame, 'discente_socio_id' => $this->soci[0], 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertRifiutato(fn () => DB::table('test_risposte')->insert([
            'consegna_id' => $consegna, 'domanda_id' => $domanda, 'opzione' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]), 'test_risposte_opzione_check');
    }

    public function test_configurazione_punteggio_dell_esame_non_negativa(): void
    {
        foreach (['test_punti_corretta' => 0, 'test_penalita_errata' => -1, 'test_penalita_vuota' => -0.5] as $colonna => $valore) {
            $this->assertRifiutato(fn () => DB::table('esami')->where('id', $this->esame)->update([$colonna => $valore]), 'esami_test_punteggio_check');
        }
    }

    public function test_fk_composita_esame_modello(): void
    {
        $evento = DB::table('eventi')->value('id');

        $this->assertRifiutato(fn () => $this->nuovoEsame($this->nuovoCorso($evento), 'XXX', 9), 'esami_modello_foreign');
        $this->assertRifiutato(fn () => $this->nuovoEsame($this->nuovoCorso($evento), 'TCRI', 2), 'esami_modello_foreign');
        $this->assertRifiutato(fn () => DB::table('esame_modelli')->delete(), 'esami_modello_foreign'); // modello in uso
    }

    public function test_un_solo_esame_per_corso_e_corso_non_cancellabile(): void
    {
        $this->assertRifiutato(fn () => $this->nuovoEsame($this->corso), 'esami_corso_id_unique');
        $this->assertRifiutato(fn () => DB::table('corsi')->where('id', $this->corso)->delete(), 'esami_corso_id_foreign');
    }

    public function test_esame_con_dati_non_si_cancella(): void
    {
        DB::table('tcri_colloqui')->insert([
            'esame_id' => $this->esame, 'discente_socio_id' => $this->soci[0], 'tipo' => 'finale', 'punteggio' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertRifiutato(fn () => DB::table('esami')->where('id', $this->esame)->delete(), 'tcri_colloqui_esame_id_foreign');
    }

    public function test_configurazione_senza_dati_si_cancella_a_cascata(): void
    {
        $lab = DB::table('esame_laboratori')->insertGetId(['esame_id' => $this->esame, 'numero' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $gruppo = DB::table('esame_gruppi')->insertGetId(['esame_id' => $this->esame, 'laboratorio_id' => $lab, 'nome' => 'G1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('esame_gruppo_discente')->insert(['gruppo_id' => $gruppo, 'socio_id' => $this->soci[0]]);

        DB::table('esami')->where('id', $this->esame)->delete();

        $this->assertSame(0, DB::table('esame_laboratori')->count() + DB::table('esame_gruppi')->count() + DB::table('esame_gruppo_discente')->count());
    }
}
