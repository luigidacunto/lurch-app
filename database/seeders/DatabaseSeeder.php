<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermessiSeeder::class,        // 1. permessi granulari (richiesti da Spatie prima dei ruoli)
            ComitatoSeeder::class,        // 2. comitati regionali ufficiali CRI
            ComitatoLocaleSeeder::class,  // 3. comitati locali da file JSON in database/data/
            SuperadminSeeder::class,      // 4. utente superadmin da .env
            CatalogoCorsiSeeder::class,      // 5. catalogo corsi CRI da database/data/catalogo_corsi.json
            QualifichePatentiSeeder::class,  // 6. patenti CRI (catalogo)
            OpzioneAlimentareSeeder::class,  // 7. allergeni e diete da database/data/opzioni_alimentari.json
            PrivacyTemplateSeeder::class,    // 8. template privacy GDPR globali
            EmailTemplateSeeder::class,      // 9. template email form preregistrazione
            EsameModelliSeeder::class,       // 10. modelli d'esame (TCRI v1) da database/data/esame_modelli/
        ]);
    }
}
