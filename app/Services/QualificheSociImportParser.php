<?php

namespace App\Services;

use App\Models\CatalogoCorso;
use App\Models\QualificaSocio;
use App\Models\Socio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

class QualificheSociImportParser implements ToCollection, WithHeadingRow
{
    /** @var array<int, array> */
    public array $righe = [];

    private array $sociPerCf     = [];
    private array $corsiPerNome  = [];
    /** codice aggiornamento (uppercase) → CatalogoCorso[] base che lo elencano in corsi_aggiornamento */
    private array $basePerId     = [];

    public function collection(Collection $rows): void
    {
        $this->buildIndexes();

        foreach ($rows as $i => $row) {
            $entries = $this->analizzaRiga($row->toArray(), $i + 1);
            foreach ($entries as $entry) {
                $this->righe[] = $entry;
            }
        }
    }

    public static function fromFile(string $fullPath): self
    {
        $parser = new self();
        Excel::import($parser, $fullPath);
        return $parser;
    }

    // -----------------------------------------------------------------------

    private function buildIndexes(): void
    {
        $this->buildIndiceSoci();
        $this->buildIndiceCorsi();
    }

    private function buildIndiceSoci(): void
    {
        if (! empty($this->sociPerCf)) {
            return;
        }

        Socio::select('id', 'codice_fiscale', 'comitato_regionale_id')
            ->chunk(500, function ($soci) {
                foreach ($soci as $s) {
                    $this->sociPerCf[strtoupper($s->codice_fiscale)] = $s;
                }
            });
    }

    private function buildIndiceCorsi(): void
    {
        if (! empty($this->corsiPerNome)) {
            return;
        }

        CatalogoCorso::select('id', 'codice', 'nome', 'nomi_alternativi', 'scadenza_tipo', 'corsi_aggiornamento', 'aggiornamento_crea_base')
            ->where('attivo', true)
            ->get()
            ->each(function (CatalogoCorso $corso) {
                $this->corsiPerNome[$this->normalizzaNome($corso->nome)] = $corso;

                foreach ((array) ($corso->nomi_alternativi ?? []) as $alias) {
                    $this->corsiPerNome[$this->normalizzaNome($alias)] = $corso;
                }

                foreach ((array) ($corso->corsi_aggiornamento ?? []) as $codiceAgg) {
                    $this->basePerId[strtoupper(trim($codiceAgg))][] = $corso;
                }
            });
    }

    /**
     * Punto d'ingresso per chi ha già un Socio noto (es. import curriculum GAIA) e non ha
     * bisogno dell'indice per-CF di tutti i soci (costoso, usato solo dal flusso Excel).
     */
    public function analizzaCorsoSingolo(Socio $socio, string $nomeCorso, array $dates): array
    {
        $this->buildIndiceCorsi();

        $base = [
            'riga'    => 0,
            'cf'      => $socio->codice_fiscale,
            'cognome' => $socio->cognome,
            'nome'    => $socio->nome,
        ];

        return $this->analizzaCorso($base, $socio, $nomeCorso, $dates);
    }

