<?php

namespace App\Services;

use App\Models\PatenteCri;
use App\Models\Socio;
use Illuminate\Support\Facades\DB;

/**
 * Analisi + scrittura delle righe "Patente CRI" lette dal curriculum GAIA di un socio, sul
 * modello di QualificheSociImportParser/QualificaSocioImportWriter ma molto più semplice: il
 * pivot `socio_patenti` non ha scadenze calcolate, gerarchie di corsi o cascate — solo
 * (tipologia, data_rilascio, data_scadenza, protocollo, fonte).
 *
 * Le tipologie GAIA (modelli 138/05 e 138/19) vengono convertite al modello 2024 da
 * PatenteCriGaiaConverter prima di essere confrontate col catalogo `patenti_cri`.
 *
 * Stati prodotti (prefisso `patente_` per non collidere con quelli delle qualifiche nella stessa
 * modale userscript):
 *   patente_inserimento | patente_aggiornamento | patente_obsoleta | patente_non_convertibile | patente_rimossa
 */
class PatenteCriGaiaImport
{
    private const FONTE = 'importazione_gaia';

    /**
     * @param  array<int, array{tipologia_raw?:string, data_rilascio?:?string, data_scadenza?:?string, protocollo?:?string}>  $righeGaia
     * @return array<int, array<string, mixed>>
     */
    public static function analizza(Socio $socio, array $righeGaia): array
    {
        $catalogo = PatenteCri::pluck('id', 'tipologia'); // tipologia 2024 => id
        $righe    = [];
        $idTrovati = [];

        foreach (self::deduplica($righeGaia) as $g) {
            $tipoRaw   = PatenteCriGaiaConverter::normalizza((string) ($g['tipologia_raw'] ?? ''));
            $rilascio  = self::dataONull($g['data_rilascio'] ?? null);
            $scadenza  = self::dataONull($g['data_scadenza'] ?? null);
            $protocollo = self::stringaONull($g['protocollo'] ?? null);

            $base = [
                'tipo_riga'          => 'patente',
                'socio_id'           => $socio->id,
                'qualifica_nome_raw' => "Patente CRI {$tipoRaw}",
                'data_rilascio'      => $rilascio,
                'data_scadenza'      => $scadenza,
                'protocollo'         => $protocollo,
                // campi riusati dalla tabella della modale userscript (colonna "Date")
                'data_acquisizione'   => $rilascio,
                'data_ultimo_rinnovo' => $scadenza,
            ];

            $conv = PatenteCriGaiaConverter::converti($tipoRaw);

            if (! $conv['convertibile']) {
                $righe[] = array_merge($base, ['stato' => 'patente_non_convertibile', 'errore' => $conv['nota']]);
                continue;
            }

            $tipo2024 = $conv['tipologia_2024'];
            $patenteId = $catalogo[$tipo2024] ?? null;

            if (! $patenteId) {
                $righe[] = array_merge($base, [
                    'stato'  => 'patente_non_convertibile',
                    'errore' => "Tipo 2024 \"$tipo2024\" non presente nel catalogo patenti — aggiornare il catalogo prima di importare.",
                ]);
                continue;
            }

            $idTrovati[] = $patenteId;
            $base['patente_cri_id']  = $patenteId;
            $base['qualifica_nome_raw'] = self::etichetta($tipoRaw, $tipo2024);
            if ($conv['nota']) {
                $base['errore'] = $conv['nota'];
            }

            $esistente = DB::table('socio_patenti')
                ->where('socio_id', $socio->id)
                ->where('patente_cri_id', $patenteId)
                ->first();

            if (! $esistente) {
                $righe[] = array_merge($base, ['stato' => 'patente_inserimento']);
                continue;
            }

            $invariata = self::dataONull($esistente->data_rilascio) === $rilascio
                && self::dataONull($esistente->data_scadenza) === $scadenza
                && self::stringaONull($esistente->protocollo) === $protocollo;

            $righe[] = array_merge($base, ['stato' => $invariata ? 'patente_obsoleta' : 'patente_aggiornamento']);
        }

        // Riconciliazione: patenti già in Lurch con fonte GAIA ma non più nel curriculum.
        $rimosse = DB::table('socio_patenti')
            ->join('patenti_cri', 'patenti_cri.id', '=', 'socio_patenti.patente_cri_id')
            ->where('socio_patenti.socio_id', $socio->id)
            ->where('socio_patenti.fonte', self::FONTE)
            ->when($idTrovati !== [], fn ($q) => $q->whereNotIn('socio_patenti.patente_cri_id', $idTrovati))
            ->select('patenti_cri.id', 'patenti_cri.tipologia')
            ->get();

        foreach ($rimosse as $r) {
            $righe[] = [
                'tipo_riga'          => 'patente',
                'socio_id'           => $socio->id,
                'patente_cri_id'     => $r->id,
                'qualifica_nome_raw' => "Patente CRI tipo {$r->tipologia}",
                'stato'              => 'patente_rimossa',
                'errore'             => 'Non più presente nel curriculum GAIA — verrà rimossa da Lurch se confermi.',
            ];
        }

        return $righe;
    }

