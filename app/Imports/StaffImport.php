<?php

namespace App\Imports;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Evento;
use App\Models\IscrizioneStaff;
use App\Models\LogModificaEmail;
use App\Models\Socio;
use App\Models\SocioEvento;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa lo staff di un evento dal formato GAIA (CRI).
 *
 * Colonne lette (per intestazione):
 *   cognome · nome · codice_fiscale · data_di_nascita
 *   email · numeri_di_telefono · comitato · sede_regionale
 *
 * Logica upsert (chiave: Codice Fiscale):
 *   - Se il socio non esiste → creato
 *   - Se esiste → aggiornato: email (con log), telefono, data nascita, comitato se cambiato
 *
 * Dopo l'upsert socio:
 *   - Crea IscrizioneStaff all'evento (firstOrCreate)
 *   - Crea SocioEvento come staff (firstOrCreate)
 */
class StaffImport implements ToCollection, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    public int   $creati     = 0;
    public int   $aggiornati = 0;
    public int   $iscritti   = 0;
    /** @var string[] */
    public array $errori     = [];

    private array $regionaliCache    = [];
    private array $localiCache       = [];
    private array $usedLocaliCodici  = [];

    public function __construct(private readonly int $eventoId) {}

    public function collection(Collection $rows): void
    {
        $evento = Evento::findOrFail($this->eventoId);

        foreach ($rows as $indice => $row) {
            try {
                \DB::transaction(function () use ($row, $evento, $indice) {
                    $this->processaRiga($row, $evento, $indice + 2);
                });
            } catch (\Throwable $e) {
                $this->errori[] = "Riga " . ($indice + 2) . ": " . $e->getMessage();
            }
        }
    }

    private function processaRiga(Collection $row, Evento $evento, int $numRiga): void
    {
        $cf      = strtoupper(trim((string) ($row['codice_fiscale'] ?? '')));
        $nome    = $this->normalizzaNomePersona((string) ($row['nome']    ?? ''));
        $cognome = $this->normalizzaNomePersona((string) ($row['cognome'] ?? ''));
        $email   = mb_strtolower(trim((string) ($row['email'] ?? '')));

        $nomeComitato = trim((string) ($row['comitato']       ?? ''));
        $nomeSede     = trim((string) ($row['sede_regionale'] ?? ''));

        if ($cf === '' || $nome === '' || $cognome === '') {
            throw new \RuntimeException("Campi obbligatori mancanti (cognome, nome, codice fiscale).");
        }
        if ($nomeComitato === '' || $nomeSede === '') {
            throw new \RuntimeException("Comitato o sede regionale mancanti — riga non importabile.");
        }

        $dataNascita = $this->parseData($row['data_di_nascita'] ?? null);
        $cellulari   = $this->parseCellulari($row['numeri_di_telefono'] ?? null);

        $comitato = $this->resolviComitato($nomeComitato, $nomeSede);

        // --- Upsert socio ---
        $partecipante = Socio::where('codice_fiscale', $cf)->first();

        if (! $partecipante) {
            $partecipante = new Socio();
            $partecipante->codice_fiscale = $cf;
            $partecipante->nome           = $nome;
            $partecipante->cognome        = $cognome;

            if ($email !== '')     $partecipante->email        = $email;
            if ($dataNascita)      $partecipante->data_nascita = $dataNascita;
            if (count($cellulari)) $this->applicaTelefoni($partecipante, $cellulari);
            if ($comitato)         $this->applicaComitato($partecipante, $comitato);

            $partecipante->save();
            $this->creati++;
        } else {
            $aggiornato = false;

            if ($email !== '' && mb_strtolower((string) $partecipante->email) !== $email) {
                $vecchiaEmail = $partecipante->email;
                Socio::withoutEvents(function () use ($partecipante, $email) {
                    $partecipante->email = $email;
                    $partecipante->save();
                });
                if ($vecchiaEmail !== null) {
                    LogModificaEmail::create([
                        'partecipante_id' => $partecipante->id,
                        'email_vecchia'   => $vecchiaEmail,
                        'email_nuova'     => $email,
                        'fonte'           => 'import',
                        'note'            => "Import staff evento #{$this->eventoId}",
                    ]);
                }
                $aggiornato = true;
            }

            if (count($cellulari) && $cellulari[0]['numero'] !== $partecipante->telefono) {
                $this->applicaTelefoni($partecipante, $cellulari);
                $aggiornato = true;
            }

            if ($dataNascita && (! $partecipante->data_nascita || ! $partecipante->data_nascita->eq($dataNascita))) {
                $partecipante->data_nascita = $dataNascita;
                $aggiornato = true;
            }

            if ($comitato && $comitato->id !== $partecipante->comitato_locale_id) {
                $this->applicaComitato($partecipante, $comitato);
                $aggiornato = true;
            }

            if ($aggiornato) {
                $partecipante->save();
                $this->aggiornati++;
            }
        }

        // Ruolo staff opzionale (es. "Logistica", "Docenza") — null se colonna assente o vuota
        $ruoloRaw  = isset($row['ruolo']) ? trim((string) $row['ruolo']) : '';
        $ruoloStaff = $ruoloRaw !== '' ? $this->normalizzaRuolo($ruoloRaw) : null;

        // --- Iscrizione staff all'evento ---
        $iscrizione = IscrizioneStaff::firstOrCreate(
            ['evento_id' => $this->eventoId, 'partecipante_id' => $partecipante->id],
            ['stato' => 'pending', 'ruolo' => $ruoloStaff]
        );
        if ($iscrizione->wasRecentlyCreated) {
            $this->iscritti++;
        } elseif ($ruoloStaff !== null && $iscrizione->ruolo !== $ruoloStaff) {
            // Aggiorna il ruolo se il file porta un valore e quello salvato è diverso
            $iscrizione->update(['ruolo' => $ruoloStaff]);
        }

        // --- Partecipazione evento come staff ---
        SocioEvento::firstOrCreate(
            ['evento_id' => $this->eventoId, 'partecipante_id' => $partecipante->id, 'ruolo' => 'staff'],
            ['abilitato_app' => true]
        );
    }

    // -------------------------------------------------------------------------

    private function resolviComitato(string $nomeComitato, string $nomeSede): ?ComitatoLocale
    {
        if ($nomeComitato === '') return null;

        $chiaveLocale = $this->normalizzaNome($nomeComitato);

        if (isset($this->localiCache[$chiaveLocale])) {
            return $this->localiCache[$chiaveLocale];
        }

        $regionale = $this->resolviComitatoRegionale($nomeSede);

        $locale = ComitatoLocale::whereRaw('LOWER(nome) = ?', [$chiaveLocale])
            ->where('comitato_regionale_id', $regionale->id)
            ->first();

        if (! $locale) {
            if (! isset($this->usedLocaliCodici[$regionale->id])) {
                $this->usedLocaliCodici[$regionale->id] = ComitatoLocale::where('comitato_regionale_id', $regionale->id)
                    ->pluck('codice')
                    ->filter()
                    ->toArray();
            }

            $codice = $this->generaCodice($nomeComitato, $regionale->codice ?? 'XX', $this->usedLocaliCodici[$regionale->id]);
            $this->usedLocaliCodici[$regionale->id][] = $codice;

            $locale = ComitatoLocale::create([
                'nome'                  => $this->normalizzaNomePersona($nomeComitato),
                'codice'                => $codice,
                'comitato_regionale_id' => $regionale->id,
                'attivo'                => true,
            ]);
        }

        $this->localiCache[$chiaveLocale] = $locale;

        return $locale;
    }

    private function resolviComitatoRegionale(string $nomeSede): ComitatoRegionale
    {
        $chiave = $this->normalizzaNome($nomeSede);

        if (isset($this->regionaliCache[$chiave])) {
            return $this->regionaliCache[$chiave];
        }

        $regionale = ComitatoRegionale::whereRaw('LOWER(nome) = ?', [$chiave])->first();

        if (! $regionale) {
            $usedRegionali = ComitatoRegionale::pluck('codice')->filter()->toArray();
            $codice        = $this->generaCodice($nomeSede, '', $usedRegionali);

            $regionale = ComitatoRegionale::create([
                'nome'   => $this->normalizzaNomePersona($nomeSede),
                'codice' => $codice,
                'attivo' => true,
            ]);
        }

        $this->regionaliCache[$chiave] = $regionale;

        return $regionale;
    }

    private function generaCodice(string $nome, string $prefisso, array $used): string
    {
        $clean = preg_replace('/^Comitato\s+(di\s+|dell[ao]\s+|d\'\s*)?/iu', '', $nome);
        $clean = preg_replace('/\s*-\s*ODV.*$/iu', '', $clean);
        $clean = preg_replace('/\s+ODV.*$/iu', '', $clean);

        $slug   = strtoupper(Str::ascii($clean));
        $slug   = preg_replace('/[^A-Z0-9]/', '', $slug);
        $slug   = substr($slug, 0, 12);
        $codice = $prefisso !== '' ? $prefisso . '-' . $slug : $slug;

        if (in_array($codice, $used)) {
            $i    = 2;
            $base = substr($slug, 0, 10);
            $sep  = $prefisso !== '' ? $prefisso . '-' : '';
            while (in_array($sep . $base . $i, $used)) {
                $i++;
            }
            $codice = $sep . $base . $i;
        }

        return $codice;
    }

    private function applicaTelefoni(Socio $p, array $cellulari): void
    {
        $p->prefisso_telefono  = $cellulari[0]['prefisso'];
        $p->telefono           = $cellulari[0]['numero'];
        $p->telefoni_secondari = count($cellulari) > 1
            ? array_values(array_map(
                fn ($c) => $c['prefisso'] . ' ' . $c['numero'],
                array_slice($cellulari, 1)
            ))
            : null;
    }

    private function applicaComitato(Socio $p, ComitatoLocale $comitato): void
    {
        $p->comitato_locale_id    = $comitato->id;
        $p->comitato_regionale_id = $comitato->comitato_regionale_id;
    }

    private function normalizzaNomePersona(string $valore): string
    {
        $s = mb_strtolower(trim($valore));
        return preg_replace_callback(
            '/(?:^|[\s\-\'])(\p{L})/u',
            fn (array $m) => mb_strtoupper($m[0]),
            $s
        );
    }

    private function normalizzaNome(string $nome): string
    {
        return mb_strtolower(trim($nome));
    }

    /** Vocabolario canonico del ruolo staff: 'docenza'/'logistica' — vecchie varianti da file già in uso vengono mappate. */
    private function normalizzaRuolo(string $raw): string
    {
        return match (mb_strtolower(trim($raw))) {
            'docenti', 'docente', 'formazione' => 'docenza',
            default => mb_strtolower(trim($raw)),
        };
    }

    private function parseCellulari(mixed $val): array
    {
        if (empty($val)) return [];

        $pezzi     = preg_split('/[;,\/]+|\s{2,}/', (string) $val);
        $cellulari = [];
        $visti     = [];

        foreach ($pezzi as $pezzo) {
            $parsed = $this->pulisciNumero($pezzo);
            if ($parsed === null) continue;
            $chiave = $parsed['prefisso'] . $parsed['numero'];
            if (! isset($visti[$chiave])) {
                $visti[$chiave] = true;
                $cellulari[]    = $parsed;
            }
        }

        return $cellulari;
    }

    private function pulisciNumero(string $numero): ?array
    {
        $raw = preg_replace('/[\s\-\.\(\)]+/', '', $numero);

        $prefisso = '+39';
        if (preg_match('/^(\+\d{1,4}|00\d{1,4})(.*)$/', $raw, $m)) {
            $codice   = $m[1];
            $raw      = $m[2];
            $prefisso = str_starts_with($codice, '00')
                ? '+' . ltrim(substr($codice, 2), '0')
                : $codice;
        }

        $pulito = preg_replace('/\D/', '', $raw);
        if ($pulito === '' || $pulito === null) return null;

        if ($prefisso === '+39') {
            if (strlen($pulito) === 10 && str_starts_with($pulito, '03')) {
                $pulito = substr($pulito, 1);
            }
            if (strlen($pulito) === 10 && str_starts_with($pulito, '3')) return ['prefisso' => $prefisso, 'numero' => $pulito];
            if (strlen($pulito) === 9  && str_starts_with($pulito, '3')) return ['prefisso' => $prefisso, 'numero' => $pulito];
            return null;
        }

        if (strlen($pulito) >= 5 && strlen($pulito) <= 15) {
            return ['prefisso' => $prefisso, 'numero' => $pulito];
        }

        return null;
    }

    private function parseData(mixed $val): ?\Illuminate\Support\Carbon
    {
        if ($val === null || $val === '') return null;

        if (is_numeric($val)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val);
                return \Illuminate\Support\Carbon::instance($dt);
            } catch (\Throwable) {
                return null;
            }
        }

        $str = trim((string) $val);
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'd.m.Y'] as $format) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat($format, $str);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
