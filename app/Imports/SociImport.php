<?php

namespace App\Imports;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\ComitatoSottosede;
use App\Models\Socio;
use App\Models\User;
use App\Services\ComitatoResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Importazione soci dal formato GAIA (CRI).
 *
 * Colonne XLSX attese (per nome intestazione):
 *   Cognome · Nome · Codice Fiscale · Data di Nascita
 *   Email · Numeri di telefono · Ingresso in CRI
 *   Tipo Attuale · Elenco
 *
 * "Tipo Attuale" determina il tipo di import per ogni riga:
 *   - "Volontario"             → comitato di appartenenza (Elenco = home)
 *   - "Volontario in Estensione" → comitato estensione (Elenco = extension, il socio deve già esistere)
 *   - "Dipendente"             → comitato datore di lavoro (Elenco = datore, campo comitato_locale_dipendente_id)
 *
 * Il campo "Elenco" può contenere il nome di un comitato locale o di una sua sottosede.
 * Se il nome non viene trovato in archivio la riga viene saltata con un avviso.
 *
 * Telefoni: vengono salvati solo i cellulari (prefisso 3xx).
 * Il primo cellulare unico diventa il contatto primario (campo `telefono`),
 * gli altri vanno in `telefoni_secondari` (JSON array).
 *
 * Ogni riga viene scritta nella propria transazione (vedi collection()): un errore su una
 * singola riga (es. email mancante o comitato non trovato) non causa il rollback delle altre.
 *
 * Chunking: niente WithChunkReading — quell'interfaccia fa scandire a Maatwebsite l'intero file
 * in una serie di job interni per singola chiamata a Excel::import(), pensata per essere
 * incodata con ShouldQueue, non per leggere "una sola finestra [offset, offset+limite)" per
 * chiamata. Usata insieme a WithLimit i due meccanismi interpretano limit() in modo opposto
 * (assoluto in ChunkReader::read(), relativo a startRow() in EndRowFinder::find()) e si
 * scontrano silenziosamente. Qui il chunking a finestre è pilotato da fuori (browser/poll,
 * vedi ListSoci::elaboraChunkImport()/elaboraChunkAnalisi()): ogni chiamata a Excel::import()
 * con chunkOffset/chunkLimit legge solo ed esattamente quella finestra, via WithStartRow+WithLimit.
 *
 * WithReadFilter: senza, Maatwebsite installerebbe da solo un LimitFilter (vedi
 * ReaderFactory::make()) che — a differenza del ChunkReadFilter usato da WithChunkReading —
 * non fa mai eccezione per la riga di intestazione quando startRow() > 1: la riga 1 non viene
 * caricata in memoria, WithHeadingRow non trova le etichette e ricade su chiavi numeriche
 * (0,1,2…), quindi ogni riga sembra avere campi obbligatori mancanti. Il filtro qui sotto
 * include sempre la riga 1 oltre alla finestra [startRow, startRow+limit).
 */
class SociImport implements ToCollection, WithHeadingRow, WithStartRow, WithLimit, WithReadFilter
{
    public int   $creati      = 0;
    public int   $aggiornati  = 0;
    public int   $estensioni  = 0;
    public int   $dipendenti  = 0;
    public int   $saltati     = 0;
    public int   $totale      = 0;
    /** @var string[] */
    public array $errori      = [];
    /** @var string[] */
    public array $avvisi      = [];
    /** @var string[] Tipi Attuale non riconosciuti, deduplicati */
    public array $tipiSconosciuti = [];

    /**
     * CF (upper) delle righe "Volontario" (appartenente) il cui comitato risolto ricade
     * nell'ambito di riconciliazione. Accumulato solo se $ambitoComitatoLocaleId o
     * $ambitoComitatoRegionaleId è valorizzato. Deduplicato lato chiamante.
     * @var string[]
     */
    public array $cfAppartenenti = [];

