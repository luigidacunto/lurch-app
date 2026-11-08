<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualificaSocio extends Model
{
    protected $table = 'qualifiche_soci';

    protected $fillable = [
        'socio_id',
        'catalogo_corso_id',
        'data_acquisizione',
        'data_ultimo_rinnovo',
        'scadenza_calcolata',
        'fonte',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_acquisizione'   => 'date',
            'data_ultimo_rinnovo' => 'date',
            'scadenza_calcolata'  => 'date',
        ];
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function catalogoCorso(): BelongsTo
    {
        return $this->belongsTo(CatalogoCorso::class, 'catalogo_corso_id');
    }

    /**
     * Calcola la data di scadenza in base alle regole del corso e alla regione del socio.
     * Restituisce null se il corso non scade.
     */
    public function calcolaScadenza(): ?Carbon
    {
        $corso = $this->catalogoCorso ?? $this->catalogoCorso()->first();
        $socio = $this->socio ?? $this->socio()->first();

        $regola = $corso->scadenzaPerRegione($socio->comitatoRegionaleEffettivoId());

        if ($regola['tipo'] === 'nessuna' || $regola['tipo'] === null) {
            return null;
        }

        $base = $this->data_ultimo_rinnovo ?? $this->data_acquisizione;

        return match ($regola['tipo']) {
            'mesi_esatti' => $base->copy()->addMonths($regola['durata']),
            'anno_solare' => $base->copy()->addYears($regola['durata'])->endOfYear(),
            default       => null,
        };
    }

    /**
     * Ricalcola e persiste scadenza_calcolata. Da chiamare dopo ogni modifica alle date.
     * Se il corso eredita la scadenza da altri corsi e il socio ne possiede uno:
     * - se tra quelli posseduti ce n'è uno SENZA scadenza propria (es. FTSSA, valutato
     *   su criteri non tracciati come le docenze anziché su un rinnovo periodico),
     *   il socio è esonerato del tutto per questo corso — scadenza_calcolata resta
     *   null a prescindere da eventuali AGGTSSA/AGGTTSSA caricati o mancanti;
     * - altrimenti prende la più recente tra la scadenza propria e quella ereditata
     *   (un rinnovo fatto solo sul corso base, es. TS via ATS, non deve restare
     *   "coperto" da un corso superiore posseduto ma non più aggiornato, es. SA scaduto).
     * Poi propaga in cascade ai corsi che a loro volta ereditano da questo.
     */
    public function aggiornaScadenzaCalcolata(): void
    {
        $corso = $this->catalogoCorso ?? $this->catalogoCorso()->first();
        $ereditaDa = $corso->eredita_scadenza_da_corsi ?? [];
        $scadenza = $this->calcolaScadenza();

        if (! empty($ereditaDa)) {
            $corsiSuperiori = CatalogoCorso::whereIn('codice', $ereditaDa)->get(['id', 'scadenza_tipo']);
            $corsiEsonero = $corsiSuperiori->whereNull('scadenza_tipo')->pluck('id');
            $corsiConScadenza = $corsiSuperiori->whereNotNull('scadenza_tipo')->pluck('id');

            $haEsonero = $corsiEsonero->isNotEmpty() && self::where('socio_id', $this->socio_id)
                ->whereIn('catalogo_corso_id', $corsiEsonero)
                ->exists();

            if ($haEsonero) {
                $scadenza = null;
            } elseif ($corsiConScadenza->isNotEmpty()) {
                $qsEredita = self::where('socio_id', $this->socio_id)
                    ->whereIn('catalogo_corso_id', $corsiConScadenza)
                    ->whereNotNull('scadenza_calcolata')
                    ->orderByDesc('scadenza_calcolata')
                    ->first();

                if ($qsEredita && (! $scadenza || $qsEredita->scadenza_calcolata->gt($scadenza))) {
                    $scadenza = $qsEredita->scadenza_calcolata;
                }
            }
        }

        $this->scadenza_calcolata = $scadenza;
        $this->saveQuietly();
        $this->propagaEreditaCascata($corso->codice);
        $this->propagaAggregatore($corso);
    }

    /**
     * Se questo corso è agganciato (equivalente_a_id) a un corso "aggregatore" (mai
     * conseguito direttamente, es. Salute e Sicurezza per i 6 corsi Sicurezza),
     * aggiorna/crea la sua riga con la scadenza più recente tra tutti i corsi del
     * gruppo posseduti da questo socio.
     */
    private function propagaAggregatore(CatalogoCorso $corso): void
    {
        if (! $corso->equivalente_a_id) {
            return;
        }

        $aggregatore = $corso->equivalenteA ?? $corso->equivalenteA()->first();
        if (! $aggregatore?->aggregatore) {
            return;
        }

        $gruppoIds = CatalogoCorso::where('equivalente_a_id', $aggregatore->id)->pluck('id');
        $maxScadenza = self::where('socio_id', $this->socio_id)
            ->whereIn('catalogo_corso_id', $gruppoIds)
            ->max('scadenza_calcolata');

        $sqAggregatore = self::firstOrNew([
            'socio_id'          => $this->socio_id,
            'catalogo_corso_id' => $aggregatore->id,
        ]);
        if (! $sqAggregatore->exists) {
            $sqAggregatore->data_acquisizione = $this->data_acquisizione;
            $sqAggregatore->fonte             = 'calcolato';
        }
        $sqAggregatore->scadenza_calcolata = $maxScadenza;
        $sqAggregatore->saveQuietly();
    }

    private function propagaEreditaCascata(string $codice): void
    {
        $dipendenti = CatalogoCorso::whereJsonContains('eredita_scadenza_da_corsi', $codice)->get();
        foreach ($dipendenti as $corsoDip) {
            $qsDip = self::where('socio_id', $this->socio_id)
                ->where('catalogo_corso_id', $corsoDip->id)
                ->first();
            $qsDip?->aggiornaScadenzaCalcolata();
        }
    }

    /**
     * Stato testuale: valida | in_scadenza | scaduta | nessuna_scadenza
     * mesi_esatti → scaduta se passata, in_scadenza se ≤6 mesi, valida se >6 mesi
     * anno_solare  → scaduta se anno passato, in_scadenza se anno corrente, valida se anno futuro
     */
    public function getStatoScadenzaAttribute(): string
    {
        return match ($this->calcolaStatoScadenza()) {
            'scaduta'     => 'scaduta',
            'in_scadenza' => 'in_scadenza',
            'valida'      => 'valida',
            default       => 'nessuna_scadenza',
        };
    }

    /**
     * Colore Filament: danger | warning | success | gray
     */
    public function getColoreScadenzaAttribute(): string
    {
        return match ($this->calcolaStatoScadenza()) {
            'scaduta'     => 'danger',
            'in_scadenza' => 'warning',
            'valida'      => 'success',
            default       => 'gray',
        };
    }

    private function calcolaStatoScadenza(): string
    {
        if ($this->scadenza_calcolata === null) {
            return 'nessuna_scadenza';
        }

        $corso  = $this->catalogoCorso ?? $this->catalogoCorso()->first();
        $socio  = $this->socio ?? $this->socio()->first();
        $regola = $corso->scadenzaPerRegione($socio->comitatoRegionaleEffettivoId());

        $oggi     = Carbon::today();
        $scadenza = $this->scadenza_calcolata;

        if ($regola['tipo'] === 'mesi_esatti') {
            if ($scadenza->lt($oggi)) return 'scaduta';
            return $oggi->diffInMonths($scadenza) > 6 ? 'valida' : 'in_scadenza';
        }

        if ($regola['tipo'] === 'anno_solare') {
            $diff = $scadenza->year - $oggi->year;
            if ($diff > 0) return 'valida';
            if ($diff === 0) return 'in_scadenza';
            return 'scaduta';
        }

        return 'nessuna_scadenza';
    }
}
