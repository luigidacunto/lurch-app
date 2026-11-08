<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * Gruppo di tutoraggio (configurazione): discenti seguiti da uno o più docenti tutor lungo tutto il percorso.
 * Un discente sta in un solo gruppo per esame; un docente può essere tutor in più gruppi. Le schede non dipendono
 * dai gruppi: il gruppo decide solo cosa il tutor può leggere (PermessiEsame::discentiTutorati).
 */
class EsameTutoraggio extends Model
{
    protected $table = 'esame_tutoraggi';

    protected $fillable = [
        'esame_id',
        'nome',
    ];

    public function esame(): BelongsTo
    {
        return $this->belongsTo(Esame::class, 'esame_id');
    }

    public function discenti(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'esame_tutoraggio_discente', 'tutoraggio_id', 'socio_id');
    }

    public function docenti(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'esame_tutoraggio_docente', 'tutoraggio_id', 'socio_id');
    }

    /** Id dei soci tra $socioIds già in un altro gruppo di tutoraggio dello stesso esame. */
    public static function discentiGiaAssegnati(int $esameId, ?int $escludiId, array $socioIds): array
    {
        if (! $socioIds) {
            return [];
        }

        return DB::table('esame_tutoraggio_discente')
            ->join('esame_tutoraggi', 'esame_tutoraggi.id', '=', 'esame_tutoraggio_discente.tutoraggio_id')
            ->where('esame_tutoraggi.esame_id', $esameId)
            ->when($escludiId, fn ($q) => $q->where('esame_tutoraggi.id', '!=', $escludiId))
            ->whereIn('esame_tutoraggio_discente.socio_id', $socioIds)
            ->pluck('esame_tutoraggio_discente.socio_id')
            ->unique()->values()->all();
    }

    /**
     * Opzioni del select discenti: non ritirati, esclusi quelli già in un altro gruppo di tutoraggio. I ritirati già nel
     * gruppo restano con "(ritirato)": il multi-select valida i valori contro le opzioni (stesso motivo di EsameGruppo).
     */
    public static function opzioniDiscenti(Esame $esame, ?self $gruppo): array
    {
        $etichetta = fn (Socio $s) => $s->cognome . ' ' . $s->nome;

        $attivi = $esame->discenti()->with('partecipante')->get()
            ->map(fn ($i) => $i->partecipante)->filter()
            ->mapWithKeys(fn ($s) => [$s->id => $etichetta($s)])->all();
        $opzioni = array_diff_key($attivi, array_flip(self::discentiGiaAssegnati($esame->id, $gruppo?->id, array_keys($attivi))));

        foreach ($gruppo?->discenti ?? [] as $s) {
            $opzioni[$s->id] ??= $etichetta($s) . ' (ritirato)';
        }
        asort($opzioni);

        return $opzioni;
    }
}