    /** Come sopra ma per le righe "Volontario in Estensione". @var string[] */
    public array $cfEstensioni = [];

    /** True appena si incontra almeno una riga "Volontario in Estensione" nel file (per validazione). */
    public bool $fileContieneEstensioni = false;

    private readonly ComitatoResolver $resolver;

    public function __construct(
        private readonly ?User $authUser    = null,
        private readonly bool  $dryRun      = false,
        /** Offset (0-based, tra le sole righe dati) da cui leggere — per l'elaborazione a chunk pilotata dal browser. Null = da capo. */
        private readonly ?int  $chunkOffset = null,
        /** Numero massimo di righe da leggere a partire da $chunkOffset. Null = nessun limite (comportamento legacy). */
        private readonly ?int  $chunkLimit  = null,
        /** Ambito riconciliazione: se valorizzato, accumula i CF delle righe di quel comitato locale. */
        private readonly ?int  $ambitoComitatoLocaleId    = null,
        /** Ambito riconciliazione: se valorizzato, accumula i CF delle righe dei comitati locali di quella regione. */
        private readonly ?int  $ambitoComitatoRegionaleId = null,
    ) {
        $this->resolver = new ComitatoResolver();
    }

    /**
     * Registra il CF nella lista giusta se la riga ricade nell'ambito di riconciliazione.
     * $isEstensione true = riga "Volontario in Estensione", false = riga "Volontario" appartenente.
     * Le righe dipendente non arrivano qui.
     */
    private function registraCfAmbito(string $cf, bool $isEstensione, ?ComitatoLocale $locale): void
    {
        if ($isEstensione) {
            $this->fileContieneEstensioni = true;
        }

        if ($locale === null) {
            return;
        }
        if ($this->ambitoComitatoLocaleId === null && $this->ambitoComitatoRegionaleId === null) {
            return;
        }

        $inAmbito = $this->ambitoComitatoLocaleId !== null
            ? $locale->id === $this->ambitoComitatoLocaleId
            : $locale->comitato_regionale_id === $this->ambitoComitatoRegionaleId;

        if (! $inAmbito) {
            return;
        }

        if ($isEstensione) {
            $this->cfEstensioni[] = $cf;
        } else {
            $this->cfAppartenenti[] = $cf;
        }
    }

    /** WithStartRow: 1-based, riga 1 = intestazione — la prima riga dati è la 2. */
    public function startRow(): int
    {
        return ($this->chunkOffset ?? 0) + 2;
    }

    /** WithLimit: relativo a startRow() (vedi EndRowFinder::find()). Nessun limite se non specificato. */
    public function limit(): int
    {
        return $this->chunkLimit ?? PHP_INT_MAX;
    }

    public function readFilter(): IReadFilter
    {
        $start        = $this->startRow();
        $endExclusive = $this->chunkLimit === null ? null : $start + $this->chunkLimit;

        return new class($start, $endExclusive) implements IReadFilter {
            public function __construct(private int $start, private ?int $endExclusive) {}

            public function readCell($column, $row, $worksheetName = ''): bool
            {
                if ($row === 1) {
                    return true; // riga di intestazione, sempre necessaria per WithHeadingRow
                }

                return $row >= $this->start && ($this->endExclusive === null || $row < $this->endExclusive);
            }
        };
    }

    /**
     * True se tutte le celle della riga sono vuote — capita per righe oltre l'ultima riga dati
     * reale che PhpSpreadsheet include comunque nel "range usato" del foglio (residuo di
     * formattazione). Non è un errore da segnalare: va semplicemente ignorata.
     */
    private function rigaVuota(Collection $row): bool
    {
        return $row->every(fn ($v) => trim((string) $v) === '');
    }

