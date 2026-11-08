<?php

namespace Tests\Feature;

use App\Esami\AvvioTest;
use App\Esami\ImportaTest;
use App\Esami\TestException;
use App\Filament\Resources\EsameResource;
use App\Models\TestDomanda;
use Livewire\Livewire;

/**
 * Import del test scritto da JSON: formato, validazione tutto-o-niente, sostituzione di tutte le domande, permessi e
 * blocco dopo l'avvio. DB di test: lurch_test.
 */
class EsameTestImportTest extends EsameTestScrittoBase
{
    private function payload(array $domande): string
    {
        return json_encode(['domande' => $domande], JSON_UNESCAPED_UNICODE);
    }

    private function d(string $testo = 'Quanto fa 2+2?', array $opzioni = ['3', '4', '5', '6'], string $corretta = 'B'): array
    {
        return ['testo' => $testo, 'opzioni' => $opzioni, 'corretta' => $corretta];
    }

    public function test_l_esempio_del_prompt_e_valido(): void
    {
        $domande = ImportaTest::leggi(ImportaTest::ESEMPIO);
        $this->assertCount(1, $domande);
        $this->assertSame(1, $domande[0]['corretta']);
        $this->assertStringContainsString('"corretta"', ImportaTest::PROMPT);
    }

    public function test_importa_n_domande_con_ordine_lettere_e_testi_normalizzati(): void
    {
        $n = ImportaTest::importa($this->esame, $this->u['direttore'], $this->payload([
            $this->d('  Prima  ', [' a ', 'b', 'c', 'd'], 'a'),
            $this->d('Seconda', ['1', '2', '3', '4'], 'd'),
            $this->d('Terza', ['x', 'y', 'z', 'w'], ' c '),
        ]));
        $this->assertSame(3, $n);
        $righe = TestDomanda::where('esame_id', $this->esame->id)->orderBy('ordine')->get();
        $this->assertSame([1, 2, 3], $righe->pluck('ordine')->all());
        $this->assertSame(['Prima', 'Seconda', 'Terza'], $righe->pluck('testo')->all());
        $this->assertSame([0, 3, 2], $righe->map(fn ($r) => (int) $r->makeVisible('corretta')->corretta)->all());
        $this->assertSame(['a', 'b', 'c', 'd'], $righe[0]->opzioni);
    }

    public function test_sostituisce_tutte_le_domande_e_non_tocca_il_punteggio(): void
    {
        $this->domande(5);
        $this->esame->update(['test_penalita_attive' => true, 'test_punti_corretta' => '2.0', 'test_penalita_errata' => '1.0', 'test_penalita_vuota' => '0.5']);

        ImportaTest::importa($this->esame, $this->u['direttore'], $this->payload([$this->d(), $this->d('Altra')]));

        $this->assertSame(2, TestDomanda::where('esame_id', $this->esame->id)->count());
        $this->assertSame(['Quanto fa 2+2?', 'Altra'], TestDomanda::where('esame_id', $this->esame->id)->orderBy('ordine')->pluck('testo')->all());
        $e = $this->esame->fresh();
        $this->assertTrue((bool) $e->test_penalita_attive);
        $this->assertSame('2.0', (string) $e->test_punti_corretta);
    }

    public function test_accetta_blocco_markdown_bom_e_elenco_semplice(): void
    {
        $this->assertCount(1, ImportaTest::leggi("```json\n" . ImportaTest::ESEMPIO . "\n```"));
        $this->assertCount(1, ImportaTest::leggi("\xEF\xBB\xBF" . ImportaTest::ESEMPIO));
        $this->assertCount(1, ImportaTest::leggi(json_encode([$this->d()])));
    }

    public function test_un_errore_non_importa_nulla_e_dice_quali_domande(): void
    {
        $this->domande(5);
        try {
            ImportaTest::importa($this->esame, $this->u['direttore'], $this->payload([
                $this->d(),
                $this->d('Tre opzioni', ['a', 'b', 'c']),
                $this->d('Lettera sbagliata', ['a', 'b', 'c', 'd'], 'E'),
                $this->d('Doppie', ['a', 'A', 'c', 'd']),
                $this->d('', ['a', 'b', 'c', 'd']),
            ]));
            $this->fail('doveva rifiutare');
        } catch (TestException $e) {
            $this->assertSame(422, $e->stato);
            foreach (['Domanda 2', 'Domanda 3', 'Domanda 4', 'Domanda 5'] as $k) {
                $this->assertStringContainsString($k, $e->getMessage());
            }
            $this->assertStringNotContainsString('Domanda 1:', $e->getMessage());
        }
        $this->assertSame(5, TestDomanda::where('esame_id', $this->esame->id)->count(), 'le domande attuali non cambiano');
    }

    public function test_json_non_valido_vuoto_o_senza_domande(): void
    {
        foreach (['', 'non è json', '{"domande": []}', '{"altro": 1}', '{"domande": "x"}'] as $testo) {
            try {
                ImportaTest::leggi($testo);
                $this->fail("doveva rifiutare: {$testo}");
            } catch (TestException $e) {
                $this->assertSame(422, $e->stato);
            }
        }
    }

    public function test_dopo_l_avvio_non_si_importa_e_le_domande_restano(): void
    {
        $this->domande(5);
        AvvioTest::avvia($this->esame->fresh(), $this->u['direttore']);

        $this->expectException(TestException::class);
        try {
            ImportaTest::importa($this->esame->fresh(), $this->u['direttore'], ImportaTest::ESEMPIO);
        } finally {
            $this->assertSame(5, TestDomanda::where('esame_id', $this->esame->id)->count());
        }
    }

    public function test_solo_chi_prepara_importa(): void
    {
        foreach (['docComm', 'docente', 'presidente'] as $k) {
            try {
                ImportaTest::importa($this->esame, $this->u[$k], ImportaTest::ESEMPIO);
                $this->fail("{$k} non doveva poter importare");
            } catch (TestException $e) {
                $this->assertSame(403, $e->stato, $k);
            }
        }
        $this->assertSame(0, TestDomanda::where('esame_id', $this->esame->id)->count());
    }

    public function test_azione_del_pannello_con_json_incollato(): void
    {
        $this->domande(5);
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\DomandeTestEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Importa il test da JSON')->assertSee('Importare le domande da un file Word o PDF')
            ->callAction('importaTest', ['testo' => $this->payload([$this->d(), $this->d('B2')])]);
        $this->assertSame(2, TestDomanda::where('esame_id', $this->esame->id)->count());

        // JSON errato: niente cambia
        Livewire::test(EsameResource\Pages\DomandeTestEsame::class, ['record' => $this->esame->getRouteKey()])
            ->callAction('importaTest', ['testo' => '{"domande":[{"testo":"x"}]}']);
        $this->assertSame(2, TestDomanda::where('esame_id', $this->esame->id)->count());

        // chi non prepara non vede il pulsante né il blocco del prompt
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\DomandeTestEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertDontSee('Importa il test da JSON')->assertDontSee('Importare le domande da un file Word o PDF');
    }
}
