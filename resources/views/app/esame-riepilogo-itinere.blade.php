@extends('app.layout')

@php $tutorati = $tutorati ?? false; @endphp
@section('title', $tutorati ? 'I miei tutorati' : 'Riepilogo itinere')

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
    .etichetta-riga { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; align-self: center; margin-right: 2px; }
    details.lab-dett { padding: 2px 0; }
    details.lab-dett > summary { cursor: pointer; color: #1d4ed8; }
    .legenda-lab { font-size: 12px; color: #6b7280; margin: 0 0 8px; }
    .riga-nota { font-size: 13px; padding: 2px 0; display: flex; align-items: center; gap: 6px; }
    .badge-na { font-size: 11px; background: #fee2e2; color: #991b1b; border-radius: 6px; padding: 1px 6px; margin-left: 6px; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
@if($tutorati)
<p class="page-title">I miei tutorati</p>
<p class="page-sub">Sola lettura. L'andamento dei discenti che segui come tutor: schede di tutti i docenti (laboratori: solo inviate) e punteggio finale d'area.</p>
@else
<p class="page-title">Riepilogo itinere</p>
<p class="page-sub">Sola lettura. Il punteggio finale d'area lo inserisce il direttore dal pannello.</p>
@endif

@if($tutorati)
    @include('app.partials.esami-legenda-lap', ['soglie' => $soglie])
@endif

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
            @if($tutorati)
                {{-- Anteprima del tutor: media dei docenti per area ("–" senza voti: il valore forzato vale solo se il direttore lo conferma) e media di ogni laboratorio, colori rispetto al LAP. --}}
                <div class="disc-lab">
                    <span class="etichetta-riga">Itinere</span>
                    {{-- In centesimi: «finale» (tutte le aree fissate dal direttore) oppure «medio» (medie dei docenti); «–» se manca una media. --}}
                    @php $ic = $itinereCentesimi[$d['socio']->id] ?? null; @endphp
                    @if($ic)
                        <span class="pillola-lab" style="@if($ic['colore'])background:{{ $ic['colore'] }}; color:#111827;@endif font-size:12px;">{{ $ic['testo'] }} su {{ $ic['peso'] }} · {{ $ic['definitivo'] ? 'finale' : 'medio' }}</span>
                    @endif
                </div>
                <div class="disc-lab">
                    <span class="etichetta-riga">Aree</span>
                    @foreach($r['aree'] as $area)
                        @php $a = $d['aree'][$area['codice']]; @endphp
                        <span class="pillola-lab" @if($a['colore_media']) style="background:{{ $a['colore_media'] }}; color:#111827;" @endif>{{ $area['nome'] }} {{ $a['colore_media'] ? $a['media_delle_medie'] : '–' }}</span>
                    @endforeach
                </div>
                @if($d['laboratori'])
                    <div class="disc-lab">
                        <span class="etichetta-riga">Laboratori</span>
                        @foreach($d['laboratori'] as $lab)
                            <span class="pillola-lab" @if($lab['colore_media']) style="background:{{ $lab['colore_media'] }}; color:#111827;" @endif>Lab {{ $lab['numero'] }} {{ $lab['media'] ?? '–' }}</span>
                        @endforeach
                    </div>
                @endif
            @elseif($d['stato_lab'])
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
            @php
                $a = $d['aree'][$area['codice']];
                // Solo i docenti che in QUEST'area hanno dato almeno un voto: chi ha una scheda ma non ha votato qui non ha colonna.
                $docentiArea = array_filter($d['docenti'], fn ($docNome, $docId) => collect($area['voci'])->contains(fn ($v) => isset($a['voti'][$docId][$v['codice']])), ARRAY_FILTER_USE_BOTH);
            @endphp
            <div class="area">
                <h3>{{ $area['nome'] }}</h3>

                <div class="blocco-personale">
                    <div class="etichetta">Schede personali dei docenti — non entrano nel calcolo</div>
                    @if($docentiArea === [])
                        Nessun docente ha ancora votato quest'area.
                    @else
                        <div class="tabella-scroll">
                            <table>
                                <thead>
                                    <tr><th>Voce</th>@foreach($docentiArea as $docNome)<th>{{ $docNome }}</th>@endforeach</tr>
                                </thead>
                                <tbody>
                                    @foreach($area['voci'] as $voce)
                                        <tr>
                                            <td>{{ $voce['testo'] }}</td>
                                            @foreach($docentiArea as $docId => $docNome)<td>{{ $a['voti'][$docId][$voce['codice']] ?? '—' }}</td>@endforeach
                                        </tr>
                                    @endforeach
                                    <tr style="font-weight:700;">
                                        <td>Media del docente</td>
                                        @foreach($docentiArea as $docId => $docNome)<td>{{ $a['medie_docenti'][$docId]['media'] ?? '—' }}</td>@endforeach
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
                            {{-- Pagina del tutor: la riga si apre sulla matrice voce × docente (schede inviate). --}}
                            @php $dett = $dettagliLab[$d['socio']->id][$lab['id']] ?? null; @endphp
                            @if($dett)<details class="lab-dett"><summary>@else<div>@endif
                            Lab {{ $lab['numero'] }}:
                                @if($lab['media'] === null) nessuna scheda inviata
                                @else <span class="pillola" style="background:{{ $lab['colore_media'] }};"><strong>{{ $lab['media'] }}</strong></span> su {{ $lab['massimo_scheda'] }}
                                    {{-- Lo scarto tra docenti interessa al direttore: il tutor legge da sé i totali dei docenti. --}}
                                    @unless($tutorati)<span @if($lab['scarto_alto']) class="scarto-alto" @endif>(scarto {{ $lab['minimo'] }}–{{ $lab['massimo'] }}, Δ {{ $lab['scarto'] }})</span>@endunless —
                                    @foreach($lab['docenti'] as $ld){{ $ld['docente'] }} <span class="pillola" style="background:{{ $ld['colore'] }};">{{ $ld['totale'] }}</span>@if($ld['nota'])@include('app.partials.esami-nota-dialog', ['docente' => $ld['docente'], 'testo' => $ld['nota'], 'titolo' => 'Laboratorio '.$lab['numero'].' · '.$d['socio']->cognome.' '.$d['socio']->nome])@endif @if(! $loop->last) · @endif @endforeach
                                @endif
                            @if($dett)</summary>
                                @include('app.partials.esami-matrice-lab', ['m' => $dett, 'titoloNota' => 'Laboratorio '.$lab['numero'].' · '.$d['socio']->cognome.' '.$d['socio']->nome])
                                <p class="legenda-lab">R rosso · G giallo · V verde. Sfondo giallo: risposte diverse; sfondo rosso: un docente ha dato rosso e un altro verde.</p>
                            </details>@else</div>@endif
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