    public function collection(Collection $rows): void
    {
        $this->resolver->buildIndici();

        if ($this->dryRun) {
            $this->analizzaChunk($rows);
            return;
        }

        // Ogni riga viene salvata nella propria transazione.
        // In questo modo un errore su una riga (es. vincolo unique, campo non valido)
        // causa il rollback solo di quella riga — la connessione PostgreSQL torna pulita
        // e le righe successive non ricevono il cascata SQLSTATE[25P02].
        foreach ($rows as $i => $row) {
            if ($this->rigaVuota($row)) {
                continue;
            }

            try {
                DB::transaction(function () use ($row) {
                    $this->processaRiga($row);
                });
            } catch (\Throwable $e) {
                $rigaExcel = ($this->chunkOffset ?? 0) + 2 + $i;
                $cf        = strtoupper(trim((string) ($row['codice_fiscale'] ?? '?')));
                $cognome   = trim((string) ($row['cognome'] ?? ''));
                $nome      = trim((string) ($row['nome']    ?? ''));
                $id        = implode(' ', array_filter([$cognome, $nome, $cf ? "({$cf})" : '']));
                $this->errori[] = "Riga {$rigaExcel}: " . ($id ?: 'dati vuoti') . ': ' . $e->getMessage();
            }
        }
    }

    /**
     * Analisi dry-run di un chunk: accumula contatori e solo gli errori.
     * Usa batch SELECT per evitare N query per riga (prestazioni su 10k-20k righe).
     */
    private function analizzaChunk(Collection $rows): void
    {
        // Batch lookup CF esistenti nel chunk
        $cfs = $rows
            ->map(fn ($r) => strtoupper(trim((string) ($r['codice_fiscale'] ?? ''))))
            ->filter()
            ->values()
            ->toArray();

        $cfEsistenti = [];
        if (! empty($cfs)) {
            $cfEsistenti = Socio::withTrashed()->whereIn('codice_fiscale', $cfs)
                ->pluck('codice_fiscale')
                ->mapWithKeys(fn ($c) => [strtoupper($c) => true])
                ->toArray();
        }

        foreach ($rows as $i => $row) {
            if ($this->rigaVuota($row)) {
                continue;
            }

            $this->totale++;
            $rigaExcel = ($this->chunkOffset ?? 0) + 2 + $i;

            $cf      = strtoupper(trim((string) ($row['codice_fiscale'] ?? '')));
            $nome    = $this->normalizzaNomePersona((string) ($row['nome']    ?? ''));
            $cognome = $this->normalizzaNomePersona((string) ($row['cognome'] ?? ''));

            $dataNascitaRaw = $row['data_di_nascita'] ?? $row['data_nascita'] ?? null;
            $dataNascita    = $this->parseData($dataNascitaRaw);

            if ($cf === '' || $nome === '' || $cognome === '' || $dataNascita === null) {
                $this->saltati++;
                $id = implode(' ', array_filter([$cognome, $nome, $cf]));
                $this->errori[] = "Riga {$rigaExcel}: " . ($id ?: 'dati vuoti') . ': Campi obbligatori mancanti (cognome, nome, codice fiscale, data di nascita).';
                continue;
            }

            $tipoAttuale  = Str::lower(trim((string) ($row['tipo_attuale'] ?? '')));
            $isEstensione = str_contains($tipoAttuale, 'estensione');
            $isDipendente = str_contains($tipoAttuale, 'dipendente');
            $isVolontario = str_contains($tipoAttuale, 'volontario');

            if (! $isEstensione && ! $isDipendente && ! $isVolontario) {
                $this->saltati++;
                $tipoOrig = trim((string) ($row['tipo_attuale'] ?? '(vuoto)'));
                if ($tipoOrig !== '' && ! in_array($tipoOrig, $this->tipiSconosciuti, true)) {
                    $this->tipiSconosciuti[] = $tipoOrig;
                }
                continue;
            }

            $nomeElenco = trim((string) ($row['elenco'] ?? ''));
            $risolto    = $nomeElenco !== '' ? $this->resolver->risolvi($nomeElenco) : null;

            // Un dipendente può appartenere direttamente al Comitato Regionale (senza locale)
            $risoltoSoloRegionale = ($isDipendente && $nomeElenco !== '' && $risolto === null)
                ? $this->resolver->risolviRegionale($nomeElenco)
                : null;

            if ($nomeElenco !== '' && $risolto === null && $risoltoSoloRegionale === null) {
                $this->saltati++;
                $this->errori[] = "Riga {$rigaExcel}: {$cognome} {$nome} ({$cf}): Comitato/sottosede non trovato: \"{$nomeElenco}\".";
                continue;
            }

            if (! $isDipendente) {
                $this->registraCfAmbito($cf, $isEstensione, $risolto['locale'] ?? null);
            }

            $esiste = isset($cfEsistenti[$cf]);

            if ($isEstensione) {
                $this->estensioni++;
            } elseif ($isDipendente) {
                $esiste ? $this->aggiornati++ : $this->creati++;
                $this->dipendenti++;
            } else {
                $esiste ? $this->aggiornati++ : $this->creati++;
            }
        }
    }

