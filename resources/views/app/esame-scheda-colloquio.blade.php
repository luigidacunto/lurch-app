@extends('app.layout')

@section('title', $socio->cognome.' · Colloquio')

@php
    $urlVoto = route('app.esame.colloquio.voto', [$esame, $socio]);
    $urlImpressione = route('app.esame.colloquio.impressione', [$esame, $socio]);
    $colori = \App\Esami\ColoreScala::per(array_column($struttura['livelli'], 'valore'), $struttura['max'], $esame->lap);
    $descrittori = [];
    foreach ($struttura['livelli'] as $l) { $descrittori[(string) $l['valore']] = $l['descrittore']; }
@endphp

@push('styles')
@include('app.partials.esami-stili')
<style>
    .scala { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 14px; }
    .scala button {
        min-height: 56px; border-radius: 10px; border: 2px solid #e5e7eb; background: #fff;
        font-size: 18px; font-weight: 800; color: #6b7280; cursor: pointer; touch-action: manipulation;
    }
    .scala button.sel { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
    .scala button:disabled { cursor: default; opacity: .7; }
    .scheda-box { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .scheda-box h3 { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
    .livello-scelto { font-size: 13px; color: #374151; line-height: 1.4; overflow-wrap: anywhere; min-height: 20px; }
    textarea.impressione {
        width: 100%; min-height: 110px; box-sizing: border-box; padding: 10px 12px; font-size: 16px; font-family: inherit;
        border: 2px solid #e5e7eb; border-radius: 10px; resize: vertical;
    }
    textarea.impressione:disabled { background: #f9fafb; }
    .conta-caratteri { font-size: 12px; color: #6b7280; text-align: right; margin-top: 4px; }
    details.legenda { background: #fff; border-radius: 12px; margin-bottom: 14px; padding: 0 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    details.legenda > summary { cursor: pointer; padding: 12px 0; font-size: 13px; font-weight: 700; color: #4b5563; }
    .liv { font-size: 13px; color: #374151; padding: 6px 8px; margin-bottom: 3px; border-radius: 6px; overflow-wrap: anywhere; line-height: 1.4; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame.colloquio', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Colloquio
</a>
<p class="page-title">{{ $socio->cognome }} {{ $socio->nome }}</p>
<p class="page-sub">Colloquio · scheda personale</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame.</div>
@if(! $modificabile)<div class="avviso-sola-lettura">La scheda si può solo consultare: l'esame non è in corso oppure la prova finale è chiusa.</div>@endif

<details class="legenda" @if($voto === null) open @endif>
    <summary>Descrittori dei voti</summary>
    @foreach($struttura['livelli'] as $l)
        <div class="liv" style="background: {{ $colori[(string) $l['valore']] }}"><strong>{{ $l['valore'] }}</strong> · {{ $l['descrittore'] }}</div>
    @endforeach
</details>

<div class="scheda-box">
    <h3>Voto ({{ $struttura['min'] }}–{{ $struttura['max'] }}) · obbligatorio</h3>
    <div class="scala" id="scala">
        @foreach($struttura['livelli'] as $l)
            <button type="button" data-voto="{{ $l['valore'] }}" aria-label="Voto {{ $l['valore'] }}"
                    aria-pressed="{{ $voto === (int) $l['valore'] ? 'true' : 'false' }}"
                    class="{{ $voto === (int) $l['valore'] ? 'sel' : '' }}" @disabled(! $modificabile)>{{ $l['valore'] }}</button>
        @endforeach
    </div>
    <div class="livello-scelto" id="livello-scelto"></div>
</div>

<div class="scheda-box">
    <h3>Impressione · facoltativa</h3>
    <textarea class="impressione" id="impressione" maxlength="{{ $impressioneMax }}" placeholder="Scegli prima il voto, poi scrivi la tua impressione"
              @disabled(! $modificabile || $voto === null)>{{ $impressione }}</textarea>
    <div class="conta-caratteri"><span id="conta">{{ mb_strlen((string) $impressione) }}</span>/{{ $impressioneMax }}</div>
</div>

@if(! empty($struttura['domande']))
<details class="legenda">
    <summary>Domande guida</summary>
    @foreach($struttura['domande'] as $d)
        <div class="liv">{{ $d }}</div>
    @endforeach
</details>
@endif

<div class="barra">
    <div class="barra-riga">
        <span id="riepilogo-voto">{{ $voto !== null ? 'Il tuo voto: '.$voto : 'Nessun voto' }}</span>
        <span id="stato-salvataggio" class="barra-stato">{{ $modificabile ? 'Ogni modifica si salva da sola' : '' }}</span>
    </div>
</div>
@endsection

@if($modificabile)
@push('scripts')
<script>
(function () {
    var urlVoto = {!! json_encode($urlVoto) !!};
    var urlImpressione = {!! json_encode($urlImpressione) !!};
    var token = {!! json_encode(csrf_token()) !!};
    var livelli = {!! json_encode($descrittori) !!};
    var stato = document.getElementById('stato-salvataggio');
    var riepilogo = document.getElementById('riepilogo-voto');
    var testo = document.getElementById('impressione');
    var conta = document.getElementById('conta');
    var scelto = document.getElementById('livello-scelto');
    var timer = null;

    function mostraLivello(v) { scelto.textContent = v && livelli[v] ? livelli[v] : ''; }
    function segna(v) {
        document.querySelectorAll('#scala button').forEach(function (b) {
            var sel = b.dataset.voto === String(v);
            b.classList.toggle('sel', sel);
            b.setAttribute('aria-pressed', sel ? 'true' : 'false');
        });
    }
    function corrente() {
        var b = document.querySelector('#scala button.sel');
        return b ? b.dataset.voto : null;
    }
    function invia(url, corpo, ok, ko) {
        stato.className = 'barra-stato';
        stato.textContent = 'Salvo…';
        fetch(url, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify(corpo),
            credentials: 'same-origin'
        }).then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        }).then(function (d) { stato.textContent = 'Salvato'; ok(d); })
          .catch(function () { stato.className = 'barra-stato errore'; stato.textContent = 'Non salvato — riprova'; if (ko) ko(); });
    }

    mostraLivello(corrente());
    document.querySelectorAll('#scala button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var precedente = corrente();
            // Il voto è obbligatorio: si può cambiare ma non togliere.
            if (precedente === btn.dataset.voto) return;
            segna(btn.dataset.voto);
            mostraLivello(btn.dataset.voto);
            invia(urlVoto, {voto: parseInt(btn.dataset.voto, 10)}, function () {
                riepilogo.textContent = 'Il tuo voto: ' + btn.dataset.voto;
                testo.disabled = false;
            }, function () { segna(precedente); mostraLivello(precedente); });
        });
    });
    function salvaTesto() {
        invia(urlImpressione, {impressione: testo.value}, function () {});
    }
    testo.addEventListener('input', function () {
        conta.textContent = testo.value.length;
        clearTimeout(timer);
        timer = setTimeout(salvaTesto, 800);
    });
    testo.addEventListener('blur', function () { clearTimeout(timer); salvaTesto(); });
})();
</script>
@endpush
@endif
