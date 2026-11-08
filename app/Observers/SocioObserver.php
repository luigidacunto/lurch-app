<?php

namespace App\Observers;

use App\Models\LogModificaEmail;
use App\Models\Socio;

class SocioObserver
{
    /**
     * Quando l'email di un socio cambia, registra la modifica nel log.
     * Il codice fiscale è l'identificatore stabile: se un import XLSX porta
     * un CF già esistente ma con email diversa, l'email viene aggiornata qui
     * e il log tiene traccia del cambiamento per audit.
     */
    public function updated(Socio $socio): void
    {
        if (! $socio->wasChanged('email')) {
            return;
        }

        $emailVecchia = $socio->getOriginal('email');
        $emailNuova   = $socio->email;

        if ($emailVecchia === null) {
            return;
        }

        LogModificaEmail::create([
            'partecipante_id' => $socio->id,
            'email_vecchia'   => $emailVecchia,
            'email_nuova'     => $emailNuova,
            'fonte'           => 'admin',
        ]);
    }
}
