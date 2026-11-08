@extends('app.layout')

@section('title', $socio->cognome.' · Itinere')

@php
    $colori = \App\Esami\ColoreScala::per(array_column($struttura['livelli'], 'valore'), $struttura['max'], $esame->lap);
    $nVoci = count($struttura['codici']);
    $nAree = count($struttura['aree']);
    $primaSenzaVoti = null;
    foreach ($riepilogo['aree'] as $i => $a) {
        if ($a['contati'] === 0) { $primaSenzaVoti = $i; break; }
    }
    $urlVoto = route('app.esame.itinere.voto', [$esame, $socio]);
@endphp

@push('styles')
@include('app.partials.esami-stili')
<style>
    details.area { background: #fff; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    details.area > summary {
        list-style: none; cursor: pointer; padding: 14px 16px; min-height: 52px;
        display: flex; justify-content: space-between; align-items: center; gap: 8px;
        font-size: 15px; font-weight: 700; color: #111827;
    }
    details.area > summary::-webkit-details-marker { display: none; }
    .area-conta { font-size: 12px; font-weight: 700; color: #6b7280; white-space: nowrap; }
    .area-conta.ok { color: #047857; }
    .area-corpo { padding: 0 16px 12px; }
    .area-testo { font-size: 13px; color: #6b7280; margin-bottom: 8px; overflow-wrap: anywhere; line-height: 1.4; }
    .area-avviso { background: #fffbeb; color: #92400e; border-radius: 8px; padding: 8px 10px; font-size: 13px; margin-bottom: 8px; }
    .voce { padding: 12px 0; border-top: 1px solid #f3f4f6; }
    .voce-testo { font-size: 14px; color: #111827; margin-bottom: 10px; overflow-wrap: anywhere; line-height: 1.4; }
    .scala { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
    .scala button {
        min-height: 52px; border-radius: 10px; border: 2px solid #e5e7eb; background: #fff;
        font-size: 17px; font-weight: 800; color: #6b7280; cursor: pointer; touch-action: manipulation;
    }
    .scala button.sel { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
    .scala button:disabled { cursor: default; opacity: .7; }
    details.legenda { background: #fff; border-radius: 12px; margin-bottom: 14px; padding: 0 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    details.legenda > summary { cursor: pointer; padding: 12px 0; font-size: 13px; font-weight: 700; color: #4b5563; }
    .liv { font-size: 13px; color: #374151; padding: 6px 8px; margin-bottom: 3px; border-radius: 6px; overflow-wrap: anywhere; line-height: 1.4; }
    .liv small { display: block; color: #6b7280; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame.itinere', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Schede itinere
</a>
<p class="page-title">{{ $socio->cognome }} {{ $socio->nome }}</p>
<p class="page-sub">Valutazione in itinere</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame.</div>
@if(! $modificabile)<div class="avviso-sola-lettura">L'esame non è in corso: la scheda si può solo consultare.</div>@endif

<details class="legenda">
    <summary>Scala dei voti ({{ $struttura['min'] }}–{{ $struttura['max'] }})</summary>
    @foreach($struttura['livelli'] as $l)
        <div class="liv" style="background: {{ $colori[(string) $l['valore']] }}"><strong>{{ $l['valore'] }}</strong> · {{ $l['declinazione'] }}@if(! empty($l['specifica']))<small>{{ $l['specifica'] }}</small>@endif</div>
    @endforeach
</details>

@foreach($struttura['aree'] as $i => $area)
    @php $r = $riepilogo['aree'][$i]; @endphp
    <details class="area" data-area="{{ $area['codice'] }}" @if($i === ($primaSenzaVoti ?? 0)) open @endif>
        <summary>
            <span>{{ $area['nome'] }}</span>
            <span class="area-conta {{ $r['contati'] > 0 ? 'ok' : '' }}">
                Media <strong data-media-area="{{ $area['codice'] }}">{{ $r['media'] ?? '—' }}</strong>
                · <span data-conta-area="{{ $area['codice'] }}">{{ $r['contati'] }}/{{ count($area['voci']) }}</span>
            </span>
        </summary>
        <div class="area-corpo">
            @if(! empty($area['testo']))<p class="area-testo">{{ $area['testo'] }}</p>@endif
            @if($area['codice'] === \App\Esami\RiepilogoItinere::AREA_SPECCHIO_LABORATORI)<p class="area-testo"><em>{{ \App\Esami\RiepilogoItinere::NOTA_AREA_SPECCHIO }}</em></p>@endif
            <div class="area-avviso" data-avviso-area="{{ $area['codice'] }}" @if($r['contati'] > 0) hidden @endif>Nessun voto in quest'area.</div>
            @foreach($area['voci'] as $voce)
                <div class="voce" data-voce-box="{{ $voce['codice'] }}" data-area="{{ $area['codice'] }}">
                    <p class="voce-testo">{{ $voce['testo'] }}</p>
                    <div class="scala">
                        @for($n = $struttura['min']; $n <= $struttura['max']; $n++)
                            <button type="button" data-voce="{{ $voce['codice'] }}" data-voto="{{ $n }}"
                                    aria-label="Voto {{ $n }}" aria-pressed="{{ ($voti[$voce['codice']] ?? null) === $n ? 'true' : 'false' }}"
                                    class="{{ ($voti[$voce['codice']] ?? null) === $n ? 'sel' : '' }}"
                                    @disabled(! $modificabile)>{{ $n }}</button>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    </details>
@endforeach

@include('app.partials.esami-annotazione', ['urlAnnotazione' => route('app.esame.itinere.annotazione', [$esame, $socio]), 'chiLegge' => 'li legge anche il direttore'])

<div class="barra">
    <div class="barra-riga">
        <span id="contatore">{{ $riepilogo['voti'] }}/{{ $nVoci }} voti</span>
        <span id="stato-salvataggio" class="barra-stato">{{ $modificabile ? 'Ogni tocco si salva da solo' : '' }}</span>
    </div>
    <div class="totale">
        Totale della tua scheda: <strong id="totale-valore">{{ $riepilogo['totale'] ?? '—' }} su {{ $riepilogo['massimo'] }}</strong>
        · Aree compilate: <strong id="aree-valore">{{ $riepilogo['aree_valutate'] }} su {{ $riepilogo['n_aree'] }}</strong>
    </div>
</div>
@endsection

@if($modificabile)
@push('scripts')
<script>
(function () {
    var url = {!! json_encode($urlVoto) !!};
    var token = {!! json_encode(csrf_token()) !!};
    var nVoci = {{ $nVoci }};
    var stato = document.getElementById('stato-salvataggio');
    var contatore = document.getElementById('contatore');
    var totaleValore = document.getElementById('totale-valore');
    var areeValore = document.getElementById('aree-valore');

    function segna(box, voto) {
        box.querySelectorAll('button').forEach(function (b) {
            var sel = b.dataset.voto === String(voto);
            b.classList.toggle('sel', sel);
            b.setAttribute('aria-pressed', sel ? 'true' : 'false');
        });
    }
    function corrente(box) {
        var b = box.querySelector('button.sel');
        return b ? b.dataset.voto : null;
    }
    // Il server calcola medie e totale (motore condiviso): qui si scrive solo ciò che risponde.
    function aggiorna(d) {
        contatore.textContent = d.voti + '/' + nVoci + ' voti';
        d.aree.forEach(function (a) {
            var conta = document.querySelector('[data-conta-area="' + a.codice + '"]');
            var n = document.querySelectorAll('[data-voce-box][data-area="' + a.codice + '"]').length;
            conta.textContent = a.contati + '/' + n;
            conta.parentElement.classList.toggle('ok', a.contati > 0);
            document.querySelector('[data-avviso-area="' + a.codice + '"]').hidden = a.contati > 0;
            document.querySelector('[data-media-area="' + a.codice + '"]').textContent = a.media === null ? '—' : a.media;
        });
        totaleValore.textContent = (d.totale === null ? '—' : d.totale) + ' su ' + d.massimo;
        areeValore.textContent = d.aree_valutate + ' su ' + d.n_aree;
    }

    document.querySelectorAll('.scala button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('.voce');
            var precedente = corrente(box);
            // Ritoccare il voto già selezionato lo toglie (le voci sono facoltative).
            var nuovo = precedente === btn.dataset.voto ? null : parseInt(btn.dataset.voto, 10);
            segna(box, nuovo);
            stato.className = 'barra-stato';
            stato.textContent = 'Salvo…';
            fetch(url, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                body: JSON.stringify({voce: btn.dataset.voce, voto: nuovo}),
                credentials: 'same-origin'
            }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.json();
            }).then(function (d) {
                stato.textContent = 'Salvato';
                aggiorna(d);
            }).catch(function () {
                segna(box, precedente);
                stato.className = 'barra-stato errore';
                stato.textContent = 'Non salvato — riprova';
            });
        });
    });
})();
</script>
@endpush
@endif
