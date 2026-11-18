<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Mail\OtpLoginCode;
use App\Models\OtpCode;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AppLoginController extends Controller
{
    /** Dati in sessione tra la richiesta del codice e l'accesso. */
    private const CHIAVI_IN_ATTESA = ['otp_pending_socio_id', 'otp_pending_email', 'otp_pending_scade', 'otp_pending_errori'];

    public function showLogin(Request $request)
    {
        if ($request->session()->has('app_socio_id')) {
            return redirect()->route('app.home');
        }

        return view('app.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $ip  = $request->ip();
        $key = 'otp:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $secondi = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Troppi tentativi. Riprova tra {$secondi} secondi."])->withInput();
        }

        RateLimiter::hit($key, 600);

        $email = strtolower(trim($request->email));

        $socio = Socio::where('email', $email)->first();

        // Email registrata o no, si passa alla stessa pagina del codice: la risposta non rivela chi è tra i soci.
        // ponytail: l'invio sincrono della mail allunga la risposta solo per le email registrate; il limite
        // di 3 richieste ogni 10 minuti per IP rende la differenza poco sfruttabile. Mail in coda se servisse di più.
        $request->session()->forget(['otp_pending_socio_id', 'otp_pending_errori']);
        $request->session()->put('otp_pending_email', $email);
        $request->session()->put('otp_pending_scade', now()->addMinutes(15)->getTimestamp());

        if (! $socio) {
            return redirect()->route('app.verifica');
        }

        // Cancella OTP precedenti non ancora usati per questo socio
        OtpCode::where('partecipante_id', $socio->id)
            ->whereNull('used_at')
            ->delete();

        // Fuori produzione si può fissare il codice (OTP_FISSO nel .env) per far provare l'app a tester esterni.
        $codice = (config('app.otp_fisso') && ! app()->environment('production')) ? config('app.otp_fisso') : str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'partecipante_id'  => $socio->id,
            'email'            => $socio->email,
            'codice'           => Hash::make($codice),
            'expires_at'       => now()->addMinutes(15),
            'tentativi_falliti' => 0,
            'ip_address'       => $ip,
        ]);

        \App\Services\PostaRegionale::perPersona($socio->comitato_regionale_id)->to($socio->email)->send(new OtpLoginCode($codice, $socio));

        $request->session()->put('otp_pending_socio_id', $socio->id);

        return redirect()->route('app.verifica');
    }

    public function showVerifica(Request $request)
    {
        if (! $request->session()->has('otp_pending_email')) {
            return redirect()->route('app.login');
        }

        $socioId = $request->session()->get('otp_pending_socio_id');
        $email   = $request->session()->get('otp_pending_email', '');
        $scade   = (int) $request->session()->get('otp_pending_scade', 0);

        $otp = $socioId ? OtpCode::where('partecipante_id', $socioId)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first() : null;

        if (($socioId && (! $otp || $otp->isScaduto())) || now()->getTimestamp() >= $scade) {
            $request->session()->forget(self::CHIAVI_IN_ATTESA);
            return redirect()->route('app.login')->withErrors([
                'email' => 'Il codice è scaduto. Inserisci di nuovo la tua email per richiederne uno nuovo.',
            ]);
        }

        $secondiRimasti = max(0, $scade - now()->getTimestamp());

        return view('app.verifica', compact('email', 'secondiRimasti'));
    }

    public function verifica(Request $request)
    {
        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ]);

        if (! $request->session()->has('otp_pending_email')) {
            return redirect()->route('app.login');
        }

        $socioId = $request->session()->get('otp_pending_socio_id');

        // Email non registrata: stesse risposte di un codice sbagliato, contate in sessione.
        if (! $socioId) {
            $errori = $request->session()->increment('otp_pending_errori');
            return back()->withErrors(['codice' => $errori >= 5
                ? 'Troppi tentativi errati. Attendi 30 minuti.'
                : 'Codice errato. Ti rimangono ' . (5 - $errori) . ' tentativi.']);
        }

        $otp = OtpCode::where('partecipante_id', $socioId)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (! $otp) {
            return back()->withErrors(['codice' => 'Codice non trovato. Richiedi un nuovo codice.']);
        }

        if ($otp->isBloccato()) {
            $minuti = (int) ceil(now()->diffInSeconds($otp->bloccato_fino_a) / 60);
            return back()->withErrors(['codice' => "Troppi tentativi errati. Riprova tra {$minuti} minuti."]);
        }

        if ($otp->isScaduto()) {
            return back()->withErrors(['codice' => 'Il codice è scaduto. Torna al login per richiederne uno nuovo.']);
        }

        if (! Hash::check($request->codice, $otp->codice)) {
            $tentativiAggiornati = $otp->tentativi_falliti + 1;
            $aggiornamenti = ['tentativi_falliti' => $tentativiAggiornati];

            if ($tentativiAggiornati >= 5) {
                $aggiornamenti['bloccato_fino_a'] = now()->addMinutes(30);
                $otp->update($aggiornamenti);
                return back()->withErrors(['codice' => 'Troppi tentativi errati. Attendi 30 minuti.']);
            }

            $otp->update($aggiornamenti);
            $rimasti = 5 - $tentativiAggiornati;
            return back()->withErrors(['codice' => "Codice errato. Ti rimangono {$rimasti} tentativi."]);
        }

        $otp->update(['used_at' => now()]);

        $request->session()->forget(self::CHIAVI_IN_ATTESA);
        // Istante di emissione su sessione e cookie: un reset sessioni dal pannello
        // invalida tutto ciò che è stato emesso prima (vedi Socio::sessioneValida()).
        $emessaIl = now()->getTimestamp();
        $request->session()->put('app_socio_id', $socioId);
        $request->session()->put('app_login_at', $emessaIl);

        // Cookie remember "id.timestamp": 90 giorni, cifrato da Laravel (APP_KEY)
        return redirect()->route('app.home')
            ->withCookie(cookie('app_remember', $socioId . '.' . $emessaIl, 60 * 24 * 90));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['app_socio_id', 'app_login_at']);
        return redirect()->route('app.login')
            ->withCookie(cookie()->forget('app_remember'));
    }
}
