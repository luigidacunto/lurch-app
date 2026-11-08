<?php

namespace App\Services;

use App\Models\CatalogoCorso;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class QualificheSociQueryBuilder
{
    private array $idsCache = [];

    public function build(
        User $user,
        array $qualificheRichieste = [],
        array $qualificheEscluse = [],
        string $operatoreRichieste = 'and',
        ?int $filtroRegione = null,
        ?int $filtroLocale = null,
        bool $includiEstensioni = false,
        bool $includiDipendenti = false,
        ?string $filtroStatoScadenza = null,
        ?string $filtroGiovani = null,
        array $with = [],
    ): Builder {
        $query = Socio::query()->with(array_merge([
            'qualificheSoci.catalogoCorso.validitaRegionali',
            'comitatoLocale',
            'comitatoRegionale',
        ], $with));

        // I soci dimessi non sono operatori: mai nella ricerca qualifiche (né negli export
        // Excel/PDF che passano di qui). Nessun toggle — scelta esplicita.
        $query->whereNull('soci.dimesso_il');

        // L'operatore è scoped a livello locale se il suo account ha un comitato locale
        // assegnato (impostato dall'admin locale che lo crea), altrimenti a livello regionale
        // (impostato dall'admin regionale) — stesso criterio di UserResource::form().
        // Il toggle "Includi soci in estensione" allarga anche lo scope di permesso, non solo
        // il filtro comitato: un admin_locale può gestire le qualifiche dei soci in estensione
        // al proprio comitato (coerente con l'import GAIA che li scrive, canEditQualifiche()).
        if ($user->isAdminLocale() || ($user->isOperatore() && $user->comitato_locale_id)) {
            $query->where(fn ($q) => $q
                ->where('comitato_locale_id', $user->comitato_locale_id)
                ->orWhere('comitato_locale_dipendente_id', $user->comitato_locale_id)
                ->when($includiEstensioni, fn ($q) => $q
                    ->orWhere('comitato_locale_estensione_id', $user->comitato_locale_id))
            );
        } elseif ($user->isAdmin() || $user->isOperatore()) {
            $query->where(fn ($q) => $q
                ->where('comitato_regionale_id', $user->comitato_regionale_id)
                ->orWhere('comitato_regionale_dipendente_id', $user->comitato_regionale_id)
                ->when($includiEstensioni, fn ($q) => $q
                    ->orWhere('comitato_regionale_estensione_id', $user->comitato_regionale_id))
            );
        }

        if ($filtroRegione) {
            $r = $filtroRegione;
            $query->where(function ($q) use ($r, $includiEstensioni, $includiDipendenti) {
                $q->where('comitato_regionale_id', $r);
                if ($includiEstensioni) {
                    $q->orWhere('comitato_regionale_estensione_id', $r);
                }
                if ($includiDipendenti) {
                    $q->orWhere('comitato_regionale_dipendente_id', $r);
                }
            });
        }

        if ($filtroLocale) {
            $l = $filtroLocale;
            $query->where(function ($q) use ($l, $includiEstensioni, $includiDipendenti) {
                $q->where('comitato_locale_id', $l);
                if ($includiEstensioni) {
                    $q->orWhere('comitato_locale_estensione_id', $l);
                }
                if ($includiDipendenti) {
                    $q->orWhere('comitato_locale_dipendente_id', $l);
                }
            });
        }

        if (! empty($qualificheRichieste)) {
            if ($operatoreRichieste === 'or') {
                $allIds = [];
                foreach ($qualificheRichieste as $qualId) {
                    $allIds = array_merge($allIds, $this->getIds((int) $qualId));
                }
                $query->whereHas('qualificheSoci', fn ($q) =>
                    $q->whereIn('catalogo_corso_id', array_unique($allIds))
                );
            } else {
                foreach ($qualificheRichieste as $qualId) {
                    $ids = $this->getIds((int) $qualId);
                    $query->whereHas('qualificheSoci', fn ($q) =>
                        $q->whereIn('catalogo_corso_id', $ids)
                    );
                    $corso = CatalogoCorso::find($qualId);
                    $ereditaDa = $corso?->eredita_scadenza_da_corsi ?? [];
                    if (! empty($ereditaDa)) {
                        $corsiSuperioriIds = CatalogoCorso::whereIn('codice', $ereditaDa)->pluck('id');
                        $query->whereDoesntHave('qualificheSoci', fn ($q) =>
                            $q->whereIn('catalogo_corso_id', $corsiSuperioriIds)
                        );
                    }
                }
            }
        }

        if (! empty($qualificheRichieste) && in_array($filtroStatoScadenza, ['valide', 'scadute'])) {
            $idsRichieste = [];
            foreach ($qualificheRichieste as $qualId) {
                $idsRichieste = array_merge($idsRichieste, $this->getIds((int) $qualId));
            }
            $query->whereHas('qualificheSoci', function ($q) use ($idsRichieste, $filtroStatoScadenza) {
                $q->whereIn('catalogo_corso_id', array_unique($idsRichieste))
                    ->whereNotNull('scadenza_calcolata');
                // "scaduta" equivale a scadenza_calcolata < oggi per entrambi i tipi di
                // regola (mesi_esatti e anno_solare, che è sempre endOfYear) — vedi
                // QualificaSocio::calcolaStatoScadenza().
                $filtroStatoScadenza === 'scadute'
                    ? $q->whereDate('scadenza_calcolata', '<', now())
                    : $q->whereDate('scadenza_calcolata', '>=', now());
            });
        }

        // Soglia "Giovani" CRI: meno di 32 anni compiuti. Chi manca di data_nascita (o, per il
        // filtro "ingresso", di volontario_da) non può essere classificato: escluso da entrambi,
        // mai incluso per default.
        if ($filtroGiovani === 'attuale') {
            $query->whereNotNull('data_nascita')
                ->whereRaw("data_nascita > (CURRENT_DATE - INTERVAL '32 years')");
        } elseif ($filtroGiovani === 'ingresso') {
            $query->whereNotNull('data_nascita')
                ->whereNotNull('volontario_da')
                ->whereRaw("data_nascita > (volontario_da - INTERVAL '32 years')");
        }

        foreach ($qualificheEscluse as $qualId) {
            $ids = $this->getIds((int) $qualId);
            $query->whereDoesntHave('qualificheSoci', fn ($q) =>
                $q->whereIn('catalogo_corso_id', $ids)
            );
        }

        return $query;
    }

    public function getIds(int $qualId): array
    {
        return $this->idsCache[$qualId] ??= CatalogoCorso::idsConEquivalenti($qualId);
    }
}
