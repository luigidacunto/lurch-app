{{-- Matrice voce × docente di UN laboratorio per UN discente (schede inviate), da RiepilogoItinere::dettaglioLaboratorio().
     Parametri: $m (dettaglio), $titoloNota (titolo del dialog delle note). Usata nel confronto tra docenti e nei tutorati. --}}
@once
@push('styles')
<style>
    .confronto-scroll { overflow-x: auto; margin: 10px 0 6px; }
    .confronto-scroll table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
    .confronto-scroll th, .confronto-scroll td { padding: 4px 6px; border-top: 1px solid #e5e7eb; text-align: center; }
    .confronto-scroll th:first-child, .confronto-scroll td:first-child { text-align: left; }
    .confronto-comp { font-weight: 700; padding-top: 8px !important; }
    .confronto-scroll tr.discorde { background: #fef9c3; }
    .confronto-scroll tr.salto { background: #fee2e2; }
    .confronto-totale td { border-top: 2px solid #9ca3af; font-weight: 800; padding-top: 6px; }
    .pillola-totale { display: inline-block; padding: 1px 6px; border-radius: 6px; color: #111827; white-space: nowrap; }
    .lv { font-weight: 800; }
    .lv-rosso { color: #dc2626; } .lv-giallo { color: #a16207; } .lv-verde { color: #16a34a; }
</style>
@endpush
@endonce
<div class="confronto-scroll">
    <table>
        <thead><tr><th>Voce</th>@foreach($m['docenti'] as $i => $docNome)<th>{{ $docNome }}@if($m['note'][$i] ?? null) @include('app.partials.esami-nota-dialog', ['docente' => $docNome, 'testo' => $m['note'][$i], 'titolo' => $titoloNota])@endif</th>@endforeach</tr></thead>
        <tbody>
            @foreach($m['competenze'] as $comp)
                <tr><td colspan="{{ count($m['docenti']) + 1 }}" class="confronto-comp">{{ $comp['nome'] }}</td></tr>
                @foreach($comp['voci'] as $voce)
                    <tr class="{{ $voce['salto'] ? 'salto' : ($voce['discordante'] ? 'discorde' : '') }}">
                        <td>{{ $voce['testo'] }}</td>
                        @foreach($voce['valori'] as $val)<td class="lv lv-{{ $val ?? 'vuoto' }}">{{ ['rosso' => 'R', 'giallo' => 'G', 'verde' => 'V'][$val] ?? '—' }}</td>@endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr class="confronto-totale">
                <td>Totale (su {{ $m['massimo'] }})</td>
                @foreach($m['totali'] as $t)<td>@if($t)<span class="pillola-totale" style="background:{{ $t['colore'] }};">{{ $t['totale'] }}</span>@else — @endif</td>@endforeach
            </tr>
        </tfoot>
    </table>
</div>
