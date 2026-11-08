<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\Frazione;
use App\Models\Esame;
use App\Models\TcriColloquio;
use App\Models\TcriProvaPratica;
use App\Models\User;
use App\Services\SocioCorrente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Salvataggio delle schede FINALI di colloquio e prova pratica: solo il direttore, a esame in corso,
 * per i discenti ammessi (non ritirati, non «non ammessi»). Non dipende dall'interruttore «Apri la prova finale».
 * Una riga per (esame, discente), sovrascritta a ogni salvataggio (nessuno storico). Non tocca mai le schede personali.
 * Come DecisioneItinere: autorizzazione, range dal modello, regola della motivazione sempre ricalcolati qui.
 */
class FinaleProvaFinale
{
    /**
     * Colloquio finale: da min a max della scala con al più 1 decimale. Motivazione obbligatoria solo se esiste una
     * proposta (schede personali visibili, a prova finale aperta) e il valore è diverso.
     *
     * @throws ValidationException messaggi per l'utente; \RuntimeException se non autorizzato.
     */
    public static function salvaColloquio(Esame $esame, ?User $utente, int $discenteId, string $punteggio, ?string $motivazione): TcriColloquio
    {
        self::controlla($esame, $utente, $discenteId);

        $scala = $esame->modello_snapshot['colloquio']['scala'];
        $valore = str_replace(',', '.', trim($punteggio));
        if (! preg_match('/^\d+(\.\d)?$/', $valore)) {
            throw ValidationException::withMessages(['punteggio' => 'Inserisci un numero con al massimo un decimale (es. 3,5).']);
        }
        $min = Frazione::da((string) $scala['min']);
        $max = Frazione::da((string) $scala['max']);
        $f = Frazione::da($valore);
        if ($f->confronta($min) < 0 || $f->confronta($max) > 0) {
            throw ValidationException::withMessages(['punteggio' => 'Il punteggio deve essere tra '.$min->tronca(1).' e '.$max->tronca(1).'.']);
        }

        $proposta = RiepilogoProvaFinale::propostaColloquio($esame, $discenteId);
        $motivazione = trim((string) $motivazione) === '' ? null : trim((string) $motivazione);
        if ($proposta !== null && $f->confronta($proposta) !== 0 && $motivazione === null) {
            throw ValidationException::withMessages(['motivazione' => 'Il punteggio è diverso dalla proposta: la motivazione è obbligatoria.']);
        }

        return DB::transaction(function () use ($esame, $utente, $discenteId, $f, $motivazione) {
            Esame::verificaInCorsoConBlocco($esame->id);

            return TcriColloquio::updateOrCreate(
                ['esame_id' => $esame->id, 'discente_socio_id' => $discenteId, 'tipo' => TcriColloquio::TIPO_FINALE],
                ['punteggio' => $f->tronca(1), 'motivazione' => $motivazione, 'inserito_da' => SocioCorrente::perUtente($utente)],
            );
        });
    }

    /**
     * Prova pratica finale: tutte le voci del semaforo (nessun salvataggio parziale: il calcolo non deve mai vedere
     * una finale a metà). `$voci` = voce_codice => chiave della scala (rosso/giallo/verde).
     *
     * @param  array<string, ?string>  $voci
     *
     * @throws ValidationException messaggi per l'utente; \RuntimeException se non autorizzato.
     */
    public static function salvaProvaPratica(Esame $esame, ?User $utente, int $discenteId, array $voci): TcriProvaPratica
    {
        self::controlla($esame, $utente, $discenteId);

        $struttura = StrutturaSemaforo::per($esame);
        $valori = [];
        foreach ($struttura['codici'] as $codice) {
            $chiave = $voci[$codice] ?? null;
            if ($chiave !== null && ! isset($struttura['scala'][$chiave])) {
                throw ValidationException::withMessages(['voci' => 'Valore non valido per una voce.']);
            }
            $valori[$codice] = $chiave === null ? null : (string) $struttura['scala'][$chiave]['valore'];
        }
        $totale = CalcoloEsameFactory::tcri()->totaleSemaforo($esame->modello_snapshot, $valori);
        if ($totale['mancanti'] !== []) {
            throw ValidationException::withMessages(['voci' => 'Compila tutte le '.count($struttura['codici']).' voci per salvare la scheda finale (mancano '.count($totale['mancanti']).').']);
        }

        return DB::transaction(function () use ($esame, $utente, $discenteId, $valori) {
            Esame::verificaInCorsoConBlocco($esame->id);
            $scheda = TcriProvaPratica::updateOrCreate(
                ['esame_id' => $esame->id, 'discente_socio_id' => $discenteId, 'tipo' => TcriProvaPratica::TIPO_FINALE],
                ['inserito_da' => SocioCorrente::perUtente($utente)],
            );
            foreach ($valori as $codice => $valore) {
                $scheda->voci()->updateOrCreate(['voce_codice' => $codice], ['valore' => $valore]);
            }
            $scheda->touch(); // data dell'ultima modifica anche se nessuna voce è cambiata

            return $scheda;
        });
    }

    private static function controlla(Esame $esame, ?User $utente, int $discenteId): void
    {
        if (! $esame->puoInserireFinali($utente)) {
            throw new \RuntimeException('Solo il direttore può inserire le schede finali, a esame in corso.');
        }
        // Ammessi = non ritirati e non «non ammessi»: gli altri non hanno scheda finale.
        if (! $esame->discentiAmmessi()->where('iscrizioni.partecipante_id', $discenteId)->exists()) {
            throw ValidationException::withMessages(['discente' => 'Il discente non è ammesso alla prova finale (ritirato o non ammesso): non ha scheda finale.']);
        }
    }
}
