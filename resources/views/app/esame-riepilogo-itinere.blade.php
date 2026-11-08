@extends('app.layout')

@section('title', 'Riepilogo itinere')

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
    .area { border-top: 1px solid #e5e7eb; padding: 12px 16px; }
    .area h3 { font-size: 15px; margin: 0 0 8px; color: #111827; }
    .blocco-personale { background: #f3f4f6; border-radius: 10px; padding: 10px; margin-bottom: 8px; font-size: 13px; }
    .blocco-finale { border-left: 4px solid #4f46e5; border-radius: 10px; padding: 10px; background: #eef2ff; font-size: 13px; }
    .etichetta { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; margin-bottom: 6px; }
    .forzato { background: #fef3c7; color: #92400e; border-radius: 6px; padding: 6px 8px; margin-top: 6px; }
    .tabella-scroll { overflow-x: auto; }
    .tabella-scroll table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
    .tabella-scroll th, .tabella-scroll td { padding: 4px 6px; border-top: 1px solid #e5e7eb; text-align: center; }
    .tabella-scroll th:first-child, .tabella-scroll td:first-child { text-align: left; }
    .pillola { display: inline-block; padding: 0 6px; border-radius: 6px; color: #111827; }
    .scarto-alto { display: inline-block; padding: 0 6px; border-radius: 6px; background: #b91c1c; color: #fff; font-weight: 800; }
    .disc-lab { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .pillola-lab { font-size: 11px; font-weight: 700; padding: 1px 6px; border-radius: 6px; background: #f3f4f6; color: #374151; }
    .pillola-lab.completo { background: #dcfce7; color: #166534; }
    .disc-lab .scarto-alto { font-size: 11px; }
    .riga-nota { font-size: 13px; padding: 2px 0; display: flex; align-items: center; gap: 6px; }
    .badge-na { font-size: 11px; background: #fee2e2; color: #991b1b; border-radius: 6px; padding: 1px 6px; margin-left: 6px; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Riepilogo itinere</p>
<p class="page-sub">Sola lettura. Il punteggio finale d'area lo inserisce il direttore dal pannello.</p>

<div class="avviso-personale">Schede personali dei docenti: appunti, non entrano nel calcolo.</div>
<div class="avviso-finale">Scheda finale d'area: deciso dal direttore con la commissione, è l'unico punteggio che entra nel calcolo.</div>

@if($r['discenti'])
    <input type="search" id="cerca" class="cerca" placeholder="Cerca per nome o cognome" autocomplete="off" aria-label="Cerca discente">
@endif

@forelse($r['discenti'] as $d)
    <details class="disc" data-nome="{{ $d['socio']->cognome }} {{ $d['socio']->nome }}">
        <summary>
            <div class="disc-nome">{{ $d['socio']->cognome }} {{ $d['socio']->nome }}
                @if($d['non_ammesso'])<span class="badge-na">Non ammesso</span>@endif
            </div>
            <div class="disc-stato">
                Aree decise {{ $d['decise'] }}/{{ $d['n_aree'] }} ·
                @if($d['totale'] !== null) Totale itinere finale {{ $d['totale'] }} su {{ $d['massimo'] }}
                @elseif($d['non_ammesso']) Nessuna scheda finale
                @else Totale itinere finale: incompleto @endif
            </div>
            @if($d['stato_lab'])
                <div class="disc-lab">
                    @foreach($d['stato_lab'] as $sl)
                        <span class="pillola-lab {{ $sl['attesi'] > 0 && $sl['inviate'] >= $sl['attesi'] ? 'completo' : '' }}">Lab {{ $sl['numero'] }} {{ $sl['inviate'] }}/{{ $sl['attesi'] }}</span>
                        @if($sl['scarto_alto'])<span class="scarto-alto">Δ Lab {{ $sl['numero'] }}: {{ $sl['scarto'] }}</span>@endif
                    @endforeach
                </div>
            @endif
        </summary>

        @if($d['annotazioni'])
            <div class="area">
                @foreach($d['annotazioni'] as $n)
                    <div class="riga-nota">{{ $n['docente'] }} ha inserito una nota sulla scheda itinere
                        @include('app.partials.esami-nota-dialog', ['docente' => $n['docente'], 'testo' => $n['testo'], 'titolo' => 'Scheda itinere · '.$d['socio']->cognome.' '.$d['socio']->nome])
                    </div>
                @endforeach
            </div>
        @endif

        @foreach($r['aree'] as $area)
            @php $a = $d['aree'][$area['codice']]; @endphp
            <div class="area">
                <h3>{{ $area['nome'] }}</h3>

                <div class="blocco-personale">
                    <div class="etichetta">Schede personali dei docenti — non entrano nel calcolo</div>
                    @if($d['docenti'] === [])
                        Nessun docente ha compilato la scheda.
                    @else
                        <div class="tabella-scroll">
                            <table>
                                <thead>
                                    <tr><th>Voce</th>@foreach($d['docenti'] as $docNome)<th>{{ $docNome }}</th>@endforeach</tr>
                                </thead>
                                <tbody>
                                    @foreach($area['voci'] as $voce)
                                        <tr>
                                            <td>{{ $voce['testo'] }}</td>
                                            @foreach($d['docenti'] as $docId => $docNome)<td>{{ $a['voti'][$docId][$voce['codice']] ?? '—' }}</td>@endforeach
                                        </tr>
                                    @endforeach
                                    <tr style="font-weight:700;">
                                        <td>Media del docente</td>
                                        @foreach($d['docenti'] as $docId => $docNome)<td>{{ $a['medie_docenti'][$docId]['media'] ?? '—' }}</td>@endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if($a['forzato'])
                        <div class="forzato">⚠ Nessun voto dei docenti. Valore forzato (LAP × {{ $esame->modello_snapshot['itinere']['scala']['max'] }}): <strong>{{ $a['proposta'] }}</strong>. Entra nel calcolo solo se il direttore lo conferma.</div>
                    @else
                        <div style="margin-top:6px;">Media delle medie dei docenti: <strong>{{ $a['media_delle_medie'] }}</strong> · Media di tutti i voti: <strong>{{ $a['media_tutti_i_voti'] }}</strong></div>
                    @endif

                    @if($area['specchio_laboratori'] && $d['laboratori'])
                        <div class="etichetta" style="margin-top:10px;">Andamento nei laboratori (schede inviate, media tra docenti)</div>
                        @foreach($d['laboratori'] as $lab)
                            <div>Lab {{ $lab['numero'] }}:
                                @if($lab['media'] === null) nessuna scheda inviata
                                @else <span class="pillola" style="background:{{ $lab['colore_media'] }};"><strong>{{ $lab['media'] }}</strong></span> su {{ $lab['massimo_scheda'] }}
                                    <span @if($lab['scarto_alto']) class="scarto-alto" @endif>(scarto {{ $lab['minimo'] }}–{{ $lab['massimo'] }}, Δ {{ $lab['scarto'] }})</span> —
                                    @foreach($lab['docenti'] as $ld){{ $ld['docente'] }} <span class="pillola" style="background:{{ $ld['colore'] }};">{{ $ld['totale'] }}</span>@if($ld['nota'])@include('app.partials.esami-nota-dialog', ['docente' => $ld['docente'], 'testo' => $ld['nota'], 'titolo' => 'Laboratorio '.$lab['numero'].' · '.$d['socio']->cognome.' '.$d['socio']->nome])@endif @if(! $loop->last) · @endif @endforeach
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="blocco-finale">
                    <div class="etichetta">Scheda finale d'area — entra nel calcolo</div>
                    @if($a['finale'])
                        Punteggio finale: <strong style="font-size:17px;">{{ $a['finale']['punteggio'] }}</strong>
                        <div style="font-size:12px; color:#6b7280;">Deciso da {{ $a['finale']['autore'] ?? 'direttore' }} il {{ $a['finale']['data']?->format('d/m/Y H:i') }}</div>
                        @if($a['finale']['motivazione'])<div style="margin-top:4px; white-space:pre-line;">Motivazione: {{ $a['finale']['motivazione'] }}</div>@endif
                    @else
                        Non ancora deciso.
                    @endif
                </div>
            </div>
        @endforeach
    </details>
@empty
    <div class="vuoto">Nessun discente iscritto al corso.</div>
@endforelse
@endsection

@push('scripts')
<script>
(function () {
    var campo = document.getElementById('cerca');
    if (!campo) return;
    var righe = document.querySelectorAll('details.disc');
    function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    campo.addEventListener('input', function () {
        var parole = norm(campo.value).split(/\s+/).filter(Boolean);
        righe.forEach(function (r) {
            var nome = norm(r.dataset.nome);
            r.hidden = !parole.every(function (p) { return nome.indexOf(p) !== -1; });
        });
    });
})();
</script>
@endpush