    // -------------------------------------------------------------------------

    private function processaRiga(Collection $row): void
    {
        // --- Campi obbligatori (normalizzati) ---
        $cf      = strtoupper(trim((string) ($row['codice_fiscale'] ?? '')));
        $nome    = $this->normalizzaNomePersona((string) ($row['nome']    ?? ''));
        $cognome = $this->normalizzaNomePersona((string) ($row['cognome'] ?? ''));

        $dataNascitaRaw = $row['data_di_nascita'] ?? $row['data_nascita'] ?? null;
        $dataNascita    = $this->parseData($dataNascitaRaw);

        if ($cf === '' || $nome === '' || $cognome === '' || $dataNascita === null) {
            $riepilogo = implode(', ', array_filter([$cognome, $nome, $cf]));
            throw new \RuntimeException(
                "Campi obbligatori mancanti (cognome, nome, codice fiscale, data di nascita)."
                . ($riepilogo ? " Dati parziali: {$riepilogo}" : '')
            );
        }

        // --- Tipo riga ---
        $tipoAttuale  = Str::lower(trim((string) ($row['tipo_attuale'] ?? '')));
        $isEstensione = str_contains($tipoAttuale, 'estensione');
        $isDipendente = str_contains($tipoAttuale, 'dipendente');

        // --- Risolvi comitato dall'Elenco ---
        $nomeElenco = trim((string) ($row['elenco'] ?? ''));
        $risolto    = $nomeElenco !== '' ? $this->resolver->risolvi($nomeElenco) : null;

        // Un dipendente può appartenere direttamente al Comitato Regionale (senza locale)
        $regionaleDipendente = ($isDipendente && $nomeElenco !== '' && $risolto === null)
            ? $this->resolver->risolviRegionale($nomeElenco)
            : null;

        if ($nomeElenco !== '' && $risolto === null && $regionaleDipendente === null) {
            throw new \RuntimeException(
                "Comitato/sottosede non trovato in archivio: \"{$nomeElenco}\". "
                . "Socio: {$cognome} {$nome} ({$cf})."
            );
        }

        $locale    = $risolto['locale']    ?? null;
        $sottosede = $risolto['sottosede'] ?? null;

        // --- Scoping admin/admin_locale: verifica appartenenza ---
        if ($locale !== null) {
            if ($this->authUser?->isAdminLocale()) {
                if (! $isEstensione && $locale->id !== $this->authUser->comitato_locale_id) {
                    throw new \RuntimeException(
                        "Il comitato \"{$nomeElenco}\" non appartiene al tuo comitato locale. "
                        . "Socio: {$cognome} {$nome} ({$cf})."
                    );
                }
            } elseif ($this->authUser?->isAdmin()) {
                if (! $isEstensione && $locale->comitato_regionale_id !== $this->authUser->comitato_regionale_id) {
                    throw new \RuntimeException(
                        "Il comitato \"{$nomeElenco}\" non appartiene alla tua regione. "
                        . "Socio: {$cognome} {$nome} ({$cf})."
                    );
                }
            }
        } elseif ($regionaleDipendente !== null) {
            if ($this->authUser?->isAdminLocale()) {
                throw new \RuntimeException(
                    "Il comitato regionale \"{$nomeElenco}\" non appartiene al tuo comitato locale. "
                    . "Socio: {$cognome} {$nome} ({$cf})."
                );
            } elseif ($this->authUser?->isAdmin() && $regionaleDipendente->id !== $this->authUser->comitato_regionale_id) {
                throw new \RuntimeException(
                    "Il comitato \"{$nomeElenco}\" non appartiene alla tua regione. "
                    . "Socio: {$cognome} {$nome} ({$cf})."
                );
            }
        }

        // --- Riga ESTENSIONE ---
        if ($isEstensione) {
            $this->registraCfAmbito($cf, true, $locale);
            $this->processaEstensione($cf, $cognome, $nome, $dataNascita, $locale, $sottosede);
            return;
        }

        // --- Riga DIPENDENTE ---
        if ($isDipendente) {
            $this->processaDipendente($cf, $cognome, $nome, $dataNascita, $locale, $row, $sottosede, $regionaleDipendente);
            return;
        }

        // --- Riga VOLONTARIO ---
        $isVolontario = str_contains($tipoAttuale, 'volontario');
        if ($isVolontario) {
            $this->registraCfAmbito($cf, false, $locale);
            $this->processaBase($row, $cf, $nome, $cognome, $dataNascita, $locale, $sottosede);
            return;
        }

        // --- Tipo sconosciuto: salta senza modificare il DB ---
        $this->saltati++;
        $tipoOriginale = trim((string) ($row['tipo_attuale'] ?? '(vuoto)'));
        if ($tipoOriginale !== '' && ! in_array($tipoOriginale, $this->tipiSconosciuti, true)) {
            $this->tipiSconosciuti[] = $tipoOriginale;
        }
    }

