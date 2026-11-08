<x-filament-panels::page>
    @php
        $d = $this->dati();
        $bordo = 'rgba(128,128,128,.35)';
        $grigio = 'rgba(128,128,128,.10)';
        $lettere = ['A', 'B', 'C', 'D'];
    @endphp

    <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px 14px; font-size:14px;">
        <strong>Test su carta di {{ $this->discente()->cognome }} {{ $this->discente()->nome }}</strong>
        <div style="font-size:13px; white-space:pre-line; overflow-wrap:anywhere;">Motivo dichiarato: {{ $d['motivo'] }}</div>
        <div style="font-size:13px; margin-top:4px;">Le domande sono nello stesso ordine del foglio stampato. Ogni scelta si salva da sola; premi «Invia» quando hai inserito tutto.</div>
        <div style="font-size:12px; margin-top:4px;">{{ $d['regola'] }}</div>
    </div>

    @if($d['inviata'])
        <div style="border:1px solid {{ $bordo }}; border-left:5px solid rgb(34 197 94); border-radius:8px; padding:10px 14px; font-size:14px;">
            Test inviato il {{ $d['consegna']->consegnato_il->format('d/m/Y H:i') }} — cartaceo, inserito da {{ $d['consegna']->inseritoDa?->nome_completo ?? 'il direttore' }}. Sola lettura.
        </div>
    @elseif($d['motivoOff'])
        <div style="border:1px solid {{ $bordo }}; border-radius:8px; padding:10px 14px; font-size:14px;">{{ $d['motivoOff'] }}</div>
    @endif

    @foreach($d['domande'] as $i => $q)
        <x-filament::section>
            <div style="font-weight:600; margin-bottom:6px; overflow-wrap:anywhere; white-space:pre-line;">{{ $i + 1 }}. {{ $q['testo'] }}</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:6px;">
                @foreach($q['opzioni'] as $k => $testo)
                    @php($sel = ($d['scelte'][$q['id']] ?? null) === $k)
                    <button type="button" @if($d['modificabile']) wire:click="scegli({{ $q['id'] }}, {{ $k }})" @else disabled @endif
                            style="text-align:left; padding:8px 10px; border-radius:8px; border:2px solid {{ $sel ? 'rgb(29 78 216)' : $bordo }}; background:{{ $sel ? 'rgba(29,78,216,.12)' : 'transparent' }}; color:inherit; cursor:{{ $d['modificabile'] ? 'pointer' : 'default' }}; overflow-wrap:anywhere; white-space:pre-line;">
                        <strong>{{ $lettere[$k] ?? $k + 1 }})</strong> {{ $testo }}
                    </button>
                @endforeach
            </div>
            @if($d['modificabile'] && ! $d['obbligatorie'] && isset($d['scelte'][$q['id']]))
                <div style="font-size:11px; margin-top:4px;">Tocca di nuovo l'opzione scelta per lasciare la domanda senza risposta.</div>
            @endif
        </x-filament::section>
    @endforeach

    <div style="display:flex; flex-wrap:wrap; gap:10px 20px; align-items:center; justify-content:space-between; position:sticky; bottom:0; padding:10px 0;">
        <strong>Risposte inserite {{ $d['date'] }} su {{ $d['totale'] }}</strong>
        <div style="display:flex; gap:8px; align-items:center;">
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\EsameResource::getUrl('test-scritto', ['record' => $this->record])">Torna al test scritto</x-filament::button>
            @unless($d['inviata'])
                <x-filament::button color="success" type="button" :disabled="! $d['puoInviare']"
                    :tooltip="$d['motivoOff'] ?? (! $d['puoInviare'] ? 'Inserisci tutte le risposte per poter inviare.' : null)"
                    wire:click="mountAction('invia')">Invia il test</x-filament::button>
            @endunless
        </div>
    </div>
</x-filament-panels::page>
