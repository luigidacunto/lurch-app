<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLoginToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminMagicLoginController extends Controller
{
    public function showForm()
    {
        return view('admin-login.form');
    }

    public function invia(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $ip  = $request->ip();
        $key = 'admin-login-email:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $secondi = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Troppi tentativi. Riprova tra {$secondi} secondi."])->withInput();
        }

        RateLimiter::hit($key, 600);

        $email = strtolower(trim($request->email));
        $user  = User::where('email', $email)->where('attivo', true)->first();

        // Messaggio generico per non rivelare se l'account esiste
        if (! $user) {
            return back()->withErrors(['email' => 'Nessun account attivo trovato con questo indirizzo.'])->withInput();
        }

        $token = UserLoginToken::invia($user);

        $request->session()->put('admin_login_pending_user_id', $user->id);
        $request->session()->put('admin_login_pending_email', $user->email);

        return redirect()->route('admin-login.verifica');
    }

    public function showVerifica(Request $request)
    {
        if (! $request->session()->has('admin_login_pending_user_id')) {
            return redirect()->route('admin-login.form');
        }

        $userId = $request->session()->get('admin_login_pending_user_id');
        $email  = $request->session()->get('admin_login_pending_email', '');

        $token = UserLoginToken::where('user_id', $userId)->whereNull('used_at')->latest('created_at')->first();

        if (! $token || $token->isScaduto()) {
            $request->session()->forget(['admin_login_pending_user_id', 'admin_login_pending_email']);
            return redirect()->route('admin-login.form')->withErrors([
                'email' => 'Il codice è scaduto. Richiedine uno nuovo.',
            ]);
        }

        $secondiRimasti = max(0, $token->expires_at->timestamp - now()->timestamp);

        return view('admin-login.verifica', compact('email', 'secondiRimasti'));
    }

    public function verifica(Request $request)
    {
        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('admin_login_pending_user_id');

        if (! $userId) {
            return redirect()->route('admin-login.form');
        }

        $token = UserLoginToken::where('user_id', $userId)->whereNull('used_at')->latest('created_at')->first();

        if (! $token) {
            return back()->withErrors(['codice' => 'Codice non trovato. Richiedi un nuovo codice.']);
        }

        if ($token->isBloccato()) {
            $minuti = (int) ceil(now()->diffInSeconds($token->bloccato_fino_a) / 60);
            return back()->withErrors(['codice' => "Troppi tentativi errati. Riprova tra {$minuti} minuti."]);
        }

        if ($token->isScaduto()) {
            return back()->withErrors(['codice' => 'Il codice è scaduto. Torna al login per richiederne uno nuovo.']);
        }

        if (! Hash::check($request->codice, $token->codice)) {
            $tentativiAggiornati = $token->tentativi_falliti + 1;
            $aggiornamenti = ['tentativi_falliti' => $tentativiAggiornati];

            if ($tentativiAggiornati >= 5) {
                $aggiornamenti['bloccato_fino_a'] = now()->addMinutes(30);
                $token->update($aggiornamenti);
                return back()->withErrors(['codice' => 'Troppi tentativi errati. Attendi 30 minuti.']);
            }

            $token->update($aggiornamenti);
            $rimasti = 5 - $tentativiAggiornati;
            return back()->withErrors(['codice' => "Codice errato. Ti rimangono {$rimasti} tentativi."]);
        }

        return $this->completaLogin($request, $token);
    }

    public function magicLink(Request $request, string $token)
    {
        $loginToken = UserLoginToken::where('token', $token)->first();

        if (! $loginToken || ! $loginToken->validaPerLink()) {
            return view('admin-login.link-non-valido');
        }

        return $this->completaLogin($request, $loginToken);
    }

    private function completaLogin(Request $request, UserLoginToken $token)
    {
        $user = $token->user;

        if (! $user->attivo) {
            abort(403, 'Account disattivato.');
        }

        $token->update(['used_at' => now()]);
        $user->completaLoginEmail();

        $request->session()->forget(['admin_login_pending_user_id', 'admin_login_pending_email']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }
}
