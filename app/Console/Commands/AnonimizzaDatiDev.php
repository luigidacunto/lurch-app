<?php

namespace App\Console\Commands;

use App\Models\CandidaturaStaff;
use App\Models\GraduatoriaCandidato;
use App\Models\OspiteEvento;
use App\Models\Socio;
use App\Models\User;
use App\Support\CodiceFiscaleFinto;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Anonimizza i dati personali (nome, cognome, CF, email, telefono, luogo di nascita, giorno e
 * mese di nascita) del DB su cui gira, mantenendo tutte le correlazioni: stesso CF vecchio →
 * stessa identità finta ovunque, FK e qualifiche intatte. Pensato per i DB di sviluppo
 * (PC, portatile, VM dev), che non devono conservare dati reali.
 *
 * Unico blocco: APP_ENV=production. Mostra sempre l'analisi e chiede sempre conferma
 * (nessun --force, voluto). L'utente superadmin non viene toccato (serve per il login).
 */
class AnonimizzaDatiDev extends Command
{
    protected $signature = 'dev:anonimizza-dati';

    protected $description = 'Sostituisce i dati personali con dati finti mantenendo le correlazioni (irreversibile). Rifiuta di girare in produzione.';

    /**
     * Pool di "cognomi" segnaposto: participi/aggettivi italiani che descrivono il fatto che
     * il dato è finto/oscurato — NESSUNO è un cognome reale (né in italiano né in altre lingue).
     * Dà varietà nelle liste ma garantisce che nessuna combinazione nome+cognome possa
     * coincidere con un volontario reale. Il nome di battesimo resta vario (Faker it_IT) —
     * da solo non è un dato identificativo.
     */
    private const COGNOMI_FINTI = ['Anonimizzato', 'Oscurato', 'Mascherato', 'Occultato', 'Segnaposto', 'Riservato', 'Nascosto', 'Fittizio'];

    /** old CF (uppercase) o chiave sintetica → ['nome'=>, 'cognome'=>, 'codice_fiscale'=>, 'data_nascita'=>Carbon] */
    private array $identita = [];

    /** CF finti già assegnati, per evitare collisioni sull'unique. */
    private array $cfUsati = [];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Ambiente di produzione: comando disabilitato.');

