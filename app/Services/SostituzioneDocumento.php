<?php

namespace App\Services;

use App\Models\RispostaPreregistrazione;
use Illuminate\Support\Facades\Storage;

/**
 * Sostituzione di un documento allegato a una RispostaPreregistrazione (ricevuta di pagamento
 * o nullaosta), consentita SOLO finché il documento non è stato verificato da un operatore.
 *
 * Punto unico condiviso da: admin (GestioneQuote / GestioneCorso / GestioneStaff), form
 * pubblici via link (FormPreregistrazione*Controller) e form PWA (App\AppForm*Controller).
 * La verifica di applicabilità dello slot (pagamento previsto? cross-regione? logistica?)
 * resta al chiamante — richiede il contesto dell'iscrizione, non della sola risposta.
 *
 * Il file nuovo deve essere GIÀ salvato sul disco 'local' quando si chiama sostituisci():
 * i controller fanno $request->file()->store(...), Filament FileUpload lo salva da sé.
 */
class SostituzioneDocumento
{
    /** slot => [colonna file, colonna "verificato"] */
    private const SLOT = [
        'ricevuta'  => ['ricevuta_path', 'pagamento_verificato'],
        'nullaosta' => ['nullaosta_path', 'nullaosta_verificato'],
        'documento_aggiuntivo' => ['documento_aggiuntivo_path', 'documento_aggiuntivo_verificato'],
    ];

    public static function slotValido(string $slot): bool
    {
        return isset(self::SLOT[$slot]);
    }

    public function verificato(RispostaPreregistrazione $risposta, string $slot): bool
    {
        return (bool) $risposta->{self::SLOT[$slot][1]};
    }

    /**
     * Sostituisce il file dello slot con quello a $nuovoPath (relativo al disco 'local',
     * già salvato). Elimina il file precedente. Rifiuta — senza scrivere nulla — se il
     * documento risulta già verificato (in quel caso ripulisce l'upload appena fatto).
     *
     * @return bool true se sostituito, false se bloccato perché già verificato
     */
    public function sostituisci(RispostaPreregistrazione $risposta, string $slot, string $nuovoPath): bool
    {
        [$colonnaFile] = self::SLOT[$slot];

        if ($this->verificato($risposta, $slot)) {
            Storage::disk('local')->delete($nuovoPath);
            return false;
        }

        $vecchioPath = $risposta->{$colonnaFile};

        $risposta->update([$colonnaFile => $nuovoPath]);

        if ($vecchioPath && $vecchioPath !== $nuovoPath) {
            Storage::disk('local')->delete($vecchioPath);
        }

        return true;
    }
}
