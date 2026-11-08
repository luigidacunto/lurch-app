<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperadminSeeder extends Seeder
{
    /**
     * Crea l'utente superadmin dal .env.
     * Upsert sul campo email: se esiste già aggiorna senza duplicare.
     */
    public function run(): void
    {
        $email    = env('SUPERADMIN_EMAIL', 'admin@lurch.local');
        $password = env('SUPERADMIN_PASSWORD', 'password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'                  => 'Super Admin',
                'password'              => Hash::make($password),
                'ruolo'                 => 'superadmin',
                'comitato_regionale_id' => null,
                'attivo'                => true,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
        ]);

        $user->syncRoles([$role->name]);

        $this->command->info("Superadmin pronto: {$email}");
    }
}
