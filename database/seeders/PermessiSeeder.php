<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermessiSeeder extends Seeder
{
    /**
     * Crea tutti i permessi granulari per gli operatori.
     * I permessi sono assegnazioni individuali on/off — non livelli gerarchici.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permessi = [
            // Gestione eventi
            'eventi.view',
            'eventi.create',
            'eventi.edit',

            // Gestione corsi
            'corsi.view',
            'corsi.create',
            'corsi.edit',

            // Gestione iscritti (corsisti)
            'iscritti.view',
            'iscritti.import',
            'iscritti.edit',
            'iscritti.view_sensitive', // CF, telefono, email, indirizzo

            // Email
            'email.send',

            // Check-in segreteria
            'checkin.manage',

            // Graduatorie
            'graduatorie.manage',

            // Report
            'report.view',

            // Questionari
            'questionario.manage',

            // Qualifiche
            'qualifiche.view',   // solo visualizzazione/ricerca
            'qualifiche.manage', // può anche aggiungere/modificare le qualifiche sui soci
            'qualifiche.validita_regionale', // override di scadenza regionali

            // Gestione soci (volontari mobili)
            'soci.view',
            'soci.create',
            'soci.edit',
            'soci.import',
            'soci.view_sensitive', // dati salute (allergie, diete, note alimentari) — non visibili di default agli admin_locale
        ];

        foreach ($permessi as $permesso) {
            Permission::firstOrCreate([
                'name'       => $permesso,
                'guard_name' => 'web',
            ]);
        }

        $this->command->info('Creati ' . count($permessi) . ' permessi granulari.');
    }
}
