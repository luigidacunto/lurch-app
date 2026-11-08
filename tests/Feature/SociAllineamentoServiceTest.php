<?php

namespace Tests\Feature;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Socio;
use App\Services\SociAllineamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Allineamento soci da import GAIA: chi non compare più nella lista di un comitato viene
 * segnato dimesso (`dimesso_il`), chi non è più in estensione perde la sola estensione, chi
 * ricompare viene riattivato.
 */
class SociAllineamentoServiceTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function regionale(): ComitatoRegionale
    {
        self::$seq++;
        return ComitatoRegionale::create(['nome' => "Regione " . self::$seq, 'codice' => "R" . self::$seq, 'attivo' => true]);
    }

    private function locale(ComitatoRegionale $r): ComitatoLocale
    {
        self::$seq++;
        return ComitatoLocale::create([
            'comitato_regionale_id' => $r->id,
            'nome' => "Locale " . self::$seq,
            'codice' => "L" . self::$seq,
            'attivo' => true,
        ]);
    }

    private function socio(array $attr): Socio
    {
        self::$seq++;
        return Socio::create(array_merge([
            'nome' => 'Nome' . self::$seq,
            'cognome' => 'Cognome' . self::$seq,
            'codice_fiscale' => str_pad((string) self::$seq, 16, '0', STR_PAD_LEFT),
        ], $attr));
    }

    public function test_segna_dimessi_i_volontari_mancanti_dallambito_locale(): void
    {
        $reg = $this->regionale();
        $a   = $this->locale($reg);
        $b   = $this->locale($reg);

        $presente = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $a->id]);
        $mancante = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $a->id]);
        $altroComitato = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $b->id]);

        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima([$presente->codice_fiscale], [], $a->id, null, true, false);

        $this->assertEqualsCanonicalizing([$mancante->id], $ant['dimessi']->pluck('id')->all());

        $svc->applica($ant);

        $this->assertNotNull($mancante->fresh()->dimesso_il);
        $this->assertNull($presente->fresh()->dimesso_il);
        $this->assertNull($altroComitato->fresh()->dimesso_il, 'un socio di un altro comitato non va toccato');
    }

    public function test_chi_compare_solo_come_estensione_non_e_dimesso(): void
    {
        // Era appartenente di A, ora si è trasferito altrove ma mantiene l'estensione verso A:
        // nel file di A (con estensioni) compare solo come "Volontario in Estensione".
        $reg = $this->regionale();
        $a   = $this->locale($reg);

        $presente   = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $a->id]);
        $trasferito = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $a->id]);

        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima(
            [$presente->codice_fiscale],       // appartenenti
            [$trasferito->codice_fiscale],     // estensioni
            $a->id, null, true, true,
        );

        $this->assertNotContains($trasferito->id, $ant['dimessi']->pluck('id')->all());
        $this->assertEmpty($ant['dimessi']->all(), 'nessun dimesso: entrambi presenti nel file');
    }

    public function test_non_tocca_il_rapporto_di_dipendente(): void
    {
        $reg = $this->regionale();
        $a   = $this->locale($reg);

        // Volontario in A + dipendente in A. Sparisce dalla lista volontari.
        $misto = $this->socio([
            'comitato_regionale_id' => $reg->id,
            'comitato_locale_id' => $a->id,
            'comitato_locale_dipendente_id' => $a->id,
            'comitato_regionale_dipendente_id' => $reg->id,
            'ruolo_associativo' => 'dipendente',
        ]);
        // Dipendente puro (nessuna appartenenza volontario): mai segnato.
        $dipendentePuro = $this->socio([
            'comitato_regionale_dipendente_id' => $reg->id,
            'comitato_locale_dipendente_id' => $a->id,
            'ruolo_associativo' => 'dipendente',
        ]);

        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima([], [], $a->id, null, true, false);
        $svc->applica($ant);

        $this->assertNotNull($misto->fresh()->dimesso_il);
        $this->assertSame($a->id, $misto->fresh()->comitato_locale_dipendente_id, 'il rapporto di lavoro resta');
        $this->assertNull($dipendentePuro->fresh()->dimesso_il, 'un dipendente puro non viene mai segnato');
    }

    public function test_rientro_riattiva_un_dimesso_di_nuovo_in_lista(): void
    {
        $reg = $this->regionale();
        $a   = $this->locale($reg);
        $tornato = $this->socio([
            'comitato_regionale_id' => $reg->id,
            'comitato_locale_id' => $a->id,
            'dimesso_il' => now()->subMonth()->toDateString(),
        ]);

        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima([$tornato->codice_fiscale], [], $a->id, null, true, false);

        $this->assertEqualsCanonicalizing([$tornato->id], $ant['rientri']->pluck('id')->all());

        $svc->applica($ant);
        $this->assertNull($tornato->fresh()->dimesso_il);
    }

    public function test_pulizia_estensioni_rimuove_solo_lestensione(): void
    {
        $reg = $this->regionale();
        $a   = $this->locale($reg);
        $b   = $this->locale($reg);

        $inEstensione = $this->socio([
            'comitato_regionale_id' => $reg->id,
            'comitato_locale_id' => $b->id,
            'comitato_locale_estensione_id' => $a->id,
            'comitato_regionale_estensione_id' => $reg->id,
        ]);

        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima([], [], $a->id, null, false, true);

        $this->assertEqualsCanonicalizing([$inEstensione->id], $ant['estensioniDaRimuovere']->pluck('id')->all());

        $svc->applica($ant);

        $fresh = $inEstensione->fresh();
        $this->assertNull($fresh->comitato_locale_estensione_id);
        $this->assertNull($fresh->comitato_regionale_estensione_id);
        $this->assertNull($fresh->dimesso_il, 'la pulizia estensioni non segna dimessi');
        $this->assertSame($b->id, $fresh->comitato_locale_id, 'appartenenza intatta');
    }

    public function test_cambio_comitato_riattiva_un_dimesso_hook_saving(): void
    {
        $reg = $this->regionale();
        $a   = $this->locale($reg);
        $b   = $this->locale($reg);

        $dimesso = $this->socio([
            'comitato_regionale_id' => $reg->id,
            'comitato_locale_id' => $a->id,
            'dimesso_il' => now()->subMonth()->toDateString(),
        ]);

        // Trasferimento (es. da form pubblico / modifica manuale): cambia il comitato.
        $dimesso->update(['comitato_locale_id' => $b->id]);
        $this->assertNull($dimesso->fresh()->dimesso_il);

        // Un salvataggio che NON tocca il comitato non riattiva.
        $ancora = $this->socio([
            'comitato_regionale_id' => $reg->id,
            'comitato_locale_id' => $a->id,
            'dimesso_il' => now()->subMonth()->toDateString(),
        ]);
        $ancora->update(['telefono' => '3331234567']);
        $this->assertNotNull($ancora->fresh()->dimesso_il);
    }

    public function test_ambito_regionale_confronta_tutti_i_comitati_della_regione(): void
    {
        $reg = $this->regionale();
        $a   = $this->locale($reg);
        $b   = $this->locale($reg);
        $altraRegione = $this->regionale();
        $c = $this->locale($altraRegione);

        $presenteA = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $a->id]);
        $mancanteA = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $a->id]);
        $mancanteB = $this->socio(['comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $b->id]);
        $altraReg  = $this->socio(['comitato_regionale_id' => $altraRegione->id, 'comitato_locale_id' => $c->id]);

        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima([$presenteA->codice_fiscale], [], null, $reg->id, true, false);

        $this->assertEqualsCanonicalizing([$mancanteA->id, $mancanteB->id], $ant['dimessi']->pluck('id')->all());
        $this->assertNotContains($altraReg->id, $ant['dimessi']->pluck('id')->all());
    }
}
