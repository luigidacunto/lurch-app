@php
    $primaIncompleta = null;
    foreach ($struttura['competenze'] as $i => $c) {
        foreach ($c['voci'] as $v) {
            if (! isset($valori[$v['codice']])) { $primaIncompleta = $i; break 2; }
        }
    }
    $lettere = ['rosso' => 'R', 'giallo' => 'G', 'verde' => 'V'];
@endphp
<p class="legenda">
    @foreach($struttura['scala'] as $chiave => $l)
        <strong>{{ $l['nome'] }}</strong> ({{ $l['punti'] }}): {{ $l['descrizione'] }}.@if(! $loop->last)<br>@endif
    @endforeach
</p>

@foreach($struttura['competenze'] as $i => $comp)
    @php
        $fatte = 0;
        foreach ($comp['voci'] as $v) { if (isset($valori[$v['codice']])) { $fatte++; } }
    @endphp
    <details class="comp" data-competenza="{{ $i }}" @if($i === ($primaIncompleta ?? 0)) open @endif>
        <summary>
            <span>{{ $comp['nome'] }}</span>
            <span class="comp-conta {{ $fatte === count($comp['voci']) ? 'ok' : '' }}" data-conta-comp="{{ $i }}">{{ $fatte }}/{{ count($comp['voci']) }}</span>
        </summary>
        <div class="comp-corpo">
            @if(! empty($comp['obiettivo']))<p class="comp-obiettivo">{{ $comp['obiettivo'] }}</p>@endif
            @foreach($comp['voci'] as $voce)
                <div class="voce" data-voce-box="{{ $voce['codice'] }}" data-comp="{{ $i }}">
                    <p class="voce-testo">{{ $voce['testo'] }}</p>
                    <div class="semaforo">
                        @foreach($struttura['scala'] as $chiave => $l)
                            <button type="button" data-voce="{{ $voce['codice'] }}" data-livello="{{ $chiave }}"
                                    aria-label="{{ $l['nome'] }}" aria-pressed="{{ ($valori[$voce['codice']] ?? null) === $chiave ? 'true' : 'false' }}"
                                    class="{{ ($valori[$voce['codice']] ?? null) === $chiave ? 'sel' : '' }}"
                                    @disabled(! $modificabile)>{{ $lettere[$chiave] ?? strtoupper(substr($chiave, 0, 1)) }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </details>
@endforeach

