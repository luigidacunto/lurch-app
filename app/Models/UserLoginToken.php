<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserLoginToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'codice',
        'token',
        'expires_at',
        'used_at',
        'tentativi_falliti',
        'bloccato_fino_a',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'        => 'datetime',
            'used_at'           => 'datetime',
            'bloccato_fino_a'   => 'datetime',
            'created_at'        => 'datetime',
            'tentativi_falliti' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isScaduto(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsato(): bool
    {
        return $this->used_at !== null;
    }

    public function isBloccato(): bool
    {
        return $this->bloccato_fino_a !== null && $this->bloccato_fino_a->isFuture();
    }

    /** Valida per il click sul link (non soggetta a tentativi/blocco: il token è già il segreto). */
    public function validaPerLink(): bool
    {
        return ! $this->isUsato() && ! $this->isScaduto();
    }

    /**
     * Genera codice+link e invia l'email. Cancella eventuali richieste precedenti non usate.
     * Se l'utente ha una password impostata, procedendo con questo login verrà disabilitata
     * (vedi User::completaLoginEmail()).
     */
    public static function invia(User $user): self
    {
        static::where('user_id', $user->id)->whereNull('used_at')->delete();

        $codice = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $token = static::create([
            'user_id'           => $user->id,
            'email'             => $user->email,
            'codice'            => Hash::make($codice),
            'token'             => Str::random(48),
            'expires_at'        => now()->addMinutes(15),
            'tentativi_falliti' => 0,
        ]);

        \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\MagicLinkLogin($codice, $token));

        return $token;
    }
}
