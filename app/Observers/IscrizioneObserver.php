<?php

namespace App\Observers;

use App\Jobs\CheckSovrapposizioniSocio;
use App\Models\Iscrizione;

class IscrizioneObserver
{
    /**
     * Nuova iscrizione: controlla se il partecipante ha ora sovrapposizioni.
     */
    public function created(Iscrizione $iscrizione): void
    {
        if ($iscrizione->partecipante_id) {
            CheckSovrapposizioniSocio::dispatch($iscrizione->partecipante_id);
        }
    }

    /**
     * Ritiro (o reintegro) dal corso: le iscrizioni ritirate non generano conflitti, quindi cambiando
     * `stato` tra "ritirato" e non-ritirato i conflitti vanno ricalcolati.
     */
    public function updated(Iscrizione $iscrizione): void
    {
        if ($iscrizione->partecipante_id && $iscrizione->wasChanged('stato')
            && ($iscrizione->stato === 'ritirato' || $iscrizione->getOriginal('stato') === 'ritirato')) {
            CheckSovrapposizioniSocio::dispatch($iscrizione->partecipante_id);
        }
    }

    /**
     * Iscrizione eliminata: ricalcola per rimuovere eventuali conflitti risolti.
     */
    public function deleted(Iscrizione $iscrizione): void
    {
        if ($iscrizione->partecipante_id) {
            CheckSovrapposizioniSocio::dispatch($iscrizione->partecipante_id);
        }
    }
}
