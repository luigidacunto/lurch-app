@extends('app.layout')

@section('title', 'Itinere')

@push('styles')
@include('app.partials.esami-stili')
<style>
    a.disc-card {
        display: block; color: inherit; text-decoration: none; min-height: 56px;
        background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px;
        border-left: 4px solid #C8102E; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    a.disc-card[hidden] { display: none; }
    .cerca {
        width: 100%; min-height: 48px; padding: 0 14px; margin-bottom: 12px; box-sizing: border-box;
        border: 2px solid #e5e7eb; border-radius: 10px; font-size: 16px; background: #fff;
    }
    .disc-nome { font-size: 15px; font-weight: 700; color: #111827; overflow-wrap: anywhere; }
    .disc-stato { font-size: 13px; color: #6b7280; margin-top: 4px; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Schede itinere</p>
<p class="page-sub">Una scheda per discente, che aggiorni nel tempo</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame.</div>

@if($righe->isNotEmpty())
    <input type="search" id="cerca" class="cerca" placeholder="Cerca per nome o cognome" autocomplete="off" aria-label="Cerca discente">
@endif

@forelse($righe as $r)
    <a class="disc-card" data-nome="{{ $r['socio']->cognome }} {{ $r['socio']->nome }}" href="{{ route('app.esame.itinere.scheda', [$esame, $r['socio']]) }}">
        <div class="disc-nome">{{ $r['socio']->cognome }} {{ $r['socio']->nome }}</div>
        <div class="disc-stato">
            @if($r['voti'] > 0)
                {{ $r['voti'] }} voti su {{ $nVoci }} · Aree {{ $r['riep']['aree_valutate'] }} su {{ $r['riep']['n_aree'] }} · Totale {{ $r['riep']['totale'] ?? '—' }} su {{ $r['riep']['massimo'] }}
            @else
                Da fare
            @endif
        </div>
    </a>
@empty
    <div class="vuoto">Non hai schede da compilare.</div>
@endforelse
<div class="vuoto" id="nessun-risultato" hidden>Nessun discente trovato.</div>
@endsection

@push('scripts')
<script>
(function () {
    var campo = document.getElementById('cerca');
    if (!campo) return;
    var card = document.querySelectorAll('.disc-card');
    var vuoto = document.getElementById('nessun-risultato');
    // Minuscole e senza accenti, così "ro" trova anche "Rò"; ogni parola scritta deve comparire nel nome.
    function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    campo.addEventListener('input', function () {
        var parole = norm(campo.value).split(/\s+/).filter(Boolean);
        var visibili = 0;
        card.forEach(function (c) {
            var nome = norm(c.dataset.nome);
            var ok = parole.every(function (p) { return nome.indexOf(p) !== -1; });
            c.hidden = !ok;
            if (ok) visibili++;
        });
        vuoto.hidden = visibili > 0;
    });
})();
</script>
@endpush
