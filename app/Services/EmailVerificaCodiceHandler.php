<?php

namespace App\Services;

use App\Models\EmailVerifica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Logica condivisa di verifica del codice a 6 cifre (tentativi/blocco/scadenza),
 * riusata da tutti i form pubblici che agganciano un'email a verificare a un
 * target EmailVerifica (Socio, GraduatoriaCandidato, CandidaturaStaff — vedi
 * EmailVerifica::colonnaTarget()). Prima duplicata identica in
 * GraduatoriaPubblicaController e CandidaturaStaffPubblicaController.
 */
class EmailVerificaCodiceHandler
{
    /**
     * @return array{ok: bool, error: ?string, nessunaVerificaAttiva: bool}
     */
    public static function verifica(Model $target, string $colonna, string $codice): array
    {
        $verifica = EmailVerifica::where($colonna, $target->id)->whereNull('used_at')->latest('id')->first();

        if (! $verifica) {
            return ['ok' => false, 'error' => null, 'nessunaVerificaAttiva' => true];
        }

        if ($verifica->isBloccato()) {
            $minuti = (int) ceil(now()->diffInSeconds($verifica->bloccato_fino_a) / 60);
            return ['ok' => false, 'error' => "Troppi tentativi errati. Riprova tra {$minuti} minuti.", 'nessunaVerificaAttiva' => false];
        }

        if ($verifica->isScaduto()) {
            return ['ok' => false, 'error' => 'Il codice è scaduto.', 'nessunaVerificaAttiva' => false];
        }

        if (! Hash::check($codice, $verifica->codice)) {
            $tentativiAggiornati = $verifica->tentativi_falliti + 1;
            $aggiornamenti = ['tentativi_falliti' => $tentativiAggiornati];

            if ($tentativiAggiornati >= 5) {
                $aggiornamenti['bloccato_fino_a'] = now()->addMinutes(30);
                $verifica->update($aggiornamenti);
                return ['ok' => false, 'error' => 'Troppi tentativi errati. Attendi 30 minuti.', 'nessunaVerificaAttiva' => false];
            }

            $verifica->update($aggiornamenti);
            $rimasti = 5 - $tentativiAggiornati;
            return ['ok' => false, 'error' => "Codice errato. Ti rimangono {$rimasti} tentativi.", 'nessunaVerificaAttiva' => false];
        }

        $verifica->segnaVerificata();

        return ['ok' => true, 'error' => null, 'nessunaVerificaAttiva' => false];
    }
}