    /** @return array{inseriti:int, aggiornati:int, rimossi:int, errori:array<int,string>} */
    public static function importa(Socio $socio, array $righe): array
    {
        $inseriti = 0;
        $aggiornati = 0;
        $rimossi = 0;
        $errori = [];

        foreach ($righe as $riga) {
            if (($riga['tipo_riga'] ?? null) !== 'patente') {
                continue;
            }
            if ((int) ($riga['socio_id'] ?? 0) !== $socio->id) {
                continue;
            }

            try {
                $stato = $riga['stato'] ?? null;

                if ($stato === 'patente_rimossa') {
                    $socio->patenti()->detach($riga['patente_cri_id']);
                    $rimossi++;
                    continue;
                }

                if (! in_array($stato, ['patente_inserimento', 'patente_aggiornamento'], true)) {
                    continue;
                }

                $socio->patenti()->syncWithoutDetaching([
                    $riga['patente_cri_id'] => [
                        'fonte'         => self::FONTE,
                        'data_rilascio' => self::dataONull($riga['data_rilascio'] ?? null),
                        'data_scadenza' => self::dataONull($riga['data_scadenza'] ?? null),
                        'protocollo'    => self::stringaONull($riga['protocollo'] ?? null),
                    ],
                ]);

                $stato === 'patente_inserimento' ? $inseriti++ : $aggiornati++;
            } catch (\Throwable $e) {
                $errori[] = $e->getMessage();
            }
        }

        return compact('inseriti', 'aggiornati', 'rimossi', 'errori');
    }

    /**
     * Il curriculum GAIA elenca lo storico completo: la stessa tipologia compare più volte, una
     * riga per ogni rilascio/rinnovo (es. "PATENTE CRI 3" con scadenza vecchia + "PATENTE CRI 3"
     * rinnovata). Teniamo solo la più recente per tipologia — quella con `data_rilascio`
     * maggiore ("Y-m-d" confrontabile come stringa; riga senza data considerata la più vecchia).
     *
     * @param  array<int, array<string, mixed>>  $righeGaia
     * @return array<int, array<string, mixed>>
     */
    private static function deduplica(array $righeGaia): array
    {
        $perTipo = [];

        foreach ($righeGaia as $g) {
            $key      = PatenteCriGaiaConverter::normalizza((string) ($g['tipologia_raw'] ?? ''));
            $rilascio = self::dataONull($g['data_rilascio'] ?? null) ?? '';

            if (! isset($perTipo[$key]) || $rilascio >= ($perTipo[$key]['_r'] ?? '')) {
                $perTipo[$key] = $g + ['_r' => $rilascio];
            }
        }

        return array_values(array_map(fn ($g) => collect($g)->except('_r')->all(), $perTipo));
    }

    private static function etichetta(string $tipoRaw, string $tipo2024): string
    {
        $norm = PatenteCriGaiaConverter::normalizza($tipoRaw);

        return $norm === $tipo2024
            ? "Patente CRI tipo {$tipo2024}"
            : "Patente CRI tipo {$norm} → tipo {$tipo2024} (2024)";
    }

    private static function dataONull(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        return substr($v, 0, 10); // "Y-m-d" o "Y-m-d H:i:s" dal DB
    }

    private static function stringaONull(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
