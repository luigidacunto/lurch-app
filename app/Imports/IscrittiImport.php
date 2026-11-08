<?php

namespace App\Imports;

use App\Models\ComitatoLocale;
use App\Models\ComitatoSottosede;
use App\Models\Corso;
use App\Services\ComitatoResolver;
use App\Models\Iscrizione;
use App\Models\LogModificaEmail;
use App\Models\Socio;
use App\Models\SocioEvento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa gli iscritti a un corso dal formato GAIA (CRI).
 *
 * Colonne lette (per intestazione):
 *   cognome · nome · codice_fiscale · data_di_nascita
 *   email · numeri_di_telefono · comitato · sede_regionale
 *
 * Formati comitato supportati:
 *   "Comitato di Pisa"                → match diretto su comitati_locali
 *   "Litorale Pisano"                 → match su comitato_sottosedi.nome
 *   "Comitato di Pisa: Litorale Pisano" → split su ":", match comitato + sottosede
 *
 * Logica upsert (chiave: Codice Fiscale):
 *   - Se il socio non esiste → creato
 *   - Se esiste → aggiornato: email (con log), telefono, data nascita, comitato, sottosede
 *
 * Campi obbligatori per riga: cognome, nome, codice_fiscale, comitato
 *
 * Comitati non trovati in archivio → riga saltata (nessuna creazione automatica).
 *
 * Dry-run: nessuna scrittura DB, ritorna solo l'analisi delle righe.
 */
