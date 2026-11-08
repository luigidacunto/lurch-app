<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CameraEvento;
use App\Models\OspiteEvento;
use App\Models\Presenza;
use App\Models\Socio;
use App\Traits\TracksUpdatedBy;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Evento extends Model implements HasMedia
{
    use InteractsWithMedia;
    use TracksUpdatedBy;

    protected $table = 'eventi';

    protected $fillable = [
        'comitato_regionale_id',
        'comitato_locale_id',
        'nome',
        'descrizione',
        'data_inizio',
        'data_fine',
        'giorni_evento',
        'residenziale',
        'sede',
        'indirizzo',
        'coordinate_maps',
        'orario_campo',
        'attivo',
        'privacy_override_informativa',
        'privacy_override_informativa_pdf',
        'privacy_override_foto_video',
        'privacy_override_foto_video_pdf',
        'form_staff_aperto_at',
        'invio_massivo_staff_at',
        'invio_massivo_sollecito_staff_at',
        'invio_massivo_info_staff_at',
        'badge_config',
        'note_app',
        'candidature_staff_apertura_at',
        'candidature_staff_chiusura_at',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio'             => 'date',
            'data_fine'               => 'date',
            'giorni_evento'           => 'array',
            'residenziale'            => 'boolean',
            'orario_campo'            => 'array',
            'attivo'                  => 'boolean',
            'form_staff_aperto_at'    => 'datetime',
            'invio_massivo_staff_at'           => 'datetime',
            'invio_massivo_sollecito_staff_at' => 'datetime',
            'invio_massivo_info_staff_at'      => 'datetime',
            'badge_config'            => 'array',
            'candidature_staff_apertura_at' => 'datetime',
            'candidature_staff_chiusura_at' => 'datetime',
        ];
    }

    /** Y-m-d di ogni giorno del range (vuoto se date mancanti/invertite). */
    public static function giorniRange(?string $inizio, ?string $fine): array
    {
        if (! $inizio || ! $fine || $fine < $inizio) return [];

        return collect(\Carbon\CarbonPeriod::create($inizio, $fine))->map(fn ($d) => $d->format('Y-m-d'))->all();
    }

    protected static function booted(): void
    {
        // giorni_evento è la fonte: ordinati e con finestra data_inizio/data_fine = primo/ultimo giorno.
        // Scatta solo se l'elenco cambia, così gli eventi esistenti con finestra più ampia non vengono toccati.
        static::saving(function (self $evento) {
            if ($evento->isDirty('giorni_evento') && filled($evento->giorni_evento)) {
                $giorni = collect($evento->giorni_evento)->filter()->unique()->sort()->values();
                $evento->giorni_evento = $giorni->all();
                $evento->data_inizio   = $giorni->first();
                $evento->data_fine     = $giorni->last();
            }
        });

        static::creating(function (self $evento) {
            if (empty($evento->candidature_staff_uuid)) {
                $evento->candidature_staff_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function scopeAttivo($query)
    {
        return $query->where('attivo', true);
    }

    /** Le candidature staff sono aperte in questo momento? Richiede entrambe le date configurate. */
    public function candidatureStaffAperte(): bool
    {
        if (! $this->candidature_staff_apertura_at || ! $this->candidature_staff_chiusura_at) {
            return false;
        }

        return now()->between($this->candidature_staff_apertura_at, $this->candidature_staff_chiusura_at);
    }

    /**
     * Giorni realmente attivi dell'evento, ordinati cronologicamente — può essere
     * un range continuo o giorni sparsi (es. weekend di settimane diverse).
     * Punto unico da cui leggere "quali giorni ha questo evento": non usare
     * CarbonPeriod::create(data_inizio, data_fine) altrove.
     */
    public function giorniEvento(): \Illuminate\Support\Collection
    {
        return collect($this->giorni_evento ?? [])
            ->map(fn ($d) => \Carbon\Carbon::parse($d))
            ->sortBy(fn ($d) => $d->format('Y-m-d'))
            ->values();
    }

    /**
     * Giorni "operativi": i giorni reali più il giorno prima e il giorno dopo di ogni blocco
     * (montaggio/smontaggio, colazione). Fonte unica per tutto ciò che deve mostrare o ammettere
     * "i giorni dell'evento" (navigatori giorno, finestra check-in, scadenza badge) — mai il
     * range data_inizio→data_fine, che per molti eventi è più largo dei giorni reali.
     */
    public function giorniOperativi(): \Illuminate\Support\Collection
    {
        return $this->giorniEvento()
            ->flatMap(fn ($d) => [$d->copy()->subDay(), $d->copy(), $d->copy()->addDay()])
            ->unique(fn ($d) => $d->format('Y-m-d'))
            ->sortBy(fn ($d) => $d->format('Y-m-d'))
            ->values();
    }

    /**
     * Elenco leggibile di giorni, con i giorni consecutivi raggruppati: "24–25 ottobre, 7–8 novembre
     * e 21 novembre 2026" (un solo giorno: "sabato 24 ottobre 2026"). Punto unico per mostrare date
     * di eventi/corsi (email, schermate) al posto di un "dal … al …" che nasconde i buchi.
     *
     * @param \Illuminate\Support\Collection<int, \Carbon\Carbon> $giorni ordinati
     */
    public static function formattaGiorni(\Illuminate\Support\Collection $giorni): string
    {
        $giorni = $giorni->values();
        if ($giorni->isEmpty()) {
            return '';
        }
        if ($giorni->count() === 1) {
            return $giorni->first()->translatedFormat('l j F Y');
        }

        // Raggruppa i giorni consecutivi in blocchi [primo, ultimo]
        $blocchi = [];
        foreach ($giorni as $giorno) {
            $ultimo = count($blocchi) - 1;
            if ($ultimo >= 0 && (int) abs($giorno->copy()->startOfDay()->diffInDays($blocchi[$ultimo][1]->copy()->startOfDay())) === 1) {
                $blocchi[$ultimo][1] = $giorno;
            } else {
                $blocchi[] = [$giorno, $giorno];
            }
        }

        $unAnno = $giorni->map->year->unique()->count() === 1;
        $testi  = array_map(function ($b) use ($unAnno) {
            [$da, $a] = $b;
            $anno = $unAnno ? '' : ' ' . $a->year;

            if ($da->isSameDay($a)) {
                return $da->translatedFormat('j F') . $anno;
            }
            if ($da->month === $a->month && $da->year === $a->year) {
                return $da->day . '–' . $a->translatedFormat('j F') . $anno;
            }

            return $da->translatedFormat('j F') . ' – ' . $a->translatedFormat('j F') . $anno;
        }, $blocchi);

        $ultimoTesto = array_pop($testi);
        $testo       = $testi ? implode(', ', $testi) . ' e ' . $ultimoTesto : $ultimoTesto;

        return $testo . ($unAnno ? ' ' . $giorni->first()->year : '');
    }

    /** Oggi (o `$quando`) è un giorno operativo? Usato per la finestra del check-in. */
    public function inGiornoOperativo(?\Carbon\Carbon $quando = null): bool
    {
        $giorno = ($quando ?? now())->format('Y-m-d');

        return $this->giorniOperativi()->contains(fn ($d) => $d->format('Y-m-d') === $giorno);
    }

    /** Ultimo giorno operativo passato (fine giornata): oltre, i badge/QR non sono più utilizzabili. */
    public function finestraOperativaScaduta(): bool
    {
        $ultimo = $this->giorniOperativi()->last();

        return $ultimo !== null && now()->gt($ultimo->copy()->endOfDay());
    }

    /**
     * Navigatore giorno per le pagine con frecce "giorno prima/dopo": porta `$data` (o oggi) sul
     * giorno operativo più vicino e restituisce quello precedente/successivo nell'elenco.
     *
     * @return array{data: ?string, prev: ?string, next: ?string}
     */
    public function navigazioneGiorno(?string $data = null): array
    {
        $date = $this->giorniOperativi()->map(fn ($d) => $d->format('Y-m-d'))->all();
        if (! $date) {
            return ['data' => $data, 'prev' => null, 'next' => null];
        }

        $target = strtotime($data ?? today()->format('Y-m-d'));
        $data   = collect($date)->sortBy(fn ($d) => abs(strtotime($d) - $target))->first();
        $i      = array_search($data, $date, true);

        return ['data' => $data, 'prev' => $date[$i - 1] ?? null, 'next' => $date[$i + 1] ?? null];
    }

    public function comitatoRegionale(): BelongsTo
    {
        return $this->belongsTo(ComitatoRegionale::class, 'comitato_regionale_id');
    }

    public function comitatoLocale(): BelongsTo
    {
        return $this->belongsTo(ComitatoLocale::class, 'comitato_locale_id');
    }

    public function corsi(): HasMany
    {
        return $this->hasMany(Corso::class, 'evento_id');
    }

    /** Operatori backend assegnati all'evento */
    public function operatori(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evento_operatore', 'evento_id', 'user_id')
                    ->withPivot('created_at');
    }

    /** Admin regionali di riferimento per l'evento */
    public function referenti(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evento_referenti', 'evento_id', 'user_id')
                    ->withTimestamps();
    }

    /** Partecipanti fisici registrati all'evento */
    public function partecipanti(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'socio_evento', 'evento_id', 'partecipante_id')
                    ->withPivot('ruolo', 'abilitato_app', 'created_at');
    }

    public function partecipazioniEvento(): HasMany
    {
        return $this->hasMany(SocioEvento::class, 'evento_id');
    }

    public function presenze(): HasMany
    {
        return $this->hasMany(Presenza::class, 'evento_id');
    }

    public function iscrizioniStaff(): HasMany
    {
        return $this->hasMany(IscrizioneStaff::class, 'evento_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(IscrizioneStaff::class, 'evento_id');
    }

    public function candidatureStaff(): HasMany
    {
        return $this->hasMany(CandidaturaStaff::class, 'evento_id');
    }

    public function questionari(): HasMany
    {
        return $this->hasMany(Questionario::class, 'evento_id');
    }

    public function camere(): HasMany
    {
        return $this->hasMany(CameraEvento::class, 'evento_id')->orderBy('data')->orderBy('nome');
    }

    public function ospiti(): HasMany
    {
        return $this->hasMany(OspiteEvento::class, 'evento_id');
    }

    public function hasDatiCorrelati(): bool
    {
        return $this->corsi()
            ->whereHas('iscrizioni', fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->whereHas('rispostaPreregistrazione')
                       ->orWhereHas('rispostaCheckin')
                       ->orWhereHas('emailInvii')
                )
            )
            ->exists();
    }

    /**
     * "Testo comunicazione / Note per l'app" conta come contenuto valido solo con almeno
     * questa lunghezza di testo reale (tag HTML del RichEditor esclusi) — un placeholder di
     * prova ("x", ".", poche lettere a caso) non deve abilitare l'invio della comunicazione
     * evento. Unico punto di verità per GestioneEvento (iscritti) e GestioneStaff (staff):
     * prima ciascuna pagina aveva la propria copia incompleta (solo `filled(note_app)`, mai
     * i documenti).
     */
    private const LUNGHEZZA_MINIMA_TESTO_COMUNICAZIONE = 10;

    /** Vero se c'è testo sufficiente in note_app, o almeno un documento allegato. */
    public function hasComunicazioneValida(): bool
    {
        $testo = trim(html_entity_decode(strip_tags($this->note_app ?? ''), ENT_QUOTES));

        return mb_strlen($testo) >= self::LUNGHEZZA_MINIMA_TESTO_COMUNICAZIONE
            || $this->getMedia('documenti')->isNotEmpty();
    }

    /** Documenti e badge tramite Spatie MediaLibrary */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documenti');
        $this->addMediaCollection('badge_sfondo')->singleFile();
        $this->addMediaCollection('intestazione_app')->singleFile();
    }
}
