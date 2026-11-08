<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'ruolo',
        'comitato_regionale_id',
        'comitato_locale_id',
        'attivo',
        'socio_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'attivo'   => 'boolean',
        ];
    }

    // --- Accesso pannello Filament ---

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->attivo;
    }

    // --- Helper ruoli ---

    public function isSuperadmin(): bool
    {
        return $this->ruolo === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->ruolo === 'admin';
    }

    public function isAdminLocale(): bool
    {
        return $this->ruolo === 'admin_locale';
    }

    public function isOperatore(): bool
    {
        return $this->ruolo === 'operatore';
    }

    /**
     * Soglia unica per il bridge GAIA (userscript Tampermonkey, tutti gli strumenti: ricerca
     * ID, import CV singolo-socio, import batch per comitato/graduatoria).
     * Aperto anche ad admin_locale dal 2026-08-13: lo scoping al
     * proprio comitato è già garantito a valle da ogni singolo entry point (filtroLocale
     * bloccato in ListQualificheSoci, SocioResource::canEdit(), Graduatoria::puoGestire(),
     * GaiaBatchImportController::autorizzaComitato()) — questa soglia resta solo il gate di
     * ruolo (operatore sempre escluso).
     */
    public function puoUsareGaiaBridge(): bool
    {
        return $this->isSuperadmin() || $this->isAdmin() || $this->isAdminLocale();
    }

    // --- Relazioni ---

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function comitatoLocale(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_id');
    }

    /** Socio collegato (opzionale, solo per facilitare l'operatività) */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /** Eventi a cui l'operatore è assegnato */
    public function eventi(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'evento_operatore', 'user_id', 'evento_id')
                    ->withPivot('created_at');
    }

    /** Graduatorie a cui l'operatore è assegnato */
    public function graduatorie(): BelongsToMany
    {
        return $this->belongsToMany(Graduatoria::class, 'graduatoria_operatore', 'user_id', 'graduatoria_id')
                    ->withPivot('created_at');
    }

    /** Checkin eseguiti da questo operatore */
    public function risposteCheckin(): HasMany
    {
        return $this->hasMany(RispostaCheckin::class, 'operatore_id');
    }

    /**
     * Completa il login via email: se l'utente aveva una password impostata,
     * la disabilita (login solo via email da questo momento in poi) e forza
     * il logout da eventuali altre sessioni aperte con la vecchia password.
     */
    public function completaLoginEmail(): void
    {
        if ($this->password !== null) {
            $this->update(['password' => null]);
            $this->invalidaSessioni();
        }
    }

    /**
     * Elimina le sessioni attive dell'utente (tabella `sessions`, driver database).
     * Usato quando cambia il metodo di login (password<->email) per forzare
     * un nuovo login ovunque. $eccettoSessionId permette di preservare la sessione corrente.
     */
    public function invalidaSessioni(?string $eccettoSessionId = null): void
    {
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $this->id)
            ->when($eccettoSessionId, fn ($q) => $q->where('id', '!=', $eccettoSessionId))
            ->delete();
    }
}
