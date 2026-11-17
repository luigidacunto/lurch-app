<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin regionale autonomo sulla propria regione: vede e gestisce anche gli altri admin regionali. */
class UtentiAdminRegionaleTest extends TestCase
{
    use RefreshDatabase;

    private function regione(string $codice): int
    {
        return DB::table('comitati_regionali')->insertGetId([
            'nome' => "CR {$codice}", 'codice' => $codice, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function utente(string $ruolo, ?int $regione): User
    {
        return User::create([
            'name' => $ruolo, 'email' => $ruolo . uniqid() . '@example.invalid', 'password' => 'x',
            'ruolo' => $ruolo, 'attivo' => true, 'comitato_regionale_id' => $regione,
        ]);
    }

    public function test_admin_regionale_gestisce_i_colleghi_della_propria_regione_e_non_altri(): void
    {
        $a = $this->regione('RA');
        $b = $this->regione('RB');

        $io        = $this->utente('admin', $a);
        $collega   = $this->utente('admin', $a);
        $operatore = $this->utente('operatore', $a);
        $altro     = $this->utente('admin', $b);
        $super     = $this->utente('superadmin', null);

        $this->actingAs($io);

        $visibili = UserResource::getEloquentQuery()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$io->id, $collega->id, $operatore->id], $visibili);

        foreach ([$collega, $operatore] as $u) {
            $this->assertTrue(UserResource::canEdit($u));
            $this->assertTrue(UserResource::canDelete($u));
            $this->assertTrue(UserResource::canResetSessioni($u));
        }

        foreach ([$io, $altro, $super] as $u) {
            $this->assertFalse(UserResource::canEdit($u));
            $this->assertFalse(UserResource::canDelete($u));
            $this->assertFalse(UserResource::canResetSessioni($u));
        }
    }

    public function test_admin_locale_resta_limitato_agli_operatori_senza_eliminazione(): void
    {
        $a = $this->regione('RA');
        $locale = DB::table('comitati_locali')->insertGetId([
            'nome' => 'CL', 'comitato_regionale_id' => $a, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $io = $this->utente('admin_locale', $a);
        $io->update(['comitato_locale_id' => $locale]);
        $operatore = $this->utente('operatore', $a);
        $operatore->update(['comitato_locale_id' => $locale]);
        $admin = $this->utente('admin', $a);

        $this->actingAs($io);

        $this->assertTrue(UserResource::canResetSessioni($operatore));
        $this->assertFalse(UserResource::canDelete($operatore));
        $this->assertFalse(UserResource::canResetSessioni($admin));
    }
}
