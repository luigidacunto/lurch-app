<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\ComitatoRegionale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Barra in alto, pagina Modifica password e permessi degli operatori (scelte del 2026-11-17). */
class PannelloPrimiPassiTest extends TestCase
{
    use RefreshDatabase;

    private function utente(string $ruolo, ?string $password, ?int $regione = null): User
    {
        return User::create([
            'name' => $ruolo, 'email' => $ruolo . uniqid() . '@example.invalid', 'password' => $password,
            'ruolo' => $ruolo, 'attivo' => true, 'comitato_regionale_id' => $regione,
        ]);
    }

    public function test_niente_ricerca_globale_nella_barra_in_alto(): void
    {
        $this->actingAs($this->utente('superadmin', null))->get('/admin')
            ->assertOk()->assertDontSee('fi-global-search', false);
    }

    public function test_modifica_password_solo_per_chi_ha_una_password(): void
    {
        $this->actingAs($this->utente('operatore', null))->get('/admin/modifica-password')->assertForbidden();
        $this->actingAs($this->utente('operatore', 'una-password-lunga'))->get('/admin/modifica-password')
            ->assertOk()->assertSee('Password attuale')->assertDontSee('Info no password');
    }

    public function test_i_permessi_senza_effetto_non_si_vedono_ma_restano(): void
    {
        foreach (['eventi.view', 'corsi.view', 'staff.view'] as $nome) {
            Permission::create(['name' => $nome, 'guard_name' => 'web']);
        }
        $regione   = ComitatoRegionale::create(['nome' => 'Reg', 'codice' => 'R1', 'attivo' => true])->id;
        $operatore = $this->utente('operatore', null, $regione);
        $operatore->givePermissionTo(['eventi.view', 'staff.view']);

        $this->actingAs($this->utente('admin', null, $regione));
        Livewire::test(EditUser::class, ['record' => $operatore->getKey()])
            ->assertFormSet(['permissions' => ['eventi.view']])
            ->fillForm(['permissions' => ['corsi.view']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['corsi.view', 'staff.view'], $operatore->fresh()->getDirectPermissions()->pluck('name')->all());
    }
}
