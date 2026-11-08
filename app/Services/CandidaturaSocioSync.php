<?php

namespace App\Services;

use App\Models\EmailVerifica;
use App\Models\Socio;

/**
 * Trova/crea il Socio a partire dai dati di una candidatura confermata da un
 * operatore (Graduatorie, candidature staff): contesto admin, quindi email
 * scritta direttamente (a differenza del form pubblico che passa da
 * email_in_verifica). Innesca la verifica email se necessaria.
 */
class CandidaturaSocioSync
{
    /**
     * @param array{socio_id?: ?int, codice_fiscale: string, nome: string, cognome: string,
     *              email: string, telefono: ?string, comitato_regionale_id: ?int, comitato_locale_id: ?int,
     *              email_verificata_at?: ?\Carbon\CarbonInterface} $dati
     */
    public static function sincronizza(array $dati): Socio
    {
        $socio = ($dati['socio_id'] ?? null)
            ? Socio::find($dati['socio_id'])
            : Socio::where('codice_fiscale', $dati['codice_fiscale'])->first();

        // Se la candidatura di provenienza aveva già verificato questa stessa email
        // (CF non trovato al momento della domanda, verificata comunque sulla
        // candidatura — vedi EmailVerifica), riportarla qui evita di richiedere una
        // nuova verifica appena il socio viene agganciato/creato: l'hook di
        // Socio::booted() non la azzera perché viene scritta nello stesso save.
        $emailVerificataAt = $dati['email_verificata_at'] ?? null;

        if ($socio) {
            $socio->update([
                'email'                 => $dati['email'],
                'telefono'              => $dati['telefono'] ?? null,
                'comitato_regionale_id' => $dati['comitato_regionale_id'] ?? $socio->comitato_regionale_id,
                'comitato_locale_id'    => $dati['comitato_locale_id'] ?? $socio->comitato_locale_id,
                ...($emailVerificataAt ? ['email_verificata_at' => $emailVerificataAt] : []),
            ]);
        } else {
            $socio = Socio::create([
                'nome'                  => $dati['nome'],
                'cognome'               => $dati['cognome'],
                'codice_fiscale'        => $dati['codice_fiscale'],
                'email'                 => $dati['email'],
                'telefono'              => $dati['telefono'] ?? null,
                'comitato_regionale_id' => $dati['comitato_regionale_id'] ?? null,
                'comitato_locale_id'    => $dati['comitato_locale_id'] ?? null,
                ...($emailVerificataAt ? ['email_verificata_at' => $emailVerificataAt] : []),
            ]);
        }

        EmailVerifica::inviaSeNecessario($socio);

        return $socio;
    }
}
