<?php

namespace App\Models;

use App\Mail\VerificaEmailSocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Verifica indirizzo email per uno di tre possibili target, esattamente uno
 * valorizzato tra socio_id/candidatura_staff_id/graduatoria_candidato_id (CHECK
 * a DB) — una candidatura senza socio agganciato (CF non trovato, o docente
 * esterno all'associazione) verifica comunque la propria email, riportata poi
 * sul Socio se/quando viene agganciato (vedi CandidaturaSocioSync::sincronizza()).
 */
class EmailVerifica extends Model
{
    public $timestamps = false;

    protected $table = 'email_verifiche';

    protected $fillable = [
        'socio_id',
        'candidatura_staff_id',
        'graduatoria_candidato_id',
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

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function candidaturaStaff(): BelongsTo
    {
        return $this->belongsTo(CandidaturaStaff::class);
    }

    public function graduatoriaCandidato(): BelongsTo
    {
        return $this->belongsTo(GraduatoriaCandidato::class);
    }

    /** Il modello effettivamente verificato da questa richiesta (uno solo dei tre è valorizzato). */
    public function target(): Model
    {
        return $this->socio ?? $this->candidaturaStaff ?? $this->graduatoriaCandidato;
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
     * Verifica riuscita: promuove l'email verificata sul target (socio, o la
     * candidatura stessa se non ancora agganciata a un socio).
     * Se la verifica era per un cambio email (email_in_verifica), la promuove a email
     * definitiva e svuota il campo pending. Se coincideva già con l'email attuale,
     * si limita a marcarla verificata.
     */
    public function segnaVerificata(): void
    {
        $this->update(['used_at' => now()]);
        $this->target()->update([
            'email'               => $this->email,
            'email_in_verifica'   => null,
            'email_verificata_at' => now(),
        ]);
    }

    /**
     * Invia un codice+link di verifica per un indirizzo email specifico del target
     * (Socio, GraduatoriaCandidato o CandidaturaStaff — tutti e tre hanno gli stessi
     * campi email/email_in_verifica/email_verificata_at). Va chiamato dopo ogni punto
     * in cui un'email di contatto si "consolida" (iscrizione confermata, sincronizzazione
     * da candidatura, ecc.) o quando un cambio email è in attesa di conferma.
     *
     * $emailDaVerificare: se omessa, usa email_in_verifica se presente, altrimenti email.
     */
    public static function inviaSeNecessario(Model $target, ?string $emailDaVerificare = null): void
    {
        $target->refresh();

        $email = $emailDaVerificare ?? $target->email_in_verifica ?? $target->email;

        if (blank($email)) {
            return;
        }

        // Già verificata e nessun cambio in attesa: niente da fare.
        if ($target->email_verificata_at !== null && blank($target->email_in_verifica) && $target->email === $email) {
            return;
        }

        $colonna = static::colonnaTarget($target);

        // Cancella eventuali richieste precedenti non ancora usate.
        static::where($colonna, $target->id)->whereNull('used_at')->delete();

        $codice = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verifica = static::create([
            $colonna            => $target->id,
            'email'             => $email,
            'codice'            => Hash::make($codice),
            'token'             => Str::random(48),
            'expires_at'        => now()->addMinutes(30),
            'tentativi_falliti' => 0,
        ]);

        Mail::to($email)->send(new VerificaEmailSocio($codice, $verifica));
    }

    /**
     * Corregge l'email di una candidatura (GraduatoriaCandidato o CandidaturaStaff) da
     * pannello — invalida la verifica corrente (l'hook del modello azzera
     * email_verificata_at) e innesca una nuova verifica: sul Socio agganciato se
     * presente (stesso staging via email_in_verifica del form pubblico), altrimenti
     * direttamente sulla candidatura. Punto unico riusato da tutte le action Filament
     * che permettono di correggere l'email da "Dettagli candidatura"/"Modifica".
     */
    public static function aggiornaIndirizzo(Model $candidatura, string $nuovaEmail): void
    {
        if ($candidatura->email === $nuovaEmail) {
            return;
        }

        $candidatura->update(['email' => $nuovaEmail]);

        if (! $candidatura->socio_id) {
            static::inviaSeNecessario($candidatura, $nuovaEmail);
            return;
        }

        $socio = $candidatura->socio;
        $socio->update(['email_in_verifica' => $nuovaEmail]);
        static::inviaSeNecessario($socio, $nuovaEmail);
    }

    /**
     * True se l'email ATTUALE della candidatura (record->email) è verificata sul target
     * giusto (socio agganciato, o la candidatura stessa se non agganciata) — non basta
     * che il target abbia MAI verificato un'email: dev'essere verificata la stessa email
     * di oggi, senza un cambio in sospeso (email_in_verifica). Altrimenti un socio già
     * verificato in passato con un indirizzo diverso (es. da un'altra candidatura)
     * risulterebbe "verificato" anche per un'email nuova mai confermata — ingannevole,
     * fa sparire il pulsante "Invia verifica" quando servirebbe ancora.
     */
    public static function isVerificata(Model $candidatura): bool
    {
        $target = $candidatura->socio_id ? $candidatura->socio : $candidatura;

        return $target
            && $target->email_verificata_at !== null
            && blank($target->email_in_verifica)
            && $target->email === $candidatura->email;
    }

    /**
     * Rinvia la verifica per l'email attuale di una candidatura (nessun cambio di
     * indirizzo — solo un nuovo invio), sul socio agganciato se presente altrimenti
     * sulla candidatura stessa. Riapplica lo staging su email_in_verifica se non era
     * già stato fatto (es. verifica mai innescata al submit pubblico).
     */
    public static function reinvia(Model $candidatura): void
    {
        if (! $candidatura->socio_id) {
            static::inviaSeNecessario($candidatura, $candidatura->email);
            return;
        }

        $socio = $candidatura->socio;
        if ($candidatura->email !== $socio->email) {
            $socio->update(['email_in_verifica' => $candidatura->email]);
        }
        static::inviaSeNecessario($socio, $candidatura->email);
    }

    /** Data dell'ultima richiesta di verifica per una candidatura (socio agganciato o candidatura stessa), o null se mai inviata. */
    public static function ultimaRichiesta(Model $candidatura): ?\Carbon\CarbonInterface
    {
        $target = $candidatura->socio_id ? $candidatura->socio : $candidatura;

        return static::where(static::colonnaTarget($target), $target->id)->latest('created_at')->value('created_at');
    }

    /**
     * Riga HTML colorata per il badge "stato invio" nelle modali "Dettagli candidatura"
     * (arancio se non ancora inviata nessuna verifica, verde se già inviata — testo
     * "mai inviata" scartato apposta: a colpo d'occhio si legge come "mail inviata").
     */
    public static function descrizioneUltimaRichiesta(?\Carbon\CarbonInterface $ultimaVerifica): string
    {
        $colore = $ultimaVerifica ? '#16a34a' : '#d97706';
        $testo  = $ultimaVerifica ? 'ultimo invio: ' . $ultimaVerifica->format('d/m/Y H:i') : 'non ancora inviata';

        return '<span style="color:' . $colore . ';">' . $testo . '</span>';
    }

    private static function colonnaTarget(Model $target): string
    {
        return match (true) {
            $target instanceof Socio => 'socio_id',
            $target instanceof CandidaturaStaff => 'candidatura_staff_id',
            $target instanceof GraduatoriaCandidato => 'graduatoria_candidato_id',
            default => throw new \InvalidArgumentException('Target di verifica email non supportato: ' . get_class($target)),
        };
    }
}
