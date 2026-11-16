<?php

namespace App\Services;

use App\Exceptions\PostaRegionaleNonDisponibile;
use App\Models\ComitatoRegionale;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Account di posta per regione: ogni comitato regionale invia con il proprio account SMTP
 * (quote separate). Due regole:
 * - mail di eventi, staff e graduatorie: account della regione del dato, nessun ripiego;
 * - accessi e verifiche email: account della regione della persona, ripiego sull'account generale (.env).
 * Con un mailer generale diverso da SMTP (log in sviluppo, array nei test) non ci si collega mai al
 * server della regione: la mail esce dal mailer generale con il mittente della regione.
 */
final class PostaRegionale
{
    /** Mail di eventi, staff e graduatorie. Lancia PostaRegionaleNonDisponibile se la regione non ha l'account. */
    public static function perEvento(?int $comitatoRegionaleId): Mailer
    {
        $regione = $comitatoRegionaleId ? ComitatoRegionale::find($comitatoRegionaleId) : null;

        if (! $regione?->haPostaConfigurata()) {
            throw new PostaRegionaleNonDisponibile(
                ($regione?->nome ?? 'La regione') . ': posta in uscita non configurata, invio bloccato. '
                . 'Va completata la sezione «Posta in uscita» del comitato regionale.'
            );
        }

        return self::mailer($regione);
    }

    /**
     * Codici di accesso e verifiche email: account della regione, altrimenti quello generale.
     * Tipo dell'interfaccia: il mailer generale può essere sostituito (Mail::fake nei test).
     */
    public static function perPersona(?int $comitatoRegionaleId): MailerContract
    {
        $regione = $comitatoRegionaleId ? ComitatoRegionale::find($comitatoRegionaleId) : null;

        if (! $regione?->haPostaConfigurata()) {
            return Mail::mailer();
        }

        try {
            return self::mailer($regione);
        } catch (PostaRegionaleNonDisponibile $e) {
            Log::warning($e->getMessage() . ' Uso l\'account generale.');

            return Mail::mailer();
        }
    }

    private static function mailer(ComitatoRegionale $regione): Mailer
    {
        // Presente (haPostaConfigurata legge il valore grezzo) ma null dopo la decifratura = cifrata con un'altra chiave.
        $password = $regione->mail_password;
        if ($password === null) {
            throw new PostaRegionaleNonDisponibile(
                "{$regione->nome}: la password SMTP salvata non è leggibile con la chiave di questa installazione. "
                . 'Va reinserita nella sezione «Posta in uscita» del comitato regionale.'
            );
        }

        $generale = (string) config('mail.default');

        if (config("mail.mailers.{$generale}.transport") !== 'smtp') {
            Log::info("Posta regionale {$regione->nome}: in produzione l'invio userebbe {$regione->mail_host}:{$regione->mail_port}, utente {$regione->mail_username}.");
            $mailer = Mail::build(config("mail.mailers.{$generale}"));
        } else {
            $mailer = Mail::build([
                'transport'    => 'smtp',
                'scheme'       => $regione->mail_scheme,
                'host'         => $regione->mail_host,
                'port'         => $regione->mail_port,
                'username'     => $regione->mail_username,
                'password'     => $password,
                'timeout'      => null,
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ]);
        }

        // Mail::build() non imposta il mittente globale: va dato a mano. Un mittente nel template email prevale.
        $mailer->alwaysFrom($regione->mail_from_address, $regione->mail_from_name ?: config('mail.from.name'));

        return $mailer;
    }
}
