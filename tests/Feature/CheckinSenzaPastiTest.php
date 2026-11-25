<?php

namespace Tests\Feature;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Corso;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\SocioEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Primo check-in: alimentazione e consenso salute solo se la persona ha pasti all'evento. */
class CheckinSenzaPastiTest extends TestCase
{
    use RefreshDatabase;

    private function badge(array $pastiNonGestiti): string
    {
        $reg = ComitatoRegionale::create(['nome' => 'Reg', 'codice' => 'R1', 'attivo' => true]);
        $loc = ComitatoLocale::create(['comitato_regionale_id' => $reg->id, 'nome' => 'Loc', 'codice' => 'L1', 'attivo' => true]);
        $giorno = now()->addDays(10)->format('Y-m-d');

        $evento = Evento::create([
            'nome' => 'Evento', 'comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $loc->id,
            'data_inizio' => $giorno, 'data_fine' => $giorno, 'giorni_evento' => [$giorno], 'sede' => 's', 'indirizzo' => 'i',
        ]);
        $corso = Corso::create([
            'evento_id' => $evento->id, 'nome' => 'Corso', 'numero_protocollo' => 'X/1',
            'data_inizio' => $giorno, 'data_fine' => $giorno, 'ora_inizio' => '09:00', 'ora_fine' => '18:00',
            'giorni_presenza' => [$giorno], 'pasti_non_gestiti' => array_map(fn ($t) => "{$t}_{$giorno}", $pastiNonGestiti),
        ]);
        $socio = Socio::create(['nome' => 'Mario', 'cognome' => 'Esempio', 'codice_fiscale' => 'XXXXXXXXXXXXXXX1',
            'comitato_regionale_id' => $reg->id, 'comitato_locale_id' => $loc->id]);
        Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $socio->id, 'stato' => 'pending', 'token' => (string) Str::uuid()]);
        $token = (string) Str::uuid();
        SocioEvento::firstOrCreate(['evento_id' => $evento->id, 'partecipante_id' => $socio->id], ['ruolo' => 'corsista', 'badge_token' => $token]);
        $token = SocioEvento::where('evento_id', $evento->id)->value('badge_token');

        $this->actingAs(User::create(['name' => 'Su', 'email' => 'su@test.local', 'ruolo' => 'superadmin', 'attivo' => true]));

        return route('badge.portale', [$evento->id, $token]);
    }

    public function test_senza_pasti_niente_alimentazione_ne_consenso_salute(): void
    {
        // note_extra = campo sempre presente del blocco Alimentazione (le caselle dipendono dal catalogo).
        $this->get($this->badge(['pranzo']))->assertOk()
            ->assertDontSee('name="note_extra"', false)
            ->assertDontSee('name="consenso_salute_accettata"', false)
            ->assertSee('name="privacy_accettata"', false);
    }

    public function test_con_un_pasto_alimentazione_e_consenso_salute_presenti(): void
    {
        $this->get($this->badge([]))->assertOk()
            ->assertSee('name="note_extra"', false)
            ->assertSee('name="consenso_salute_accettata"', false);
    }
}
