<?php

namespace App\Services;

use App\Models\CandidaturaStaff;
use App\Models\IscrizioneStaff;
use App\Models\Presenza;
use App\Models\SocioEvento;

class StaffRimozione
{
    /** Rimuove completamente un membro dallo staff di un evento: SocioEvento, risposta di
     *  pre-registrazione, presenze da form e l'IscrizioneStaff stessa. Se l'iscrizione era
     *  legata a una candidatura online, la riporta in_attesa invece di lasciarla orfana su
     *  "confermato" — punto unico riusato sia dalla rimozione manuale in GestioneStaff sia
     *  dal ritiro conferma in GestioneCandidatureStaff. */
    public static function rimuovi(IscrizioneStaff $iscrizione): void
    {
        SocioEvento::where('evento_id', $iscrizione->evento_id)
            ->where('partecipante_id', $iscrizione->partecipante_id)
            ->where('ruolo', 'staff')
            ->delete();

        $iscrizione->rispostaPreregistrazione?->delete();

        Presenza::where('evento_id', $iscrizione->evento_id)
            ->where('partecipante_id', $iscrizione->partecipante_id)
            ->whereNull('iscrizione_id')
            ->where('fonte', 'form')
            ->delete();

        if ($iscrizione->candidatura_staff_id) {
            CandidaturaStaff::where('id', $iscrizione->candidatura_staff_id)->update([
                'stato'            => 'in_attesa',
                'confermato_at'    => null,
                'confermato_da_id' => null,
            ]);
        }

        $iscrizione->delete();
    }
}