    /**
     * Righe sintetiche "rimossa" per le qualifiche del socio con fonte GAIA/Excel che non
     * compaiono più tra quelle appena lette dal curriculum GAIA (probabile correzione/rimozione
     * fatta direttamente su GAIA). Deliberatamente esclude 'inserimento_manuale',
     * 'creata_da_aggiornamento' e 'calcolato' — solo un dato effettivamente arrivato da GAIA/Excel
     * può essere considerato "disallineato" da una nuova lettura GAIA; la via di fuga per
     * proteggere una riga specifica è cambiarne a mano la fonte. whereNotIn con collection vuota
     * (curriculum letto con zero corsi trovati) restituisce correttamente tutte le righe
     * candidate — comportamento voluto, non un bug.
     */
    public function trovaQualificheRimosse(Socio $socio, Collection $catalogoCorsoIdsTrovati): Collection
    {
        return QualificaSocio::where('socio_id', $socio->id)
            ->whereIn('fonte', ['importazione_gaia', 'importazione_excel'])
            ->whereNotIn('catalogo_corso_id', $catalogoCorsoIdsTrovati->all())
            ->with('catalogoCorso')
            ->get()
            ->map(fn (QualificaSocio $sq) => [
                'qualifica_nome_raw'  => $sq->catalogoCorso->nome,
                'catalogo_corso_id'   => $sq->catalogo_corso_id,
                'socio_id'            => $socio->id,
                'cf'                  => $socio->codice_fiscale,
                'cognome'             => $socio->cognome,
                'nome'                => $socio->nome,
                'data_acquisizione'   => optional($sq->data_acquisizione)->format('Y-m-d'),
                'data_ultimo_rinnovo' => optional($sq->data_ultimo_rinnovo)->format('Y-m-d'),
                'stato'               => 'rimossa',
                'errore'              => 'Non più presente nel curriculum GAIA — probabile correzione/rimozione. Verrà eliminata da Lurch se confermi.',
                'aggiorna_base'       => [],
            ]);
    }

    /**
     * Analizza una riga e restituisce un array di entry (uno per corso trovato nella cella).
     *
     * @return array<int, array>
     */
    private function analizzaRiga(array $row, int $numRiga): array
    {
        $cf      = strtoupper(trim((string) ($row['codice_fiscale'] ?? '')));
        $cognome = trim((string) ($row['cognome'] ?? ''));
        $nome    = trim((string) ($row['nome']    ?? ''));

        $base = [
            'riga'    => $numRiga,
            'cf'      => $cf,
            'cognome' => $cognome,
            'nome'    => $nome,
        ];

        $vuoto = array_merge($base, [
            'qualifica_nome_raw'  => null,
            'catalogo_corso_id'        => null,
            'socio_id'            => null,
            'data_acquisizione'   => null,
            'data_ultimo_rinnovo' => null,
            'stato'               => null,
            'errore'              => null,
            'aggiorna_base'       => [],
        ]);

        if ($cf === '') {
            return [array_merge($vuoto, ['stato' => 'saltata', 'errore' => 'Codice fiscale vuoto'])];
        }

        $socio = $this->sociPerCf[$cf] ?? null;
        if (! $socio) {
            return [array_merge($vuoto, [
                'stato'  => 'errore_cf',
                'errore' => "CF {$cf} non trovato in archivio",
            ])];
        }

        $cellQualifica = $this->trovaColonnaQualifica($row);

        if (empty($cellQualifica)) {
            return [array_merge($vuoto, [
                'socio_id' => $socio->id,
                'stato'    => 'saltata',
                'errore'   => 'Colonna qualifica vuota o assente',
            ])];
        }

        $corsiInCella = $this->parseCellaQualifiche((string) $cellQualifica);

        if (empty($corsiInCella)) {
            return [array_merge($vuoto, [
                'socio_id'           => $socio->id,
                'qualifica_nome_raw' => (string) $cellQualifica,
                'stato'              => 'saltata',
                'errore'             => 'Impossibile estrarre corsi dalla cella',
            ])];
        }

        $risultati = [];
        foreach ($corsiInCella as [$nomeCorso, $dates]) {
            $risultati[] = $this->analizzaCorso($base, $socio, $nomeCorso, $dates);
        }

        return $risultati;
    }

