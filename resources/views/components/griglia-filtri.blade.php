{{--
    Filtro client-side per le liste "hand-rolled" delle pagine hub (Gestione staff,
    Gestione corso): la lista è già tutta nel DOM (nessuna paginazione), quindi si
    filtra in JS con Alpine senza round-trip Livewire.

    Uso:
        <x-griglia-filtri :totale="$staff->count()" :selects="[
            ['key' => 'ruolo', 'placeholder' => 'Tutti i ruoli', 'options' => ['docenza' => 'Solo docenza', 'logistica' => 'Solo logistica']],
            ['key' => 'form',  'placeholder' => 'Form: tutti',    'options' => ['compilato' => 'Compilato', 'mancante' => 'Non compilato']],
        ]">
            @foreach(... as $riga)
                <div data-row data-search="{{ $search }}" data-ruolo="{{ $ruolo }}" data-form="{{ $form }}"> ... </div>
            @endforeach
        </x-griglia-filtri>

    Ogni riga dello slot: attributo `data-row` + `data-search` (stringa minuscola su
    cui cerca il testo libero) + un `data-<key>` per ogni select. Il valore di un
    `data-<key>` può contenere più token separati da spazio (es. "docenza logistica"):
    la select combacia se uno dei token è uguale all'opzione scelta.
--}}
@props(['selects' => [], 'totale' => 0, 'vuoto' => 'Nessun elemento corrisponde ai filtri.'])

<div
    x-data="{
        q: '',
        f: { @foreach($selects as $s)'{{ $s['key'] }}': '', @endforeach },
        match(el) {
            if (this.q && !(el.dataset.search || '').includes(this.q.toLowerCase().trim())) return false;
            for (const k in this.f) {
                if (!this.f[k]) continue;
                if (!(el.dataset[k] || '').split(' ').includes(this.f[k])) return false;
            }
            return true;
        },
        apply() {
            let n = 0;
            this.$refs.lista.querySelectorAll('[data-row]').forEach(el => {
                const ok = this.match(el);
                el.hidden = !ok;
                if (ok) n++;
            });
            this.$refs.vuoto.hidden = n !== 0;
            this.$refs.conteggio.textContent = n;
        },
        reset() { this.q = ''; for (const k in this.f) this.f[k] = ''; this.apply(); }
    }"
    x-init="$watch('q', () => apply()); $watch('f', () => apply())"
>
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:16px;">
        <input type="text" x-model.debounce.200ms="q"
               placeholder="Nome, cognome, CF, email, comitato…"
               style="flex:1; min-width:220px; font-size:13px; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
        @foreach($selects as $s)
            <select x-model="f.{{ $s['key'] }}"
                    style="font-size:13px; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
                <option value="">{{ $s['placeholder'] }}</option>
                @foreach($s['options'] as $val => $lbl)
                    <option value="{{ $val }}">{{ $lbl }}</option>
                @endforeach
            </select>
        @endforeach
        <x-filament::button color="gray" size="sm" icon="heroicon-o-x-mark"
                            x-on:click="reset()" tag="button" type="button">Azzera filtri</x-filament::button>
        <span style="font-size:11px; color:#9ca3af;">
            <span x-ref="conteggio">{{ $totale }}</span> di {{ $totale }}
        </span>
    </div>

    <div x-ref="lista" style="display:flex; flex-direction:column; gap:0;">
        {{ $slot }}
    </div>

    <div x-ref="vuoto" hidden style="text-align:center; padding:32px; font-size:13px; color:#9ca3af;">
        {{ $vuoto }}
    </div>
</div>
