<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Struttura del modello TCRI v1 (database/data/esame_modelli/tcri_v1.json). */
class EsameModelloTcriV1Test extends TestCase
{
    private array $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = json_decode(file_get_contents(__DIR__ . '/../../database/data/esame_modelli/tcri_v1.json'), true);
    }

    public function test_identificazione_e_pesi(): void
    {
        $this->assertSame('TCRI', $this->m['tipo_codice']);
        $this->assertSame(1, $this->m['versione']);
        $this->assertSame(['itinere' => 30, 'colloquio' => 10, 'prova_pratica' => 40, 'test_scritto' => 20], $this->m['pesi']);
        $this->assertSame(100, array_sum($this->m['pesi']));
    }

    public function test_itinere_4_aree_13_voci_scala_1_5(): void
    {
        $aree = $this->m['itinere']['aree'];
        $this->assertSame([4, 3, 4, 2], array_map(fn ($a) => count($a['voci']), $aree));
        $this->assertSame(13, array_sum(array_map(fn ($a) => count($a['voci']), $aree)));
        $this->assertSame([1, 5], [$this->m['itinere']['scala']['min'], $this->m['itinere']['scala']['max']]);
        $this->assertCount(5, $this->m['itinere']['scala']['livelli']);
    }

    public function test_semaforo_7_competenze_28_voci(): void
    {
        $comp = $this->m['semaforo']['competenze'];
        $this->assertSame([3, 4, 4, 4, 4, 7, 2], array_map(fn ($c) => count($c['voci']), $comp));
        $this->assertSame(28,array_sum(array_map(fn ($c) => count($c['voci']), $comp)));
        $this->assertSame(
            ['rosso' => '0', 'giallo' => '0.25', 'verde' => '1'],
            array_map(fn ($s) => $s['valore'], $this->m['semaforo']['scala'])
        );
    }

    public function test_colloquio_5_livelli(): void
    {
        $this->assertCount(5, $this->m['colloquio']['scala']['livelli']);
        $this->assertCount(5, $this->m['colloquio']['domande_guida']);
    }

    public function test_codici_voce_univoci(): void
    {
        $codici = [];
        foreach ($this->m['itinere']['aree'] as $a) {
            array_push($codici, ...array_column($a['voci'], 'codice'));
        }
        foreach ($this->m['semaforo']['competenze'] as $c) {
            array_push($codici, ...array_column($c['voci'], 'codice'));
        }
        $this->assertCount(41, $codici);
        $this->assertCount(41, array_unique($codici));
    }
}
