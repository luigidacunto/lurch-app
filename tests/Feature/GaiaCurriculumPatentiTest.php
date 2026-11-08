<?php

namespace Tests\Feature;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\PatenteCri;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import delle righe "Patente CRI" del curriculum GAIA via GaiaCurriculumController:
 * conversione modello 138/05/19 → 2024 (5b→2b, 9→non convertibile), analisi/importa,
 * riconciliazione delle patenti fonte GAIA non più nel curriculum.
 */
class GaiaCurriculumPatentiTest extends TestCase
{
    use RefreshDatabase;

    private function setup2024(): void
    {
        foreach (['1', '2', '2b', '5', '7b'] as $t) {
            PatenteCri::updateOrCreate(['tipologia' => $t], ['descrizione' => "Tipo $t", 'ordine' => 0, 'attivo' => true]);
        }
    }

    private function socio(): Socio
    {
        $reg = ComitatoRegionale::create(['nome' => 'R', 'codice' => 'R1', 'attivo' => true]);
        $loc = ComitatoLocale::create(['comitato_regionale_id' => $reg->id, 'nome' => 'L', 'codice' => 'L1', 'attivo' => true]);

        return Socio::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'codice_fiscale' => str_pad('1', 16, '0'),
            'comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $loc->id,
        ]);
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create(['name' => 'A', 'email' => 'a@t.local', 'ruolo' => 'superadmin', 'attivo' => true]);
    }

    public function test_analisi_converte_e_classifica(): void
    {
        $this->setup2024();
        $socio = $this->socio();

        $resp = $this->actingAs($this->admin())->postJson("/api/v1/soci/{$socio->id}/qualifiche-gaia-analisi", [
            'righe' => [],
            'patenti' => [
                ['tipologia_raw' => 'PATENTE CRI 5B', 'data_rilascio' => '2009-11-17', 'data_scadenza' => '2014-11-17', 'protocollo' => '038272'],
                ['tipologia_raw' => 'PATENTE CRI 1', 'data_rilascio' => '2009-11-17', 'data_scadenza' => null, 'protocollo' => null],
                ['tipologia_raw' => 'PATENTE CRI 9', 'data_rilascio' => '2009-11-17', 'data_scadenza' => null, 'protocollo' => null],
            ],
        ]);

        $resp->assertOk();
        $patenti = collect($resp->json('patenti'));

        $cinqueB = $patenti->firstWhere('data_scadenza', '2014-11-17');
        $this->assertSame('patente_inserimento', $cinqueB['stato']);
        $this->assertSame(PatenteCri::where('tipologia', '2b')->value('id'), $cinqueB['patente_cri_id']);

        $nove = $patenti->first(fn ($r) => str_contains($r['qualifica_nome_raw'], '9'));
        $this->assertSame('patente_non_convertibile', $nove['stato']);
    }

    public function test_dedup_tiene_la_piu_recente_per_tipologia(): void
    {
        $this->setup2024();
        $socio = $this->socio();

        $resp = $this->actingAs($this->admin())->postJson("/api/v1/soci/{$socio->id}/qualifiche-gaia-analisi", [
            'righe' => [],
            'patenti' => [
                ['tipologia_raw' => 'PATENTE CRI 3', 'data_rilascio' => '2026-06-08', 'data_scadenza' => '2031-12-22', 'protocollo' => 'NUOVO'],
                ['tipologia_raw' => 'PATENTE CRI 3', 'data_rilascio' => '2026-03-25', 'data_scadenza' => '2026-06-08', 'protocollo' => 'VECCHIO'],
            ],
        ]);

        $resp->assertOk();
        $patenti = collect($resp->json('patenti'))->where('tipo_riga', 'patente');
        $this->assertCount(1, $patenti);
        $this->assertSame('2026-06-08', $patenti->first()['data_rilascio']);
        $this->assertSame('NUOVO', $patenti->first()['protocollo']);
    }

    public function test_importa_scrive_e_riconcilia(): void
    {
        $this->setup2024();
        $socio = $this->socio();
        $t2b = PatenteCri::where('tipologia', '2b')->value('id');
        $t7b = PatenteCri::where('tipologia', '7b')->value('id');

        // patente GAIA preesistente che NON sarà nel prossimo curriculum → dev'essere rimossa
        $socio->patenti()->attach($t7b, ['fonte' => 'importazione_gaia']);

        $analisi = $this->actingAs($this->admin())->postJson("/api/v1/soci/{$socio->id}/qualifiche-gaia-analisi", [
            'righe' => [],
            'patenti' => [
                ['tipologia_raw' => '5b', 'data_rilascio' => '2009-11-17', 'data_scadenza' => '2014-11-17', 'protocollo' => '038272'],
            ],
        ])->json();

        $righe = array_merge($analisi['righe'], $analisi['patenti']);
        $resp = $this->actingAs($this->admin())->postJson("/api/v1/soci/{$socio->id}/qualifiche-gaia-importa", ['righe' => $righe]);

        $resp->assertOk();
        $this->assertSame(1, $resp->json('inseriti'));
        $this->assertSame(1, $resp->json('rimossi'));

        $socio->refresh();
        $this->assertFalse($socio->patenti->contains($t7b));
        $pivot = $socio->patenti->firstWhere('id', $t2b)->pivot;
        $this->assertSame('038272', $pivot->protocollo);
        $this->assertSame('importazione_gaia', $pivot->fonte);
    }
}
