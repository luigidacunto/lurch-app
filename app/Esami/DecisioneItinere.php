<?php

namespace App\Esami;

use App\Esami\Calcolo\Frazione;
use App\Models\Esame;
use App\Models\TcriItinereDecisione;
use App\Models\User;
use App\Services\SocioCorrente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Salvataggio della scheda finale d'area: solo il direttore, a esame in corso, per i discenti
 * non ritirati e non «non ammessi». Una riga per (esame, discente, area), sovrascritta a ogni salvataggio
 * (nessuno storico). Non tocca mai le schede personali dei docenti. Motivazione obbligatoria solo se il valore
 * differisce dalla proposta (media delle medie, o LAP × max se nessun voto) troncata a 1 decimale.
 */
class DecisioneItinere
{
    /**
     * @throws ValidationException messaggi per l'utente; \RuntimeException se non autorizzato.
     */
    public static function salva(Esame $esame, ?User $utente, int $discenteId, string $areaCodice, string $punteggio, ?string $motivazione): TcriItinereDecisione
    {
        if (! $esame->puoDecidereItinere($utente)) {
            throw new \RuntimeException('Solo il direttore può inserire il punteggio finale, a esame in corso.');
        }

        $snapshot = $esame->modello_snapshot;
        if (! in_array($areaCodice, array_column($snapshot['itinere']['aree'], 'codice'), true)) {
            throw ValidationException::withMessages(['area' => "Area sconosciuta."]);
        }
        $iscrizione = $esame->discenti()->where('iscrizioni.partecipante_id', $discenteId)->first();
        if (! $iscrizione) {
            throw ValidationException::withMessages(['discente' => 'Il discente non fa parte dell\'esame (o è ritirato).']);
        }
        if ($iscrizione->non_ammesso) {
            throw ValidationException::withMessages(['discente' => 'Il discente è segnato come non ammesso: non ha scheda finale.']);
        }

        $valore = str_replace(',', '.', trim($punteggio));
        if (! preg_match('/^\d+(\.\d)?$/', $valore)) {
            throw ValidationException::withMessages(['punteggio' => 'Inserisci un numero con al massimo un decimale (es. 3,5).']);
        }
        $min = Frazione::da((string) $snapshot['itinere']['scala']['min']);
        $max = Frazione::da((string) $snapshot['itinere']['scala']['max']);
        $f = Frazione::da($valore);
        if ($f->confronta($min) < 0 || $f->confronta($max) > 0) {
            throw ValidationException::withMessages(['punteggio' => 'Il punteggio deve essere tra '.$min->tronca(1).' e '.$max->tronca(1).'.']);
        }

        $proposta = Frazione::da(RiepilogoItinere::proposta($esame, $discenteId, $areaCodice)->tronca(1));
        $motivazione = trim((string) $motivazione) === '' ? null : trim((string) $motivazione);
        if ($f->confronta($proposta) !== 0 && $motivazione === null) {
            throw ValidationException::withMessages(['motivazione' => 'Il punteggio è diverso dalla proposta: la motivazione è obbligatoria.']);
        }

        return DB::transaction(function () use ($esame, $utente, $discenteId, $areaCodice, $f, $motivazione) {
            Esame::verificaInCorsoConBlocco($esame->id);

            return TcriItinereDecisione::updateOrCreate(
                ['esame_id' => $esame->id, 'discente_socio_id' => $discenteId, 'area_codice' => $areaCodice],
                ['punteggio' => $f->tronca(1), 'motivazione' => $motivazione, 'deciso_da' => SocioCorrente::perUtente($utente)],
            );
        });
    }
}
