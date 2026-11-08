<?php

namespace Tests\Unit;

use App\Models\Corso;
use App\Services\FormSectionsGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Regole di generazione voci pasto dai giorni in presenza del corso.
 * Funzione (quasi) pura → test senza DB.
 */
class FormSectionsGeneratorTest extends TestCase
{
    private function tipiPerData(array $sezioni): array
    {
        $out = [];
        foreach ($sezioni as $s) {
            $out[$s['data']][] = $s['tipo'];
        }
        return $out;
    }

    private function genera(array $giorni, bool $residenziale): array
    {
        return (new FormSectionsGenerator())->calcola(new Corso([
            'giorni_presenza' => $giorni,
            'residenziale'    => $residenziale,
        ]));
    }

    public function test_non_residenziale_solo_pranzo_anche_su_giorni_consecutivi(): void
    {
        $t = $this->tipiPerData($this->genera(['2026-10-24', '2026-10-25'], residenziale: false));

        $this->assertSame(['pranzo'], $t['2026-10-24']);
        $this->assertSame(['pranzo'], $t['2026-10-25']);
    }

    public function test_residenziale_cena_e_pernotto_solo_se_ce_un_giorno_subito_dopo(): void
    {
        // 24-25 contigui, poi 07-08 contigui, poi 21 isolato
        $giorni = ['2026-10-24', '2026-10-25', '2026-11-07', '2026-11-08', '2026-11-21'];
        $t = $this->tipiPerData($this->genera($giorni, residenziale: true));

        sort($t['2026-10-24']);
        $this->assertSame(['cena', 'pernotto', 'pranzo'], $t['2026-10-24']); // 25 subito dopo
        $this->assertSame(['pranzo'], $t['2026-10-25']);                     // 26 non selezionato
        sort($t['2026-11-07']);
        $this->assertSame(['cena', 'pernotto', 'pranzo'], $t['2026-11-07']); // 08 subito dopo
        $this->assertSame(['pranzo'], $t['2026-11-08']);
        $this->assertSame(['pranzo'], $t['2026-11-21']);                     // isolato
    }

    public function test_nessun_giorno_nessuna_sezione(): void
    {
        $this->assertSame([], $this->genera([], residenziale: true));
    }

    public function test_colazione_mai_generata(): void
    {
        $sezioni = $this->genera(['2026-10-24', '2026-10-25'], residenziale: true);

        $this->assertNotContains('colazione', array_column($sezioni, 'tipo'));
    }
}