    // -------------------------------------------------------------------------

    private function processaBase(
        Collection $row,
        string $cf,
        string $nome,
        string $cognome,
        \Illuminate\Support\Carbon $dataNascita,
        ?ComitatoLocale $comitato,
        ?ComitatoSottosede $sottosede = null,
    ): void {
        $socio = Socio::withTrashed()->where('codice_fiscale', $cf)->first();
        $isNew = ! $socio;

        if ($isNew) {
            $socio = new Socio();
            $socio->codice_fiscale = $cf;
        }

        $socio->cognome      = $cognome;
        $socio->nome         = $nome;
        $socio->data_nascita = $dataNascita;

        // Email: tutto minuscolo, nullable. Non sovrascrive un'email già verificata
        // con un valore diverso — protegge un contatto confermato da un dato GAIA
        // disallineato; se non è ancora verificata (o coincide), importa come sempre.
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '' && ($socio->email_verificata_at === null || $socio->email === $email)) {
            $socio->email = $email;
        }

        // Data ingresso in CRI → volontario_da
        $ingresso = $this->parseData($row['ingresso_in_cri'] ?? null);
        if ($ingresso !== null) {
            $socio->volontario_da = $ingresso;
        }

        // Comitato di appartenenza e sottosede
        if ($comitato !== null) {
            $socio->comitato_locale_id        = $comitato->id;
            $socio->comitato_regionale_id     = $comitato->comitato_regionale_id;
            $socio->sottosede_appartenenza_id = $sottosede?->id;
        }

        // Ruolo associativo: GAIA esporta solo volontari
        if ($socio->ruolo_associativo === null) {
            $socio->ruolo_associativo = 'volontario';
        }