class IscrittiImport implements ToCollection, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    public int   $creati     = 0;
    public int   $aggiornati = 0;
    public int   $iscritti   = 0;
    public int   $rimossi    = 0;
    /** @var array<int, array{riga:int,stato:string,nome:string,cf:string,comitato:string,errore:string}> */
    public array $righeAnalizzate = [];

    /** @var string[] CF processati in questo import */
    private array $cfProcessati = [];

    private readonly ComitatoResolver $resolver;

    public function __construct(
        private readonly int  $corsoId,
        private readonly bool $sync    = false,
        private readonly bool $dryRun  = false,
    ) {
        $this->resolver = new ComitatoResolver();
    }

    public function collection(Collection $rows): void
    {
        $this->resolver->buildIndici();

        $corso = Corso::with('evento')->findOrFail($this->corsoId);

        // Batch lookup per determinare stati: 1 query soci + 1 query iscrizioni
        $tuttiCf = $rows
            ->map(fn ($r) => strtoupper(trim((string) ($r['codice_fiscale'] ?? ''))))
            ->filter()
            ->values()
            ->toArray();

        // CF → socio_id (solo soci esistenti)
        $cfToSocioId = Socio::whereIn('codice_fiscale', $tuttiCf)
            ->get(['id', 'codice_fiscale'])
            ->mapWithKeys(fn ($s) => [strtoupper($s->codice_fiscale) => $s->id])
            ->toArray();

        // socio_id → true (solo già iscritti a questo corso)
        $giaIscritti = [];
        if (! empty($cfToSocioId)) {
            $giaIscritti = Iscrizione::where('corso_id', $this->corsoId)
                ->whereIn('partecipante_id', array_values($cfToSocioId))
                ->pluck('partecipante_id')
                ->mapWithKeys(fn ($id) => [$id => true])
                ->toArray();
        }

        foreach ($rows as $indice => $row) {
            $numRiga = $indice + 2;
            $cf      = strtoupper(trim((string) ($row['codice_fiscale'] ?? '')));
            $nome    = $this->normalizzaNomePersona((string) ($row['nome']    ?? ''));
            $cognome = $this->normalizzaNomePersona((string) ($row['cognome'] ?? ''));

            // Analisi comitato (comune a dry-run e import reale)
            $nomeComitato = trim((string) ($row['comitato'] ?? ''));
            $risolto      = $nomeComitato !== '' ? $this->resolver->risolvi($nomeComitato) : null;

            if ($cf === '' || $nome === '' || $cognome === '') {
                $this->righeAnalizzate[] = [
                    'riga'     => $numRiga,
                    'stato'    => 'skip',
                    'nome'     => trim("{$cognome} {$nome}") ?: '—',
                    'cf'       => $cf ?: '—',
                    'comitato' => $nomeComitato ?: '—',
                    'errore'   => 'Campi obbligatori mancanti (cognome, nome, codice fiscale).',
                ];
                continue;
            }

            if ($nomeComitato !== '' && $risolto === null) {
                $this->righeAnalizzate[] = [
                    'riga'     => $numRiga,
                    'stato'    => 'skip',
                    'nome'     => "{$cognome} {$nome}",
                    'cf'       => $cf,
                    'comitato' => $nomeComitato,
                    'errore'   => "Comitato/sottosede non trovato in archivio: \"{$nomeComitato}\".",
                ];
                continue;
            }

            // Tre stati distinti:
            //   'nuovo'    = socio non esiste nel sistema → verrà creato e iscritto
            //   'iscritto' = socio esiste ma non iscritto a questo corso → verrà iscritto
            //   'ok'       = già iscritto al corso → solo aggiornamento dati
            $socioId = $cfToSocioId[$cf] ?? null;
            $stato   = match (true) {
                $socioId === null               => 'nuovo',
                ! isset($giaIscritti[$socioId]) => 'iscritto',
                default                          => 'ok',
            };

            $this->righeAnalizzate[] = [
                'riga'     => $numRiga,
                'stato'    => $stato,
                'nome'     => "{$cognome} {$nome}",
                'cf'       => $cf,
                'comitato' => $risolto
                    ? ($risolto['locale']->nome . ($risolto['sottosede'] ? ': ' . $risolto['sottosede']->nome : ''))
                    : $nomeComitato,
                'errore'   => '',
            ];

            if ($this->dryRun) {
                continue;
            }

            try {
                \DB::transaction(function () use ($row, $corso, $numRiga, $cf, $nome, $cognome, $risolto) {
                    $this->processaRiga($row, $corso, $numRiga, $cf, $nome, $cognome, $risolto);
                });
            } catch (\Throwable $e) {
                Log::error('IscrittiImport: errore su riga', [
                    'corso_id' => $this->corsoId,
                    'riga'     => $numRiga,
                    'cf'       => $cf,
                    'errore'   => $e->getMessage(),
                ]);

                // Aggiorna la riga già inserita in righeAnalizzate con l'errore
                $last = count($this->righeAnalizzate) - 1;
                $this->righeAnalizzate[$last]['stato']  = 'skip';
                $this->righeAnalizzate[$last]['errore'] = $e->getMessage();
            }
        }

        if (! $this->dryRun && $this->sync) {
            $this->rimossi = Iscrizione::where('corso_id', $this->corsoId)
                ->whereHas('partecipante', fn ($q) => $q->whereNotIn('codice_fiscale', $this->cfProcessati))
                ->delete();
        }
    }

    private function processaRiga(
        Collection $row,
        Corso $corso,
        int $numRiga,
        string $cf,
        string $nome,
        string $cognome,
        ?array $risolto,
    ): void {
        $this->cfProcessati[] = $cf;

        $email       = mb_strtolower(trim((string) ($row['email'] ?? '')));
        $dataNascita = $this->parseData($row['data_di_nascita'] ?? null);
        $cellulari   = $this->parseCellulari($row['numeri_di_telefono'] ?? null);

        $locale    = $risolto['locale']    ?? null;
        $sottosede = $risolto['sottosede'] ?? null;

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
            if ($locale)           $this->applicaComitato($partecipante, $locale, $sottosede);

            $partecipante->save();
            $this->creati++;
        } else {
            $aggiornato = false;

            // Email: aggiorna e logga se cambiata
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
                        'note'            => "Import corso #{$this->corsoId}",
                    ]);
                }
                $aggiornato = true;
            }

            // Telefono
            if (count($cellulari) && $cellulari[0]['numero'] !== $partecipante->telefono) {
                $this->applicaTelefoni($partecipante, $cellulari);
                $aggiornato = true;
            }

            // Data di nascita
            if ($dataNascita && (! $partecipante->data_nascita || ! $partecipante->data_nascita->eq($dataNascita))) {
                $partecipante->data_nascita = $dataNascita;
                $aggiornato = true;
            }

            // Comitato e sottosede
            if ($locale && $locale->id !== $partecipante->comitato_locale_id) {
                $this->applicaComitato($partecipante, $locale, $sottosede);
                $aggiornato = true;
            } elseif ($sottosede && $sottosede->id !== $partecipante->sottosede_appartenenza_id) {
                $partecipante->sottosede_appartenenza_id = $sottosede->id;
                $aggiornato = true;
            }

            if ($aggiornato) {
                $partecipante->save();
                $this->aggiornati++;
            }
        }

        // --- Iscrizione al corso ---
        $iscrizione = Iscrizione::firstOrCreate(
            ['corso_id' => $this->corsoId, 'partecipante_id' => $partecipante->id],
            ['stato' => 'pending', 'qr_code_path' => null]
        );
        if ($iscrizione->wasRecentlyCreated) $this->iscritti++;

        // --- Partecipazione evento ---
        SocioEvento::firstOrCreate(
            ['evento_id' => $corso->evento_id, 'partecipante_id' => $partecipante->id, 'ruolo' => 'corsista'],
            ['abilitato_app' => true]
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function applicaComitato(Socio $p, ComitatoLocale $locale, ?ComitatoSottosede $sottosede): void
    {
        $p->comitato_locale_id           = $locale->id;
        $p->comitato_regionale_id        = $locale->comitato_regionale_id;
        $p->sottosede_appartenenza_id    = $sottosede?->id;
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
        $nome = mb_strtolower(trim($nome));
        return preg_replace("/('\s+)/u", "'", $nome);
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