    public function analizzaCorso(array $base, object $socio, string $nomeCorso, array $dates): array
    {
        $entry = array_merge($base, [
            'qualifica_nome_raw'  => $nomeCorso,
            'catalogo_corso_id'        => null,
            'socio_id'            => $socio->id,
            'data_acquisizione'   => null,
            'data_ultimo_rinnovo' => null,
            'stato'               => null,
            'errore'              => null,
            'aggiorna_base'       => [],
        ]);

        $corso = $this->corsiPerNome[$this->normalizzaNome($nomeCorso)] ?? null;
        if (! $corso) {
            return array_merge($entry, [
                'stato'  => 'errore_qualifica',
                'errore' => "\"$nomeCorso\" non trovata nel catalogo — aggiornare il catalogo prima di importare",
            ]);
        }

        if (empty($dates)) {
            return array_merge($entry, [
                'catalogo_corso_id' => $corso->id,
                'stato'        => 'saltata',
                'errore'       => 'Data di acquisizione non trovata nel file: la riga viene saltata, andrà inserita manualmente se necessario.',
            ]);
        }

        sort($dates);

        if (Carbon::parse(end($dates))->isFuture()) {
            return array_merge($entry, [
                'catalogo_corso_id' => $corso->id,
                'stato'  => 'saltata',
                'errore' => 'Data nel futuro rispetto a oggi (' . end($dates) . '): riga saltata, probabile errore di battitura sull\'anno nel file di origine.',
            ]);
        }

        $haScadenza = ! empty($corso->scadenza_tipo);

        $esistente = QualificaSocio::where('socio_id', $socio->id)
            ->where('catalogo_corso_id', $corso->id)
            ->first();

        $candidataAggiornamento = Carbon::parse(end($dates));
        $aggiornaBasi    = $this->calcolaAggiornamentoBasi($corso, $socio->id, $dates[0] ?? null, $candidataAggiornamento);
        $isAggiornamento = ! empty($aggiornaBasi);
        // Se il catalogo è cambiato dopo l'ultimo import (es. abilitato "crea base" o
        // aggiunta una correlazione) può esserci un'azione da fare sul corso base
        // anche se questa riga di per sé è invariata: in tal caso non va considerata obsoleta.
        $haAzioneBase = collect($aggiornaBasi)->contains('azione_richiesta', true);

        // Import fatti a mano, in ordine non garantito, su decine di migliaia di soci:
        // data_acquisizione resta sempre la più vecchia mai vista (fatto storico),
        // data_ultimo_rinnovo (ciò che guida il calcolo scadenza) resta sempre la più
        // recente. Un file "vecchio" ricaricato per errore non fa mai retrocedere nulla.
        if ($isAggiornamento) {
            $dataAcquisizione  = $this->piuRecente($esistente?->data_acquisizione, $candidataAggiornamento);
            $dataUltimoRinnovo = null;
        } elseif ($haScadenza) {
            $tutteLeDate = collect($dates)
                ->merge(array_filter([
                    $esistente?->data_acquisizione?->toDateString(),
                    $esistente?->data_ultimo_rinnovo?->toDateString(),
                ]))
                ->unique()
                ->sort()
                ->values();
            $dataAcquisizione  = Carbon::parse($tutteLeDate->first());
            $dataUltimoRinnovo = $tutteLeDate->count() > 1 ? Carbon::parse($tutteLeDate->last()) : null;
        } else {
            $dataAcquisizione  = $this->piuRecente($esistente?->data_acquisizione, $candidataAggiornamento);
            $dataUltimoRinnovo = null;
        }

        $invariata = $esistente
            && $esistente->data_acquisizione?->toDateString() === $dataAcquisizione->toDateString()
            && $esistente->data_ultimo_rinnovo?->toDateString() === $dataUltimoRinnovo?->toDateString()
            && ! $haAzioneBase;

        if ($invariata) {
            return array_merge($entry, [
                'catalogo_corso_id' => $corso->id,
                'stato'  => 'obsoleta',
                'errore' => 'Dati già aggiornati in archivio (data uguale o più vecchia di quella registrata): riga ignorata.',
            ]);
        }

        return array_merge($entry, [
            'catalogo_corso_id'              => $corso->id,
            'data_acquisizione'         => $dataAcquisizione->toDateString(),
            'data_ultimo_rinnovo'       => $dataUltimoRinnovo?->toDateString(),
            'data_acquisizione_attuale' => $esistente?->data_acquisizione?->toDateString(),
            'stato'                     => $esistente ? 'aggiornamento' : 'inserimento',
            'aggiorna_base'             => $aggiornaBasi,
        ]);
    }

