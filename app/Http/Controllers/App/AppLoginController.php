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

        // Messaggio generico per non rivelare se l'email è nel sistema
        if (! $socio) {
            return back()->withErrors([
                'email' => 'Indirizzo email non trovato nel sistema.',
            ])->withInput(['email' => $request->email]);
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

        Mail::to($socio->email)->send(new OtpLoginCode($codice, $socio));

        $request->session()->put('otp_pending_socio_id', $socio->id);
        $request->session()->put('otp_pending_email', $socio->email);

        return redirect()->route('app.verifica');
    }

    public function showVerifica(Request $request)
    {
        if (! $request->session()->has('otp_pending_socio_id')) {
            return redirect()->route('app.login');
        }

        $socioId = $request->session()->get('otp_pending_socio_id');
        $email   = $request->session()->get('otp_pending_email', '');

        $otp = OtpCode::where('partecipante_id', $socioId)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (! $otp || $otp->isScaduto()) {
            $request->session()->forget(['otp_pending_socio_id', 'otp_pending_email']);
            return redirect()->route('app.login')->withErrors([
                'email' => 'Il codice è scaduto. Inserisci di nuovo la tua email per richiederne uno nuovo.',
            ]);
        }

        $secondiRimasti = max(0, $otp->expires_at->timestamp - now()->timestamp);

        return view('app.verifica', compact('email', 'secondiRimasti'));
    }

    public function verifica(Request $request)
    {
        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ]);

        $socioId = $request->session()->get('otp_pending_socio_id');

        if (! $socioId) {
            return redirect()->route('app.login');
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

        $request->session()->forget(['otp_pending_socio_id', 'otp_pending_email']);
        $request->session()->put('app_socio_id', $socioId);

        // Cookie remember: 90 giorni, cifrato da Laravel (APP_KEY)
        return redirect()->route('app.home')
            ->withCookie(cookie('app_remember', (string) $socioId, 60 * 24 * 90));
    }

    public function logout(Request $request)
    {
        $request->session()->forget('app_socio_id');
        return redirect()->route('app.login')
            ->withCookie(cookie()->forget('app_remember'));
    }
}
