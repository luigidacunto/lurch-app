<?php

namespace Tests\Feature;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** La pagina di riferimento interna è solo del superadmin, su ogni sua scheda. */
class PrototipoSubNavAccessoTest extends TestCase
{
    use RefreshDatabase;

    private const PAGINE = ['', 'panoramica', 'staff', 'badge', 'vetrina'];

    public static function ruoli(): array
    {
        return [['operatore', 403], ['admin_locale', 403], ['admin', 403], ['superadmin', 200]];
    }

    #[DataProvider('ruoli')]
    public function test_solo_il_superadmin_apre_il_prototipo(string $ruolo, int $atteso): void
    {
        $reg = ComitatoRegionale::create(['nome' => 'Reg', 'codice' => 'R1', 'attivo' => true]);
        $loc = ComitatoLocale::create(['comitato_regionale_id' => $reg->id, 'nome' => 'Loc', 'codice' => 'L1', 'attivo' => true]);
        $evento = Evento::create([
            'nome' => 'Evento Test', 'comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $loc->id,
            'data_inizio' => '2026-10-24', 'data_fine' => '2026-10-25', 'giorni_evento' => ['2026-10-24', '2026-10-25'],
            'sede' => 'Sede di prova', 'indirizzo' => 'Via Test 1',
        ]);
        $utente = User::create([
            'name' => $ruolo, 'email' => "{$ruolo}@example.invalid", 'password' => 'x', 'ruolo' => $ruolo, 'attivo' => true,
            'comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $loc->id,
        ]);

        foreach (self::PAGINE as $p) {
            $url = '/admin/prototipo-subnav' . ($p === '' ? '' : "/{$evento->id}/{$p}");
            $this->actingAs($utente)->get($url)->assertStatus($atteso);
        }
    }
}