    /** Restituisce la più recente tra due date, ignorando quella nulla. */
    private function piuRecente(?Carbon $attuale, Carbon $candidata): Carbon
    {
        return ($attuale && $attuale->gt($candidata)) ? $attuale : $candidata;
    }

    private function calcolaAggiornamentoBasi(CatalogoCorso $corso, int $socioId, ?string $dataMinima = null, ?Carbon $candidataRinnovo = null): array
    {
        $codice = strtoupper(trim($corso->codice ?? ''));
        $basi   = $this->basePerId[$codice] ?? [];

        $risultati = [];
        foreach ($basi as $base) {
            if (empty($base->scadenza_tipo)) {
                continue;
            }

            $qsBase   = QualificaSocio::where('socio_id', $socioId)
                ->where('catalogo_corso_id', $base->id)
                ->first();
            $creaBase = ! $qsBase && $base->aggiornamento_crea_base && $dataMinima !== null;

            // Serve davvero fare qualcosa? O crea la base ex-novo, o spinge il rinnovo
            // solo se più recente di quello già registrato (stessa protezione anti-retrocessione).
            $riferimentoBase = $qsBase?->data_ultimo_rinnovo ?? $qsBase?->data_acquisizione;
            $azioneRichiesta = $creaBase
                || ($qsBase !== null && $candidataRinnovo !== null && (! $riferimentoBase || $candidataRinnovo->gt($riferimentoBase)));

            $risultati[] = [
                'catalogo_corso_id' => $base->id,
                'qualifica_codice'  => $base->codice,
                'qualifica_nome'    => $base->nome,
                'socio_ha_base'     => $qsBase !== null,
                'sq_base_id'        => $qsBase?->id,
                'crea_base'         => $creaBase,
                'data_minima'       => $creaBase ? $dataMinima : null,
                'azione_richiesta'  => $azioneRichiesta,
            ];
        }

        return $risultati;
    }

    private function trovaColonnaQualifica(array $row): mixed
    {
        foreach ($row as $key => $value) {
            $k = strtolower((string) $key);
            if (
                str_contains($k, 'titoli') ||
                str_contains($k, 'qualifica') ||
                str_contains($k, 'operativita') ||
                str_contains($k, 'operatività')
            ) {
                return $value ?? null;
            }
        }
        return null;
    }

    /**
     * Dalla cella GAIA estrae tutti i corsi presenti.
     * Il formato si ripete: "Nome corso - - yyyy-mm-dd[ - Nome corso - - yyyy-mm-dd]*"
     * Corsi identici con date diverse vengono raggruppati (più occorrenze = rinnovi).
     * Quando manca la data di acquisizione, GAIA scrive un trattino al posto della data
     * (es. "Nome corso - - -"): il corso viene comunque estratto, con dates vuoto.
     *
     * @return array<int, array{string, string[]}> array di [nomeCorso, dates[]]
     */
    private function parseCellaQualifiche(string $cella): array
    {
        $cella = trim($cella);
        if ($cella === '') {
            return [];
        }

        preg_match_all('/(.+?)\s+-\s+-\s+(\d{4}-\d{2}-\d{2}|-)(?=\s|$)/', $cella, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return [[trim($cella), []]];
        }

        $grouped = [];
        foreach ($matches as $match) {
            $nome = trim(preg_replace('/^[\s\-]+/', '', trim($match[1])));
            if ($nome === '') {
                continue;
            }
            $key = $this->normalizzaNome($nome);
            if (! isset($grouped[$key])) {
                $grouped[$key] = ['nome' => $nome, 'dates' => []];
            }
            if ($match[2] !== '-') {
                $grouped[$key]['dates'][] = $match[2];
            }
        }

        return array_map(
            fn($g) => [$g['nome'], array_unique($g['dates'])],
            array_values($grouped)
        );
    }

    private function normalizzaNome(string $nome): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $nome)));
    }
}
