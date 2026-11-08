<?php

namespace App\Services;

use App\Models\QualificaSocio;
use Illuminate\Support\Carbon;

/**
 * Scrittura di una riga già analizzata da QualificheSociImportParser::analizzaCorso() (stato
 * inserimento/aggiornamento, catalogo_corso_id, date, cascata aggiorna_base) — estratta da
 * ListQualificheSoci::confermaImport() per essere condivisa con l'import curriculum GAIA
 * (GaiaCurriculumController::importa()). Stessa identica semantica di scrittura per entrambi
 * i flussi, solo la `fonte` cambia.
 */
class QualificaSocioImportWriter
{
    /**
     * Gerarchia di affidabilità delle fonti: manuale < excel < gaia. Una
     * fonte non presente qui (es. 'creata_da_aggiornamento', 'calcolato' — righe generate dal
     * sistema, non da un import) vale -1: sempre sovrascrivibile da un import reale.
     */
    private const GERARCHIA_FONTE = [
        'inserimento_manuale' => 0,
        'importazione_excel'  => 1,
        'importazione_gaia'   => 2,
    ];

    private static function rankFonte(?string $fonte): int
    {
        return self::GERARCHIA_FONTE[$fonte] ?? -1;
    }

    /**
     * Vero se $fonteNuova può scrivere sopra $fonteEsistente — mai una fonte meno autorevole
     * sopra una più autorevole (es. un import Excel non deve mai declassare una riga già di
     * fonte GAIA), a parità di rango vince comunque la nuova scrittura (stesso import ripetuto).
     */
    private static function fontePuoSovrascrivere(?string $fonteEsistente, string $fonteNuova): bool
    {
        return self::rankFonte($fonteNuova) >= self::rankFonte($fonteEsistente);
    }

    public static function scriviRiga(array $riga, string $fonte): void
    {
        $sq = QualificaSocio::firstOrNew([
            'socio_id'          => $riga['socio_id'],
            'catalogo_corso_id' => $riga['catalogo_corso_id'],
        ]);

        $sq->data_acquisizione   = $riga['data_acquisizione'];
        $sq->data_ultimo_rinnovo = $riga['data_ultimo_rinnovo'];
        if (self::fontePuoSovrascrivere($sq->fonte, $fonte)) {
            $sq->fonte = $fonte;
        }
        $sq->save();

        $sq->aggiornaScadenzaCalcolata();

        // Se è un aggiornamento di una qualifica base, aggiorna o crea la base (solo se la
        // data porta davvero un rinnovo più recente di quello già registrato).
        foreach ($riga['aggiorna_base'] ?? [] as $baseInfo) {
            if ($baseInfo['socio_ha_base']) {
                $sqBase = QualificaSocio::find($baseInfo['sq_base_id']);
                if ($sqBase) {
                    $nuovoRinnovo       = Carbon::parse($riga['data_acquisizione']);
                    $riferimentoAttuale = $sqBase->data_ultimo_rinnovo ?? $sqBase->data_acquisizione;
                    if (! $riferimentoAttuale || $nuovoRinnovo->gt($riferimentoAttuale)) {
                        $sqBase->data_ultimo_rinnovo = $nuovoRinnovo;
                        $sqBase->save();
                        $sqBase->aggiornaScadenzaCalcolata();
                    }
                }
            } elseif ($baseInfo['crea_base'] ?? false) {
                $sqBase = QualificaSocio::firstOrNew([
                    'socio_id'          => $riga['socio_id'],
                    'catalogo_corso_id' => $baseInfo['catalogo_corso_id'],
                ]);
                if (! $sqBase->exists) {
                    // fonte dedicata: distingue le qualifiche create in automatico dalla
                    // cascata "crea base se assente" da quelle importate direttamente, per
                    // poterle individuare/eliminare in blocco se serve (vedi
                    // qualifiche-soci:reset-auto-create)
                    $sqBase->data_acquisizione   = $baseInfo['data_minima'];
                    $sqBase->data_ultimo_rinnovo = null;
                    $sqBase->fonte               = 'creata_da_aggiornamento';
                    $sqBase->save();
                    $sqBase->aggiornaScadenzaCalcolata();
                }
            }
        }
    }

    /**
     * Riga "obsoleta" (date già registrate, nessuna scrittura di date necessaria) ma per cui
     * vale la pena ricalcolare comunque scadenza_calcolata: se il catalogo corsi è cambiato
     * dopo l'ultimo import (es. scadenza_tipo/durata modificati) la scadenza già salvata
     * potrebbe essere rimasta indietro pur essendo le date corrette.
     *
     * $fonte (opzionale, il tipo di import in corso) promuove comunque la fonte secondo la
     * stessa gerarchia di scriviRiga() anche quando le date non cambiano — es. una qualifica
     * inserita a mano e poi ritrovata identica su GAIA diventa fonte GAIA: la data non porta
     * nulla di nuovo, ma GAIA resta una fonte più autorevole del manuale. Senza $fonte (nessun
     * chiamante attuale lo omette, ma resta opzionale per non rompere firme future) il
     * comportamento è quello originale: nessun cambio a fonte.
     */
    public static function ricalcolaSeEsistente(array $riga, ?string $fonte = null): void
    {
        $sq = QualificaSocio::where('socio_id', $riga['socio_id'])
            ->where('catalogo_corso_id', $riga['catalogo_corso_id'])
            ->first();

        if (! $sq) {
            return;
        }

        if ($fonte !== null && self::fontePuoSovrascrivere($sq->fonte, $fonte) && $sq->fonte !== $fonte) {
            $sq->fonte = $fonte;
            $sq->save();
        }

        $sq->aggiornaScadenzaCalcolata();
    }

    /**
     * Riga "rimossa" (vedi QualificheSociImportParser::trovaQualificheRimosse()): la qualifica
     * non compare più nell'ultima lettura del curriculum GAIA, viene eliminata da Lurch.
     */
    public static function eliminaSeEsistente(array $riga): void
    {
        QualificaSocio::where('socio_id', $riga['socio_id'])
            ->where('catalogo_corso_id', $riga['catalogo_corso_id'])
            ->delete();
    }
}