        // Telefoni
        $cellulari = $this->parseCellulari($row['numeri_di_telefono'] ?? null);
        if (count($cellulari) > 0) {
            $socio->prefisso_telefono  = $cellulari[0]['prefisso'];
            $socio->telefono           = $cellulari[0]['numero'];
            // Numeri secondari salvati come stringa completa "prefisso numero"
            $socio->telefoni_secondari = count($cellulari) > 1
                ? array_values(array_map(
                    fn ($c) => $c['prefisso'] . ' ' . $c['numero'],
                    array_slice($cellulari, 1)
                ))
                : null;
        }

        if ($socio->trashed()) {
            $socio->restore();
        }

        $socio->save();
        $isNew ? $this->creati++ : $this->aggiornati++;
    }

    private function processaEstensione(
        string $cf,
        string $cognome,
        string $nome,
        ?\Illuminate\Support\Carbon $dataNascita,
        ?ComitatoLocale $comitato,
        ?ComitatoSottosede $sottosede = null,
    ): void {
        $socio = Socio::withTrashed()->where('codice_fiscale', $cf)->first();

        if (! $socio) {
            $socio = new Socio();
            $socio->codice_fiscale    = $cf;
            $socio->cognome           = $cognome;
            $socio->nome              = $nome;
            $socio->data_nascita      = $dataNascita;
            $socio->ruolo_associativo = 'volontario';
        }

        if ($comitato !== null) {
            $socio->comitato_locale_estensione_id    = $comitato->id;
            $socio->comitato_regionale_estensione_id = $comitato->comitato_regionale_id;
            $socio->sottosede_estensione_id          = $sottosede?->id;
        } else {
            $socio->comitato_locale_estensione_id    = null;
            $socio->comitato_regionale_estensione_id = null;
            $socio->sottosede_estensione_id          = null;
        }

        if ($socio->trashed()) {
            $socio->restore();
        }

        $socio->save();
        $this->estensioni++;
    }

    private function processaDipendente(
        string $cf,
        string $cognome,
        string $nome,
        ?\Illuminate\Support\Carbon $dataNascita,
        ?ComitatoLocale $comitato,
        Collection $row,
        ?ComitatoSottosede $sottosede = null,
        ?ComitatoRegionale $comitatoRegionale = null,
    ): void {
        $socio = Socio::withTrashed()->where('codice_fiscale', $cf)->first();
        $isNew = ! $socio;

        if ($isNew) {
            $socio = new Socio();
            $socio->codice_fiscale = $cf;
        }

        $socio->cognome      = $cognome;
        $socio->nome         = $nome;
        $socio->data_nascita = $dataNascita;

        // Non sovrascrive un'email già verificata con un valore diverso (vedi processaBase()).
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '' && ($socio->email_verificata_at === null || $socio->email === $email)) {
            $socio->email = $email;
        }

        $cellulari = $this->parseCellulari($row['numeri_di_telefono'] ?? null);
        if (count($cellulari) > 0) {
            $socio->prefisso_telefono  = $cellulari[0]['prefisso'];
            $socio->telefono           = $cellulari[0]['numero'];
            $socio->telefoni_secondari = count($cellulari) > 1
                ? array_values(array_map(
                    fn ($c) => $c['prefisso'] . ' ' . $c['numero'],
                    array_slice($cellulari, 1)
                ))
                : null;
        }

        if ($comitato !== null) {
            // Dipendente di un comitato locale specifico
            $socio->comitato_locale_dipendente_id    = $comitato->id;
            $socio->comitato_regionale_dipendente_id = $comitato->comitato_regionale_id;
            $socio->sottosede_dipendente_id          = $sottosede?->id;
        } elseif ($comitatoRegionale !== null) {
            // Dipendente assunto direttamente dal Comitato Regionale, nessun locale
            $socio->comitato_locale_dipendente_id    = null;
            $socio->comitato_regionale_dipendente_id = $comitatoRegionale->id;
            $socio->sottosede_dipendente_id          = null;
        }

        // Il ruolo dipendente ha priorità su qualsiasi ruolo precedente
        $socio->ruolo_associativo = 'dipendente';

        if ($socio->trashed()) {
            $socio->restore();
        }

        $socio->save();
        $isNew ? $this->creati++ : $this->aggiornati++;
        $this->dipendenti++;
    }

    // -------------------------------------------------------------------------
    // Normalizzazione testo
    // -------------------------------------------------------------------------

    /**
     * Title case per nomi propri italiani.
     * Capitalizza dopo spazio, trattino e apostrofo.
     *   "MARIO ROSSI"      → "Mario Rossi"
     *   "d'amico mario"    → "D'Amico Mario"
     *   "dell'aquila"      → "Dell'Aquila"
     *   "DE-LUCA"          → "De-Luca"
     */
    private function normalizzaNomePersona(string $valore): string
    {
        $s = mb_strtolower(trim($valore));

        // Capitalizza il primo carattere di ogni "parola" dopo: inizio, spazio, trattino, apostrofo
        return preg_replace_callback(
            '/(?:^|[\s\-\'])(\p{L})/u',
            fn (array $m) => mb_strtoupper($m[0]),
            $s
        );
    }

    // -------------------------------------------------------------------------
    // Telefoni
    // -------------------------------------------------------------------------

    /**
     * Estrae i numeri di cellulare da una stringa, elimina duplicati.
     * Restituisce un array di ['prefisso' => '+39', 'numero' => '3801234567'].
     *
     * @return array<int, array{prefisso: string, numero: string}>
     */
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

    /**
     * Pulisce un singolo numero e rileva il prefisso internazionale.
     * Default +39 se assente. Restituisce null se il numero non è valido.
     *
     * @return array{prefisso: string, numero: string}|null
     */
    private function pulisciNumero(string $numero): ?array
    {
        $raw = preg_replace('/[\s\-\.\(\)]+/', '', $numero);

        // Rileva prefisso internazionale (+XX o 00XX)
        $prefisso = '+39';
        if (preg_match('/^(\+\d{1,4}|00\d{1,4})(.*)$/', $raw, $m)) {
            $codice   = $m[1];
            $raw      = $m[2];
            $prefisso = str_starts_with($codice, '00')
                ? '+' . ltrim(substr($codice, 2), '0')
                : $codice;
        }

        // Tieni solo cifre
        $pulito = preg_replace('/\D/', '', $raw);
        if ($pulito === '' || $pulito === null) return null;

        if ($prefisso === '+39') {
            // Vecchio formato con 0 iniziale (es. GAIA: "0335123456") → normalizza rimuovendo lo 0
            if (strlen($pulito) === 10 && str_starts_with($pulito, '03')) {
                $pulito = substr($pulito, 1); // → 9 cifre "335123456"
            }
            // Cellulare italiano: 10 cifre che iniziano per 3 (formato moderno)
            if (strlen($pulito) === 10 && str_starts_with($pulito, '3')) {
                return ['prefisso' => $prefisso, 'numero' => $pulito];
            }
            // Cellulare italiano: 9 cifre che iniziano per 3 (formato storico senza 0)
            if (strlen($pulito) === 9 && str_starts_with($pulito, '3')) {
                return ['prefisso' => $prefisso, 'numero' => $pulito];
            }
            return null; // fisso o non riconosciuto → scarta
        }

        // Numero estero: accetta se tra 5 e 15 cifre
        if (strlen($pulito) >= 5 && strlen($pulito) <= 15) {
            return ['prefisso' => $prefisso, 'numero' => $pulito];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Date
    // -------------------------------------------------------------------------

    private function parseData(mixed $val): ?\Illuminate\Support\Carbon
    {
        if ($val === null || $val === '') return null;

        // Numero seriale Excel
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
