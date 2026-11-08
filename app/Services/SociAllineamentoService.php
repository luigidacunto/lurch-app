<?php

namespace App\Services;

use App\Models\ComitatoLocale;
use App\Models\Socio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Allineamento dei soci a partire da un import GAIA: chi non compare più nella lista di un
 * comitato (ambito locale o regionale) viene segnato come dimesso (`soci.dimesso_il`); chi non
 * compare più tra le righe "Volontario in Estensione" perde la sola estensione.
 *
 * Regole in sintesi:
 *  - tocca SOLO l'appartenenza volontario (comitato_locale_id) — mai i campi *_dipendente_id
 *  - non cancella nulla, `dimesso_il` è reversibile
 *  - un CF di nuovo presente in lista azzera un `dimesso_il` precedente (rientro)
 *  - comitato_locale_id / comitato_regionale_id restano invariati (ultimo comitato noto)
 */
class SociAllineamentoService
{
    /**
     * Calcola l'anteprima senza scrivere nulla.
     *
     * @param string[] $cfAppartenenti CF (qualsiasi case) visti come volontari appartenenti nell'ambito
     * @param string[] $cfEstensioni   CF visti come volontari in estensione nell'ambito
     * @return array{dimessi: Collection<int,Socio>, estensioniDaRimuovere: Collection<int,Socio>, rientri: Collection<int,Socio>}
     */
    public function anteprima(
        array $cfAppartenenti,
        array $cfEstensioni,
        ?int $comitatoLocaleId,
        ?int $comitatoRegionaleId,
        bool $segnaDimessi,
        bool $puliziaEstensioni,
    ): array {
        $cfAppartenenti = $this->normalizzaCf($cfAppartenenti);
        $cfEstensioni   = $this->normalizzaCf($cfEstensioni);

        // Chi compare nel file in QUALSIASI forma — come appartenente o come "in estensione" —
        // è un volontario ancora attivo: al massimo si è trasferito a un altro comitato (e in
        // quel caso l'import ne aggiorna comunque l'appartenenza, o la aggiornerà il file del
        // nuovo comitato), non si è dimesso. Solo l'assenza totale dal file = dimissione.
        $cfNelFile = array_values(array_unique(array_merge($cfAppartenenti, $cfEstensioni)));

        $dimessi  = collect();
        $rientri  = collect();
        $estRimuovere = collect();

        if ($segnaDimessi) {
            $base = Socio::query()->whereNotNull('comitato_locale_id');
            $this->scopeAmbitoAppartenenza($base, $comitatoLocaleId, $comitatoRegionaleId);

            $dimessi = (clone $base)
                ->whereNull('dimesso_il')
                ->when(
                    ! empty($cfNelFile),
                    fn ($q) => $q->whereNotIn(DB::raw('upper(codice_fiscale)'), $cfNelFile)
                )
                ->orderBy('cognome')->orderBy('nome')
                ->get();

            $this->annotaIscrizioniAttive($dimessi);

            $rientri = (clone $base)
                ->whereNotNull('dimesso_il')
                ->when(
                    ! empty($cfNelFile),
                    fn ($q) => $q->whereIn(DB::raw('upper(codice_fiscale)'), $cfNelFile)
                )
                ->orderBy('cognome')->orderBy('nome')
                ->get();
        }

        if ($puliziaEstensioni) {
            $localiAmbito = $comitatoLocaleId !== null
                ? [$comitatoLocaleId]
                : ComitatoLocale::where('comitato_regionale_id', $comitatoRegionaleId)->pluck('id')->all();

            $estRimuovere = Socio::query()
                ->whereIn('comitato_locale_estensione_id', $localiAmbito)
                ->when(
                    ! empty($cfEstensioni),
                    fn ($q) => $q->whereNotIn(DB::raw('upper(codice_fiscale)'), $cfEstensioni)
                )
                ->orderBy('cognome')->orderBy('nome')
                ->get();
        }

        return [
            'dimessi'               => $dimessi,
            'estensioniDaRimuovere' => $estRimuovere,
            'rientri'               => $rientri,
        ];
    }

    /**
     * Applica l'anteprima in transazione.
     *
     * @param array{dimessi: Collection, estensioniDaRimuovere: Collection, rientri: Collection} $anteprima
     * @return array{dimessi: int, estensioni: int, rientri: int}
     */
    public function applica(array $anteprima): array
    {
        $oggi = now()->toDateString();

        return DB::transaction(function () use ($anteprima, $oggi) {
            $dimessi = Socio::whereIn('id', $anteprima['dimessi']->pluck('id'))
                ->update(['dimesso_il' => $oggi]);

            $rientri = Socio::whereIn('id', $anteprima['rientri']->pluck('id'))
                ->update(['dimesso_il' => null]);

            $estensioni = Socio::whereIn('id', $anteprima['estensioniDaRimuovere']->pluck('id'))
                ->update([
                    'comitato_locale_estensione_id'    => null,
                    'comitato_regionale_estensione_id' => null,
                    'sottosede_estensione_id'          => null,
                ]);

            return ['dimessi' => $dimessi, 'estensioni' => $estensioni, 'rientri' => $rientri];
        });
    }

    private function scopeAmbitoAppartenenza($query, ?int $comitatoLocaleId, ?int $comitatoRegionaleId): void
    {
        $comitatoLocaleId !== null
            ? $query->where('comitato_locale_id', $comitatoLocaleId)
            : $query->where('comitato_regionale_id', $comitatoRegionaleId);
    }

    /** @param string[] $cf */
    private function normalizzaCf(array $cf): array
    {
        return collect($cf)
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Aggiunge l'attributo (non persistito) `ha_iscrizioni_attive` a ogni socio: true se è
     * iscritto come corsista o staff a un evento non ancora concluso. Solo informativo per
     * l'anteprima — non cambia se il socio viene segnato dimesso.
     */
    private function annotaIscrizioniAttive(Collection $soci): void
    {
        if ($soci->isEmpty()) {
            return;
        }

        $oggi = now()->toDateString();
        $eventoAttivo = fn ($q) => $q->where(fn ($e) => $e
            ->whereNull('data_fine')->orWhereDate('data_fine', '>=', $oggi));

        $conIscrizione = Socio::whereIn('id', $soci->pluck('id'))
            ->where(fn ($q) => $q
                ->whereHas('iscrizioni', fn ($i) => $i
                    ->where('stato', '!=', 'ritirato')
                    ->whereHas('corso.evento', $eventoAttivo))
                ->orWhereHas('iscrizioniStaff.evento', $eventoAttivo))
            ->pluck('id')
            ->flip();

        foreach ($soci as $socio) {
            $socio->setAttribute('ha_iscrizioni_attive', $conIscrizione->has($socio->id));
        }
    }
}
