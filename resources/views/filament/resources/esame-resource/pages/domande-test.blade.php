<x-filament-panels::page>
    @php
        $esame  = $this->record;
        $d      = $this->dati();
        $c      = $d['config'];
        $bordo  = 'rgba(128,128,128,.35)';
        $verde  = 'rgba(34,197,94,.25)';
        $lettere = ['A', 'B', 'C', 'D'];
        $modificabile = $d['puoPreparare'] || $d['puoRefusi'];
    @endphp

    @if(! $esame->modello_snapshot && $esame->isBozza())
        <x-filament::section>
            <p style="margin:0;">Puoi preparare domande e punteggio anche prima di «Attiva esame»; il test si avvia per i discenti solo a esame in corso.</p>
        </x-filament::section>
    @endif

    <div style="font-size:13px; color:#6b7280;">
        @if($d['stato'] === 'bozza')
            Domande e punteggio si modificano liberamente. L'avvio del test per i discenti e i risultati sono in «Valutazioni › Test scritto».
        @else
            Test già avviato: domande e punteggio sono congelati, si possono correggere solo i refusi nei testi.
        @endif
    </div>

    {{-- Punteggio --}}
    <x-filament::section heading="Punteggio e ordine">
        <div style="display:flex; flex-wrap:wrap; gap:10px 24px; align-items:center; justify-content:space-between;">
            <div style="font-size:14px;">
                @if($c['penalita'])
                    Penalità <strong>attive</strong>: corretta <strong>+{{ \App\Esami\TestScritto::mostraDecimale($c['corretta']) }}</strong> ·
                    sbagliata <strong>−{{ \App\Esami\TestScritto::mostraDecimale($c['errata']) }}</strong> ·
                    non data <strong>−{{ \App\Esami\TestScritto::mostraDecimale($c['vuota']) }}</strong> — le risposte si possono lasciare vuote.
                @else
                    Penalità non attive: <strong>1 punto</strong> per risposta giusta, <strong>tutte le risposte obbligatorie</strong>.
                @endif
                <div style="font-size:12px;">Ordine al discente: <strong>{{ $c['mescola'] ? 'mescolato a ogni visualizzazione (domande e risposte)' : 'come impostato' }}</strong></div>
                <div style="font-size:12px;">Punteggio massimo: <strong>{{ \App\Esami\Formato::unDecimale($d['massimo']) }}</strong> ({{ count($d['domande']) }} domande) · il totale non scende mai sotto 0.</div>
            </div>
            @if($d['puoPreparare'])
                <x-filament::button size="sm" color="info" type="button" wire:click="mountAction('configurazione')">Imposta il punteggio</x-filament::button>
            @else
                <span style="font-size:12px;">{{ $d['motivoModifica'] ?? 'Punteggio congelato dall\'avvio.' }}</span>
            @endif
        </div>
    </x-filament::section>

    @if($d['puoPreparare'])
        <x-filament::section heading="Importare le domande da un file Word o PDF" description="Dai il file (domande, risposte e correttore) a un assistente insieme a questo prompt, poi carica il JSON che ti restituisce con «Importa il test da JSON». L'import sostituisce tutte le domande." collapsible collapsed>
            <div x-data="{ copiato: false }">
                <textarea readonly rows="7" x-ref="prompt" style="width:100%; font-size:13px; border:1px solid rgba(128,128,128,.35); border-radius:8px; padding:8px; background:transparent;">{{ \App\Esami\ImportaTest::PROMPT }}</textarea>
                <div style="margin-top:8px; display:flex; gap:10px; align-items:center;">
                    <x-filament::button size="sm" color="info" type="button"
                        x-on:click="navigator.clipboard.writeText($refs.prompt.value).then(() => { copiato = true; setTimeout(() => copiato = false, 2000) })">Copia il prompt</x-filament::button>
                    <span x-show="copiato" x-cloak style="font-size:13px;">Copiato.</span>
                </div>
                <p style="margin:10px 0 0; font-size:12px; color:#6b7280;">Esempio del formato: <code>{{ \App\Esami\ImportaTest::ESEMPIO }}</code></p>
            </div>
        </x-filament::section>
    @endif

    {{-- Domande --}}
    <x-filament::section heading="Domande ({{ count($d['domande']) }})" description="La risposta corretta è in verde. Il discente non la vede mai.">
        @if($modificabile && $d['puoPreparare'])
            <div style="margin-bottom:10px; display:flex; flex-wrap:wrap; gap:8px;">
                <x-filament::button size="sm" color="info" type="button" wire:click="mountAction('domanda')">Aggiungi domanda</x-filament::button>
                <x-filament::button size="sm" color="info" type="button" wire:click="mountAction('importaTest')">Importa il test da JSON</x-filament::button>
            </div>
        @endif
        @forelse($d['domande'] as $i => $q)
            <div style="border:1px solid {{ $bordo }}; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
                <div style="display:flex; flex-wrap:wrap; gap:6px 12px; justify-content:space-between; align-items:flex-start;">
                    <div style="font-weight:600; overflow-wrap:anywhere; white-space:pre-line; flex:1 1 300px;">{{ $i + 1 }}. {{ $q['testo'] }}</div>
                    @if($modificabile)
                        <div style="display:flex; gap:6px;">
                            <x-filament::button size="xs" color="info" type="button" wire:click="mountAction('domanda', {{ \Illuminate\Support\Js::from(['domanda_id' => $q['id']]) }})">
                                {{ $d['puoPreparare'] ? 'Modifica' : 'Correggi refusi' }}
                            </x-filament::button>
                            @if($d['puoPreparare'])
                                <x-filament::button size="xs" color="danger" type="button" wire:click="mountAction('eliminaDomanda', {{ \Illuminate\Support\Js::from(['domanda_id' => $q['id']]) }})">Elimina</x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>
                @foreach($q['opzioni'] as $k => $testo)
                    <div style="margin-top:4px; padding:3px 8px; border-radius:6px; overflow-wrap:anywhere; white-space:pre-line; font-size:14px; {{ $k === $q['corretta'] ? 'background:' . $verde . '; font-weight:600;' : '' }}">
                        {{ $lettere[$k] ?? $k + 1 }}) {{ $testo }}
                    </div>
                @endforeach
            </div>
        @empty
            <p style="margin:0;">Nessuna domanda ancora.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