            return self::FAILURE;
        }

        $this->warn('Database: ' . DB::connection()->getDatabaseName() . ' (ambiente: ' . app()->environment() . ')');
        $this->mostraAnalisi();

        if (! $this->confirm('Anonimizzare questi dati? Operazione NON reversibile.', false)) {
            $this->line('Annullato, nessuna modifica.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            $this->anonimizza(Socio::withTrashed(), 'codice_fiscale', 'SOCIO',
                withBirthdate: true,
                colonneContatto: ['email', 'email_in_verifica', 'telefono', 'prefisso_telefono', 'telefoni_secondari', 'luogo_nascita']);
            $this->anonimizza(GraduatoriaCandidato::query(), 'codice_fiscale', 'GRADCAND',
                colonneContatto: ['email', 'email_in_verifica', 'telefono']);
            $this->anonimizza(CandidaturaStaff::query(), 'codice_fiscale', 'CANDSTAFF',
                colonneContatto: ['email', 'email_in_verifica', 'telefono', 'prefisso_telefono']);
            $this->anonimizza(OspiteEvento::query(), 'cf', 'OSPITE');
            $this->anonimizzaUsers();
            $this->anonimizzaAltro();
        });

        $this->info('Fatto. Identità finte distinte generate: ' . count($this->identita));
        $this->newLine();
        $this->line('NON modificati: utenti superadmin, gaia_persona_id, email/indirizzi/rappresentanti dei comitati');
        $this->line('(dati istituzionali pubblici), allergie/diete, percorsi dei PDF caricati (i file su disco restano).');

        return $this->selfCheck();
    }

    /** Conteggi di ciò che verrà modificato, mostrati prima della conferma. */
    private function mostraAnalisi(): void
    {

        $this->table(['Tabella', 'Righe', 'Azione'], [
            ['soci', Socio::withTrashed()->whereNotIn('cognome', self::COGNOMI_FINTI)->count(), 'nome, cognome, CF, email, telefoni, luogo e giorno/mese di nascita (anno invariato)'],
            ['graduatoria_candidati', GraduatoriaCandidato::whereNotIn('cognome', self::COGNOMI_FINTI)->count(), 'nome, cognome, CF, email, telefono; note svuotate'],
            ['candidature_staff', CandidaturaStaff::whereNotIn('cognome', self::COGNOMI_FINTI)->count(), 'nome, cognome, CF, email, telefono'],
            ['ospiti_evento', OspiteEvento::whereNotIn('cognome', self::COGNOMI_FINTI)->count(), 'nome, cognome, CF'],
            ['users (non superadmin)', $this->utentiDaAnonimizzare()->count(), 'nome, email (solo quelli non già finti)'],
            ['users (superadmin)', User::where('ruolo', 'superadmin')->count(), 'INTATTI'],
            ['corsi (direttore_corso)', $this->contaCorsiDaRiallineare(), 'testo riallineato al nome finto del socio collegato'],
            ['risposte_preregistrazione (JSON)', $this->contaRisposteDaPulire('risposte_preregistrazione'), 'email/telefono finti, note_extra sostituite'],
            ['risposte_checkin (JSON)', $this->contaRisposteDaPulire('risposte_checkin'), 'email/telefono finti, note_extra sostituite'],
            ['notifications', DB::table('notifications')->count(), 'ELIMINATE (testo con nomi/CF degli import)'],
            ['sessions (non superadmin)', $this->querySessioniDaEliminare()->count(), 'ELIMINATE'],
            ['log_modifiche_email', DB::table('log_modifiche_email')->where(fn ($q) => $q->whereNotLike('email_vecchia', '%@example.invalid')->orWhereNotLike('email_nuova', '%@example.invalid')->orWhereNotNull('note'))->count(), 'email finte, note svuotate'],
            ['otp_codes', DB::table('otp_codes')->count(), 'ELIMINATE (temporanee)'],
            ['email_verifiche', DB::table('email_verifiche')->count(), 'ELIMINATE (temporanee)'],
            ['user_login_tokens (non superadmin)', DB::table('user_login_tokens')->whereNotIn('user_id', User::where('ruolo', 'superadmin')->pluck('id'))->count(), 'ELIMINATI (temporanei)'],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  list<string>  $colonneContatto
     */
    private function anonimizza($query, string $colCf, string $prefissoChiave, bool $withBirthdate = false, array $colonneContatto = []): void
    {
        $query->orderBy('id')->chunkById(500, function ($righe) use ($colCf, $prefissoChiave, $withBirthdate, $colonneContatto) {
            foreach ($righe as $r) {
                // Idempotente: una riga già anonimizzata (cognome del pool) non si rigenera,
                // altrimenti il CF finto farebbe da seme per una NUOVA identità.
                if ($this->giaAnonimizzata($r)) {
                    continue;
                }

                $cfVecchio = filled($r->{$colCf}) ? strtoupper(trim($r->{$colCf})) : "{$prefissoChiave}{$r->id}";
                $nascita = $withBirthdate && $r->data_nascita ? Carbon::parse($r->data_nascita) : null;

                $id = $this->identitaPer($cfVecchio, $nascita);

                $nuovi = [
                    'nome' => $id['nome'],
                    'cognome' => $id['cognome'],
                    $colCf => $id['codice_fiscale'],
                ] + $this->datiContatto($id, $r, $colonneContatto);

                if ($nascita) {
                    $nuovi['data_nascita'] = $id['data_nascita']->toDateString();
                }

                $r->forceFill($nuovi)->saveQuietly();
            }
        });
    }

    private function giaAnonimizzata($r): bool
    {
        return in_array($r->cognome, self::COGNOMI_FINTI, true);
    }

    /**
     * Valori di contatto finti, deterministici per riga. Preserva l'assenza del dato
     * (se il campo era vuoto resta vuoto — non inventa contatti a chi non ne aveva).
     *
     * @param  list<string>  $colonne
     * @return array<string, mixed>
     */
    private function datiContatto(array $id, $r, array $colonne): array
    {
        $out = [];

        foreach ($colonne as $c) {
            $val = $r->{$c} ?? null;

            if ($c === 'telefoni_secondari') {
                $out[$c] = filled($val) ? [$this->telefonoFinto($r->id)] : $val;

                continue;
            }

            if (blank($val)) {
                continue;
            }

            $out[$c] = match ($c) {
                'email', 'email_in_verifica' => $this->emailFinta($id, $r->id),
                'telefono'                   => $this->telefonoFinto($r->id),
                'prefisso_telefono'          => '+39',
                'luogo_nascita'              => 'Comune Fittizio',
                default                      => $val,
            };
        }

        return $out;
    }

    /** Dominio `.invalid` (RFC 2606): non risolve mai, impossibile che sia un indirizzo reale. */
    private function emailFinta(array $id, int $rowId): string
    {
        return Str::slug($id['nome']) . '.' . Str::slug($id['cognome']) . '.' . $rowId . '@example.invalid';
    }

    /** Nessun numero italiano inizia con "+39 000" → impossibile che sia reale (stesso principio del Belfiore Z000). */
    private function telefonoFinto(int $rowId): string
    {
        return '+39 000 ' . str_pad((string) $rowId, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Utenti non superadmin con email ancora vera. Quelli già finti (`@example.invalid` di un giro precedente o
     * `operatore-N@lurch.test`) non si toccano: sono i login usati per le prove (idempotenza).
     */
    private function utentiDaAnonimizzare()
    {
        return User::query()->where('ruolo', '!=', 'superadmin')
            ->whereNotLike('email', '%@example.invalid')
            ->whereNotLike('email', 'operatore-%@lurch.test');
    }

    /** Tutti gli utenti tranne i superadmin (il loro login è l'unico accesso al portale di dev). */
    private function anonimizzaUsers(): void
    {
        $this->utentiDaAnonimizzare()->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $u) {
                $faker = FakerFactory::create('it_IT');
                $faker->seed(crc32("USER{$u->id}"));

                $u->forceFill([
                    'name' => $faker->firstName() . ' ' . self::COGNOMI_FINTI[$faker->numberBetween(0, count(self::COGNOMI_FINTI) - 1)],
                    'email' => "operatore-{$u->id}@lurch.test",
                ])->saveQuietly();
            }
        });
    }

    /** Tabelle satellite con email/nomi in chiaro: righe temporanee eliminate, log riscritti. */
    private function anonimizzaAltro(): void
    {
        // Dopo i soci: il testo del direttore deve riflettere il nome finto del socio collegato.
        DB::statement("UPDATE corsi SET direttore_corso = s.nome || ' ' || s.cognome FROM soci s WHERE corsi.direttore_socio_id = s.id");
        DB::table('corsi')->whereNull('direttore_socio_id')->whereNotNull('direttore_corso')->where('direttore_corso', '!=', '')
            ->update(['direttore_corso' => 'Direttore Anonimizzato']);

        DB::table('graduatoria_candidati')->update(['note' => null]);
        DB::table('log_modifiche_email')->update([
            'email_vecchia' => DB::raw("'vecchia-' || id || '@example.invalid'"),
            'email_nuova' => DB::raw("'nuova-' || id || '@example.invalid'"),
            'note' => null,
        ]);

        $this->anonimizzaRisposteJson('risposte_preregistrazione');
        $this->anonimizzaRisposteJson('risposte_checkin');
        DB::table('notifications')->delete();
        $this->querySessioniDaEliminare()->delete();

        DB::table('otp_codes')->delete();
        DB::table('email_verifiche')->delete();
        DB::table('user_login_tokens')->whereNotIn('user_id', User::where('ruolo', 'superadmin')->pluck('id'))->delete();
    }

    /** Corsi il cui testo direttore differisce da quello che l'anonimizzazione scriverebbe (le già allineate non contano: idempotenza). */
    private function contaCorsiDaRiallineare(): int
    {
        return (int) DB::selectOne(
            "SELECT count(*) AS n FROM corsi c LEFT JOIN soci s ON s.id = c.direttore_socio_id
             WHERE (s.id IS NOT NULL AND c.direttore_corso IS DISTINCT FROM s.nome || ' ' || s.cognome)
                OR (s.id IS NULL AND c.direttore_corso IS NOT NULL AND c.direttore_corso != '' AND c.direttore_corso != 'Direttore Anonimizzato')"
        )->n;
    }

    /** Righe di `risposte` con email/telefono/nota non ancora finti (le già pulite non contano: idempotenza). */
    private function contaRisposteDaPulire(string $tabella): int
    {
        return DB::table($tabella)->whereRaw(self::SQL_RISPOSTE_DA_PULIRE)->count();
    }

    /** Sessioni da eliminare: tutte tranne quelle dei superadmin (il superadmin resta collegato). */
    private function querySessioniDaEliminare()
    {
        return DB::table('sessions')->where(fn ($q) => $q->whereNull('user_id')
            ->orWhereNotIn('user_id', User::where('ruolo', 'superadmin')->pluck('id')));
    }

    private const SQL_RISPOSTE_DA_PULIRE = "(risposte->>'email' NOT LIKE '%@example.invalid' AND coalesce(risposte->>'email','') <> '')
        OR (risposte->>'telefono' NOT LIKE '+39 000 %' AND coalesce(risposte->>'telefono','') <> '')
        OR (coalesce(risposte->>'note_extra','') NOT IN ('', 'Nota anonimizzata'))";

    /**
     * `risposte` (jsonb) delle risposte ai form: sostituisce solo email, telefono e il testo libero
     * `note_extra`; pasti, diete, allergeni, disponibilità restano (servono ai test). Idempotente:
     * i valori già finti non vengono riscritti.
     */
    private function anonimizzaRisposteJson(string $tabella): void
    {
        DB::statement("UPDATE {$tabella} SET risposte = jsonb_set(risposte, '{email}', to_jsonb('risposta-' || id || '@example.invalid'::text))
            WHERE coalesce(risposte->>'email','') <> '' AND risposte->>'email' NOT LIKE '%@example.invalid'");
        DB::statement("UPDATE {$tabella} SET risposte = jsonb_set(risposte, '{telefono}', to_jsonb('+39 000 ' || lpad(id::text, 7, '0')))
            WHERE coalesce(risposte->>'telefono','') <> '' AND risposte->>'telefono' NOT LIKE '+39 000 %'");
        DB::statement("UPDATE {$tabella} SET risposte = jsonb_set(risposte, '{note_extra}', to_jsonb('Nota anonimizzata'::text))
            WHERE coalesce(risposte->>'note_extra','') NOT IN ('', 'Nota anonimizzata')");
    }

    /** @return array{nome:string, cognome:string, codice_fiscale:string, data_nascita:Carbon} */
    private function identitaPer(string $cfVecchio, ?Carbon $nascita): array
    {
        if (isset($this->identita[$cfVecchio])) {
            return $this->identita[$cfVecchio];
        }

        $faker = FakerFactory::create('it_IT');

        for ($tentativo = 0; $tentativo < 50; $tentativo++) {
            $faker->seed(crc32($cfVecchio) + $tentativo);

            $nome = $faker->firstName();
            $cognome = self::COGNOMI_FINTI[$faker->numberBetween(0, count(self::COGNOMI_FINTI) - 1)];
            // Anno reale invariato, giorno/mese inventati (1–28: valido in ogni mese); senza data nota, anno inventato.
            $data = Carbon::create(
                $nascita?->year ?? $faker->numberBetween(1955, 2005),
                $faker->numberBetween(1, 12),
                $faker->numberBetween(1, 28),
            );

            $cf = CodiceFiscaleFinto::genera($cognome, $nome, $data);

            if (! in_array($cf, $this->cfUsati, true)) {
                $this->cfUsati[] = $cf;

                return $this->identita[$cfVecchio] = compact('nome', 'cognome') + ['codice_fiscale' => $cf, 'data_nascita' => $data];
            }
        }

        // Praticamente irraggiungibile: 50 seed diversi tutti in collisione.
        throw new \RuntimeException("Impossibile generare un CF finto univoco per {$cfVecchio}");
    }

    private function selfCheck(): int
    {
        $regex = '/^[A-Z]{6}\d{2}[A-Z]\d{2}' . CodiceFiscaleFinto::BELFIORE . '[A-Z]$/';
        $errori = 0;

        foreach ($this->identita as $chiave => $id) {
            $cf = $id['codice_fiscale'];
            if (! preg_match($regex, $cf) || ! CodiceFiscaleFinto::checksumValido($cf)) {
                $this->error("CF finto non valido per {$chiave}: {$cf}");
                $errori++;
            }
        }

        if (count($this->cfUsati) !== count(array_unique($this->cfUsati))) {
            $this->error('Collisione tra CF finti generati.');
            $errori++;
        }

        if ($errori > 0) {
            return self::FAILURE;
        }

        $this->info('Self-check OK: ' . count($this->identita) . ' CF finti, formato + checksum + univocità verificati.');

        return self::SUCCESS;
    }
}
