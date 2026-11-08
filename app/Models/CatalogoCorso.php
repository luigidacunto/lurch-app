<?php

namespace App\Models;

use App\Casts\ArrayOrCsvString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogoCorso extends Model
{
    use SoftDeletes;

    protected $table = 'catalogo_corsi';

    protected $fillable = [
        'codice',
        'nome',
        'nomi_alternativi',
        'nome_visibile',
        'tipo',
        'obiettivo',
        'livello',
        'descrizione',
        'attivo',
        'equivalente_a_id',
        'aggregatore',
        'eredita_scadenza_da_corsi',
        'scadenza_tipo',
        'scadenza_durata',
        'corsi_aggiornamento',
        'aggiornamento_crea_base',
        'scheda_pdf',
        'gestito_manualmente',
    ];

    protected function casts(): array
    {
        return [
            'attivo'                  => 'boolean',
            'aggregatore'                => 'boolean',
            'scadenza_durata'         => 'integer',
            'corsi_aggiornamento'        => ArrayOrCsvString::class,
            'nomi_alternativi'           => ArrayOrCsvString::class,
            'eredita_scadenza_da_corsi'  => ArrayOrCsvString::class,
            'aggiornamento_crea_base'    => 'boolean',
            'gestito_manualmente'        => 'boolean',
        ];
    }

    public function scopeAttivo($query)
    {
        return $query->where('attivo', true);
    }

    /**
     * Regola di validazione per campi TagsInput che contengono liste di codici corso
     * (es. corsi_aggiornamento, qualifiche propedeutiche/preferenziali graduatorie):
     * verifica che ogni codice inserito esista davvero nel catalogo.
     */
    public static function regolaCodiciValidi(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (empty($value) || ! is_array($value)) {
                return;
            }

            $codici = array_map(fn ($c) => strtoupper(trim($c)), $value);

            $trovati = static::whereIn('codice', $codici)->pluck('codice')->toArray();

            $nonTrovati = array_values(array_diff($codici, $trovati));

            if (! empty($nonTrovati)) {
                $fail('Codici non riconosciuti: ' . implode(', ', $nonTrovati));
            }
        };
    }

    public function equivalenteA(): BelongsTo
    {
        return $this->belongsTo(CatalogoCorso::class, 'equivalente_a_id');
    }

    public function corsiEquivalenti(): HasMany
    {
        return $this->hasMany(CatalogoCorso::class, 'equivalente_a_id');
    }

    /**
     * Restituisce l'array di ID che comprende il corso dato più tutti i corsi
     * che lo dichiarano equivalente (un livello solo, senza ricorsione).
     */
    public static function idsConEquivalenti(int $id): array
    {
        $extra = static::where('equivalente_a_id', $id)->pluck('id')->toArray();
        return array_merge([$id], $extra);
    }

    public function validitaRegionali(): HasMany
    {
        return $this->hasMany(CatalogoCorsoValiditaRegionale::class, 'catalogo_corso_id');
    }

    public function qualificheSoci(): HasMany
    {
        return $this->hasMany(QualificaSocio::class, 'catalogo_corso_id');
    }

    /**
     * Restituisce la regola di scadenza applicabile a una determinata regione.
     * Se esiste un override regionale lo usa, altrimenti usa il default nazionale.
     *
     * @return array{tipo: string|null, durata: int|null}
     */
    public function scadenzaPerRegione(?int $comitatoRegionaleId): array
    {
        $override = $comitatoRegionaleId !== null
            ? $this->validitaRegionali->firstWhere('comitato_regionale_id', $comitatoRegionaleId)
            : null;

        if ($override) {
            return [
                'tipo'   => $override->scadenza_tipo,
                'durata' => $override->scadenza_durata,
            ];
        }

        return [
            'tipo'   => $this->scadenza_tipo,
            'durata' => $this->scadenza_durata,
        ];
    }

    /** Stringa leggibile della scadenza nazionale. */
    public function getScadenzaNazionaleLegibileAttribute(): string
    {
        return match ($this->scadenza_tipo) {
            'mesi_esatti' => "Ogni {$this->scadenza_durata} mesi (data esatta)",
            'anno_solare' => "Ogni {$this->scadenza_durata} " . ($this->scadenza_durata === 1 ? 'anno' : 'anni') . ' (anno solare)',
            default       => 'Nessuna scadenza',
        };
    }
}
