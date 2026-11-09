{{-- Legenda del LAP per prova (ProiezioneEsame::soglieLap). Stili in linea: la usano sia la PWA sia il pannello.
     $tooltip = true (solo pannello): al passaggio del mouse la descrizione a parole della fascia. Niente @if dentro i tag: Livewire ci inserirebbe dei commenti. --}}
<div style="background:rgba(128,128,128,.08); border:1px solid rgba(128,128,128,.3); border-radius:10px; padding:10px 12px; margin-bottom:12px; font-size:13px; line-height:1.5;">
    <div style="font-weight:700; margin-bottom:4px;">LAP {{ $soglie['lap'] }}% — idoneo da {{ $soglie['totale'] }} su 100</div>
    <div style="display:flex; flex-wrap:wrap; gap:4px 18px;">
        @foreach($soglie['prove'] as $p)
            <span>{{ $p['nome'] }}: <strong>{{ $p['centesimi'] }}</strong> su {{ $p['peso'] }}@if($p['punti'] !== null) <span style="opacity:.7;">({{ $p['punti'] }} su {{ $p['massimo'] }} punti)</span>@endif</span>
        @endforeach
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:4px 6px; margin-top:6px; align-items:center;">
        <span style="opacity:.7;">Colori, in % del massimo di ogni prova:</span>
        @foreach($soglie['fasce'] as $f)
            @php $titolo = ($tooltip ?? false) ? ' title="'.e($f['descrizione']).'"' : ''; @endphp
            <span{!! $titolo !!} style="display:inline-block; padding:0 8px; border-radius:6px; color:#111827; background:{{ $f['colore'] }};">{{ $f['nome'] }} {{ $f['testo'] }}</span>
        @endforeach
    </div>
</div>
