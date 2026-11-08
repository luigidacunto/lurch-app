<?php

namespace Tests\Feature;

use App\Models\EsameModello;
use Database\Seeders\EsameModelliSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Seeder dei modelli d'esame: crea se manca, non fa nulla se identico, lo salta se diverso (DB di test: lurch_test). */
class EsameModelliSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_manca_lo_crea_e_la_struttura_e_caricata(): void
    {
        $this->seed(EsameModelliSeeder::class);

        $this->assertSame(1, EsameModello::where('tipo_codice', 'TCRI')->where('versione', 1)->count());

        $m = EsameModello::where('tipo_codice', 'TCRI')->firstOrFail()->struttura;
        $this->assertCount(4, $m['itinere']['aree']);
        $this->assertCount(7, $m['semaforo']['competenze']);
    }

    public function test_se_identico_non_fa_nulla(): void
    {
        $this->seed(EsameModelliSeeder::class);
        $riga = EsameModello::firstOrFail();
        $marker = now()->subYear()->startOfSecond();
        EsameModello::whereKey($riga->id)->update(['updated_at' => $marker]);

        $this->seed(EsameModelliSeeder::class);

        $this->assertSame(1, EsameModello::count());
        $this->assertTrue($marker->equalTo(EsameModello::firstOrFail()->updated_at));
    }

    public function test_se_diverso_lo_salta_senza_sovrascrivere(): void
    {
        $this->seed(EsameModelliSeeder::class);
        $riga = EsameModello::firstOrFail();
        $modificata = $riga->struttura;
        $modificata['pesi']['itinere'] = 99;
        $riga->update(['struttura' => $modificata]);

        $this->seed(EsameModelliSeeder::class);

        $this->assertSame(1, EsameModello::count());
        $this->assertSame(99, EsameModello::firstOrFail()->struttura['pesi']['itinere']);
    }
}
