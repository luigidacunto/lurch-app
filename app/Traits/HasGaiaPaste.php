<?php

namespace App\Traits;

use Filament\Notifications\Notification;

trait HasGaiaPaste
{
    public function riempiDaGaia(array $data): void
    {
        if (empty($data['corso'])) return;

        if (isset($data['corso']))       $this->data['nome']                 = $data['corso'];
        if (isset($data['protocollo']))  $this->data['numero_protocollo']    = $data['protocollo'];
        // Il direttore si sceglie da un socio (direttore_socio_id), che poi riscrive il testo. Finché non è
        // scelto, il nome incollato resta nel testo (sola lettura) da copiare e cercare nella select.
        if (! empty($data['direttore']) && blank($this->data['direttore_socio_id'] ?? null)) {
            $this->data['direttore_corso'] = $data['direttore'];
        }
        if (isset($data['linkGaia']))    $this->data['link_gaia']            = $data['linkGaia'];
        if (array_key_exists('quota', $data))       $this->data['pagamento_previsto']   = (bool) $data['quota'];
        if (array_key_exists('quotaValore', $data)) $this->data['quota_partecipazione'] = $data['quotaValore'];

        if (! empty($data['dataInizio'])) {
            $inizio = \Carbon\Carbon::parse($data['dataInizio']);
            $this->data['data_inizio'] = $inizio->toDateString();
            $this->data['ora_inizio']  = $inizio->format('H:i');
        }
        if (! empty($data['dataEsame'])) {
            $fine = \Carbon\Carbon::parse($data['dataEsame']);
            $this->data['data_fine'] = $fine->toDateString();
            $this->data['ora_fine']  = $fine->format('H:i');
        }
        if (! empty($data['scadenzaDomande'])) {
            $this->data['scadenza_domande'] = \Carbon\Carbon::parse($data['scadenzaDomande'])->toDateTimeString();
        }

        $notifica = Notification::make()
            ->title('Dati GAIA incollati: ' . $data['corso'])
            ->success();

        $this->dispatch('notificationSent', notification: $notifica->toArray());
    }
}
