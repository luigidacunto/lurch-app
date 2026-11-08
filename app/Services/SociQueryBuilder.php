<?php

namespace App\Services;

use App\Filament\Resources\SocioResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SociQueryBuilder
{
    public function build(
        User $user,
        ?int $filtroRegione = null,
        ?int $filtroLocale = null,
        ?string $filtroRuolo = null,
        bool $includiEstensioni = false,
        bool $includiDipendenti = false,
        ?string $filtroGiovani = null,          // null | 'attuale' | 'ingresso'
        ?string $filtroStatoAssociativo = null, // null(=solo attivi, default) | 'dimessi' | 'tutti'
        ?string $filtroCestinati = null,        // null(=senza eliminati, default) | 'con' | 'solo'
    ): Builder {
        $query = SocioResource::getEloquentQuery();

        // "Includi estensioni/dipendenti" allargano il filtro sul comitato selezionato con un OR
        // (chi è in estensione/ha un rapporto di lavoro verso quel comitato, pur non
        // appartenendogli come volontario) — stesso meccanismo di QualificheSociQueryBuilder.
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

        if ($filtroRuolo) {
            $query->where('ruolo_associativo', $filtroRuolo);
        }

        // Soglia CRI "Giovani": meno di 32 anni compiuti — stessa soglia/logica del filtro
        // "Giovani" nella ricerca Qualifiche (QualificheSociQueryBuilder).
        // Chi manca di data_nascita (o
        // volontario_da per "ingresso") non è classificabile, escluso da entrambe.
        if ($filtroGiovani === 'attuale') {
            $query->whereNotNull('data_nascita')
                ->whereRaw("data_nascita > (CURRENT_DATE - INTERVAL '32 years')");
        } elseif ($filtroGiovani === 'ingresso') {
            $query->whereNotNull('data_nascita')
                ->whereNotNull('volontario_da')
                ->whereRaw("data_nascita > (volontario_da - INTERVAL '32 years')");
        }

        match ($filtroStatoAssociativo) {
            'dimessi' => $query->whereNotNull('dimesso_il'),
            'tutti'   => null,
            default   => $query->whereNull('dimesso_il'),
        };

        match ($filtroCestinati) {
            'con'   => null,
            'solo'  => $query->whereNotNull('deleted_at'),
            default => $query->whereNull('deleted_at'),
        };

        return $query;
    }
}
