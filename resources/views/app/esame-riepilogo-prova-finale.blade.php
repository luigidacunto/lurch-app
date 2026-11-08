@extends('app.layout')

@section('title', 'Riepilogo prova finale')

@push('styles')
@include('app.partials.esami-stili')
<style>
    .avviso-finale { background: #eef2ff; color: #3730a3; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; border-left: 4px solid #4f46e5; }
    .cerca {
        width: 100%; min-height: 48px; padding: 0 14px; margin-bottom: 12px; box-sizing: border-box;
        border: 2px solid #e5e7eb; border-radius: 10px; font-size: 16px; background: #fff;
    }
    details.disc { background: #fff; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); border-left: 4px solid #C8102E; }
    details.disc[hidden] { display: none; }
    details.disc > summary { list-style: none; cursor: pointer; padding: 14px 16px; min-height: 48px; }
    details.disc > summary::-webkit-details-marker { display: none; }
    .disc-nome { font-size: 15px; font-weight: 700; color: #111827; overflow-wrap: anywhere; }
    .disc-stato { font-size: 13px; color: #6b7280; margin-top: 4px; }
    .prova { border-top: 1px solid #e5e7eb; padding: 12px 16px; }
    .prova h3 { font-size: 15px; margin: 0 0 8px; color: #111827; }
    .blocco-personale { background: #f3f4f6; border-radius: 10px; padding: 10px; margin-bottom: 8px; font-size: 13px; }
    .blocco-finale { border-left: 4px solid #4f46e5; border-radius: 10px; padding: 10px; background: #eef2ff; font-size: 13px; }
    .etichetta { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; margin-bottom: 6px; }
    .riga-commissario { padding: 4px 0; overflow-wrap: anywhere; }
    .impressione { color: #4b5563; font-style: italic; overflow-wrap: anywhere; white-space: pre-line; }
    .mancante { color: #92400e; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Riepilogo prova finale</p>
<p class="page-sub">Colloquio e prova pratica · sola lettura. Le schede finali le inserisce il direttore dal pannello.</p>

<div class="avviso-personale">Schede personali dei commissari: appunti, non entrano nel calcolo.@unless($r['aperta']) La prova finale è chiusa: sono in sola lettura.@endunless</div>
<div class="avviso-finale">Schede finali del direttore: sono le uniche che entrano nel calcolo.</div>

@if($r['discenti'])
    <input type="search" id="cerca" class="cerca" placeholder="Cerca per nome o cognome" autocomplete="off" aria-label="Cerca discente">
@endif

@forelse($r['discenti'] as $d)
    @php $c = $d['completezza']; @endphp
    <details class="disc" data-nome="{{ $d['socio']->cognome }} {{ $d['socio']->nome }}">
        <summary>
            <div class="disc-nome">{{ $d['socio']->cognome }} {{ $d['socio']->nome }}</div>
            <div class="disc-stato">
                Colloquio {{ $d['colloquio']['finale'] ? $d['colloquio']['finale']['punteggio'].' su '.$esame->modello_snapshot['colloquio']['scala']['max'] : 'finale mancante' }}
                · Prova pratica {{ ($d['prova_pratica']['finale']['totale'] ?? null) ? $d['prova_pratica']['finale']['totale'].' su '.$r['massimo_pratica'] : 'finale mancante' }}
            </div>
        </summary>

        <div class="prova">
            <h3>Colloquio</h3>
            <div class="blocco-personale">
                <div class="etichetta">Schede personali — appunti</div>
                @forelse($d['colloquio']['personali'] as $p)
                    <div class="riga-commissario"><strong>{{ $p['membro'] }}</strong>: voto {{ $p['voto'] }}
                        @if($p['impressione'])<div class="impressione">{{ $p['impressione'] }}</div>@endif
                    </div>
                @empty
                    <div class="mancante">Nessuna scheda personale.</div>
                @endforelse
                @if($d['colloquio']['proposta'] !== null)<div>Media dei voti (troncata): <strong>{{ $d['colloquio']['proposta'] }}</strong></div>@endif
            </div>
            <div class="blocco-finale">
                <div class="etichetta">Scheda finale — entra nel calcolo</div>
                @if($d['colloquio']['finale'])
                    <div><strong>{{ $d['colloquio']['finale']['punteggio'] }} su {{ $esame->modello_snapshot['colloquio']['scala']['max'] }}</strong></div>
                    @if($d['colloquio']['finale']['motivazione'])<div class="impressione">{{ $d['colloquio']['finale']['motivazione'] }}</div>@endif
                    <div>{{ $d['colloquio']['finale']['autore'] ?? '—' }} · {{ $d['colloquio']['finale']['data']?->format('d/m/Y H:i') }}</div>
                @else
                    <div class="mancante">Finale non ancora inserita.</div>
                @endif
            </div>
        </div>

        <div class="prova">
            <h3>Prova pratica</h3>
            <div class="blocco-personale">
                <div class="etichetta">Schede personali — appunti</div>
                @forelse($d['prova_pratica']['personali'] as $p)
                    <div class="riga-commissario"><strong>{{ $p['membro'] }}</strong>:
                        @if($p['inviata']) inviata{{ $p['totale'] !== null ? ' · '.$p['totale'].' su '.$r['massimo_pratica'] : '' }}
                        @else bozza ({{ $p['voci'] }} voci) @endif
                    </div>
                @empty
                    <div class="mancante">Nessuna scheda personale.</div>
                @endforelse
                @if($d['prova_pratica']['proposta_totale'] !== null)<div>Proposta (voce per voce): <strong>{{ $d['prova_pratica']['proposta_totale'] }} su {{ $r['massimo_pratica'] }}</strong></div>@endif
            </div>
            <div class="blocco-finale">
                <div class="etichetta">Scheda finale — entra nel calcolo</div>
                @if($d['prova_pratica']['finale'])
                    <div><strong>{{ $d['prova_pratica']['finale']['totale'] ?? '—' }} su {{ $d['prova_pratica']['finale']['massimo'] }}</strong></div>
                    <div>{{ $d['prova_pratica']['finale']['autore'] ?? '—' }} · {{ $d['prova_pratica']['finale']['data']?->format('d/m/Y H:i') }}</div>
                @else
                    <div class="mancante">Finale non ancora inserita.</div>
                @endif
            </div>
        </div>
    </details>
@empty
    <div class="vuoto">Nessun discente ammesso alla prova finale.</div>
@endforelse
<div class="vuoto" id="nessun-risultato" hidden>Nessun discente trovato.</div>
@endsection

@push('scripts')
<script>
(function () {
    var campo = document.getElementById('cerca');
    if (!campo) return;
    var card = document.querySelectorAll('details.disc');
    var vuoto = document.getElementById('nessun-risultato');
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
