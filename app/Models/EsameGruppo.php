<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Gruppo di lavoro dei laboratori (configurazione). */
class EsameGruppo extends Model
{
    protected $table = 'esame_gruppi';

    protected $fillable = [
        'esame_id',
        'laboratorio_id',
        'nome',
    ];

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    /**
     * Id dei soci tra $socioIds già in un altro gruppo dello stesso laboratorio
     * (un discente sta in un solo gruppo per laboratorio).
     */
    public static function discentiGiaAssegnati(int $laboratorioId, ?int $escludiGruppoId, array $socioIds): array
    {
        if (! $laboratorioId || ! $socioIds) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('esame_gruppo_discente')
            ->join('esame_gruppi', 'esame_gruppi.id', '=', 'esame_gruppo_discente.gruppo_id')
            ->where('esame_gruppi.laboratorio_id', $laboratorioId)
            ->when($escludiGruppoId, fn ($q) => $q->where('esame_gruppi.id', '!=', $escludiGruppoId))
            ->whereIn('esame_gruppo_discente.socio_id', $socioIds)
            ->pluck('esame_gruppo_discente.socio_id')
            ->unique()->values()->all();
    }

    /**
     * Opzioni del select discenti (id socio => "Cognome Nome") per un gruppo del laboratorio $laboratorioId:
     * discenti del corso non ritirati, esclusi quelli già in un altro gruppo dello stesso laboratorio.
     * I ritirati già nel gruppo $gruppo restano tra le opzioni con "(ritirato)": altrimenti il select
     * (che valida i valori contro le opzioni) impedirebbe di salvare il gruppo, o li toglierebbe in silenzio.
     */
    public static function opzioniDiscenti(Esame $esame, int $laboratorioId, ?self $gruppo): array
    {
        if (! $laboratorioId) {
            return [];
        }
        $etichetta = fn (Socio $s) => $s->cognome . ' ' . $s->nome;

        $attivi = $esame->discenti()->with('partecipante')->get()
            ->map(fn ($i) => $i->partecipante)->filter()
            ->mapWithKeys(fn ($s) => [$s->id => $etichetta($s)])->all();
        $occupati = self::discentiGiaAssegnati($laboratorioId, $gruppo?->id, array_keys($attivi));
        $opzioni  = array_diff_key($attivi, array_flip($occupati));

        foreach ($gruppo?->discenti ?? [] as $s) {
            $opzioni[$s->id] ??= $etichetta($s) . ' (ritirato)';
        }
        asort($opzioni);

        return $opzioni;
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(EsameLaboratorio::class, 'laboratorio_id');
    }

    public function discenti(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'esame_gruppo_discente', 'gruppo_id', 'socio_id');
    }

    public function docenti(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'esame_gruppo_docente', 'gruppo_id', 'socio_id');
    }
}
