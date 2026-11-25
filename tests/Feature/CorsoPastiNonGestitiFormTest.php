<?php

namespace Tests\Feature;

use App\Filament\Resources\CorsoResource\Pages\EditCorso;
use App\Models\Corso;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Modulo del corso: i pasti non forniti seguono i giorni e il flag residenziale senza bloccare il salvataggio. */
class CorsoPastiNonGestitiFormTest extends TestCase
{
    use RefreshDatabase;

    private function corso(array $extra = []): Corso
    {
        $now = now();
        $cr = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR Test', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-12-12', 'data_fine' => '2026-12-13', 'giorni_evento' => '["2026-12-12","2026-12-13"]',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return Corso::create($extra + [
            'evento_id' => $evento, 'nome' => 'Corso', 'numero_protocollo' => 'X/2026/P/1',
            'data_inizio' => '2026-12-12', 'ora_inizio' => '09:00', 'data_fine' => '2026-12-13', 'ora_fine' => '18:00',
            'giorni_presenza' => ['2026-12-12', '2026-12-13'], 'direttore_corso' => 'Direttore Esempio',
        ]);
    }

    private function pagina(Corso $corso)
    {
        $this->actingAs(User::create(['name' => 'Su', 'email' => 'su@test.local', 'ruolo' => 'superadmin', 'attivo' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::test(EditCorso::class, ['record' => $corso->getRouteKey()]);
    }

    public function test_togliere_il_giorno_di_un_pasto_non_fornito_non_blocca_il_salvataggio(): void
    {
        $corso = $this->corso(['pasti_non_gestiti' => ['pranzo_2026-12-12', 'pranzo_2026-12-13']]);

        // set() sull'intero array, come il browser: fillForm() scrive gli elementi uno a uno e lancia
        // l'aggiornamento prima di togliere l'elemento in più.
        $this->pagina($corso)
            ->set('data.giorni_presenza', ['2026-12-12'])
            ->assertFormSet(['pasti_non_gestiti' => ['pranzo_2026-12-12']])
            ->call('save')
            ->assertHasNoFormErrors();

        $corso->refresh();
        $this->assertSame(['pranzo_2026-12-12'], $corso->pasti_non_gestiti);
        $this->assertSame([], $corso->form_sections);
    }

    public function test_spegnere_residenziale_toglie_la_cena_non_fornita(): void
    {
        $corso = $this->corso(['residenziale' => true, 'pasti_non_gestiti' => ['cena_2026-12-12']]);

        $this->pagina($corso)
            ->fillForm(['residenziale' => false])
            ->assertFormSet(['pasti_non_gestiti' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($corso->fresh()->pasti_non_gestiti);
    }
}
