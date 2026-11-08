@php
    $cent = fn (?string $c) => $c === null ? '—' : str_replace('.', ',', $c);
    $nd   = fn (?string $f) => $f === null ? '—' : \App\Esami\Formato::decimale(\App\Esami\Calcolo\Frazione::daFrazione($f));
    $corso = $esame->corso;
    $lap  = str_replace('.', ',', rtrim(rtrim((string) $riga['lap'], '0'), '.'));
    $nomi = \App\Esami\RisultatiEsame::ETICHETTE_PROVE;
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 18mm 16mm 18mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 17px; margin: 0 0 2px; }
    .sub { font-size: 11px; color: #444; margin-bottom: 8px; }
    .intestazione { border: 1px solid #999; padding: 6px 8px; margin-bottom: 12px; }
    .intestazione td { padding: 2px 8px 2px 0; vertical-align: top; }
    .lap { font-size: 15px; font-weight: bold; }
    .prov { border: 2px solid #b91c1c; color: #b91c1c; font-weight: bold; padding: 6px 8px; margin-bottom: 12px; text-align: center; }
    table.prove { width: 100%; border-collapse: collapse; margin: 8px 0; }
    table.prove th, table.prove td { border: 1px solid #999; padding: 5px 7px; text-align: left; }
    table.prove th { background: #eee; }
    .num { text-align: right !important; }
    .totale { border: 2px solid #111; padding: 8px 10px; margin: 10px 0; }
    .esito { font-size: 18px; font-weight: bold; }
    .nota { font-size: 9px; color: #555; margin-top: 6px; }
</style>
</head>
<body>
    <h1>{{ $esame->titolo }}</h1>
    <div class="sub">Esito individuale · {{ $esame->tipo_codice }} (modello v{{ $esame->modello_versione }})</div>

    @if($provvisorio)
        <div class="prov">PROVVISORIO — l'esame non è ancora chiuso: i dati sono calcolati dal vivo e possono cambiare.</div>
    @endif

    {{-- In alto: LAP, info del corso e dell'esame --}}
    <table class="intestazione" style="width:100%">
        <tr>
            <td class="lap">LAP: {{ $lap }}%</td>
            <td><strong>Corso:</strong> {{ $corso?->nome }}@if($corso?->numero_protocollo) ({{ $corso->numero_protocollo }})@endif</td>
        </tr>
        <tr>
            <td><strong>Direttore:</strong> {{ $corso?->direttore?->nome_completo ?? $corso?->direttore_corso ?? '—' }}</td>
            <td>
                <strong>Periodo:</strong> {{ $corso?->data_inizio?->format('d/m/Y') }} – {{ $corso?->data_fine?->format('d/m/Y') }}
                @if(! $provvisorio) · <strong>Esame chiuso</strong> il {{ $esame->chiuso_il?->format('d/m/Y H:i') }}@if($esame->chiusoDa) da {{ $esame->chiusoDa->nome_completo }}@endif @endif
            </td>
        </tr>
    </table>

    <p><strong>Discente:</strong> {{ $riga['socio']->cognome }} {{ $riga['socio']->nome }} · <strong>Codice fiscale:</strong> {{ $riga['socio']->codice_fiscale }}</p>

    @if($riga['stato'] === 'non_ammesso')
        <div class="totale">
            <div class="esito">NON AMMESSO alla prova finale</div>
        </div>
    @elseif($riga['stato'] === 'incompleto')
        <div class="totale">
            <div class="esito">ESITO NON ANCORA CALCOLABILE</div>
            <div>Manca: {{ implode(', ', $riga['mancanti']) }}.</div>
        </div>
    @else
        <table class="prove">
            <thead>
                <tr><th>Prova</th><th class="num">Peso</th><th class="num">Punti</th><th class="num">Contributo (centesimi)</th></tr>
            </thead>
            <tbody>
                @foreach(array_keys($nomi) as $p)
                    <tr>
                        <td>{{ $nomi[$p] }}</td>
                        <td class="num">{{ $pesi[$p] ?? '—' }}%</td>
                        <td class="num">{{ $nd($riga['prove'][$p]['punti'] ?? null) }} su {{ $nd($riga['prove'][$p]['massimo'] ?? null) }}</td>
                        <td class="num">{{ $cent($riga['prove'][$p]['contributo'] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totale">
            <div>Punteggio totale: <strong style="font-size:18px;">{{ $cent($riga['finale']) }}</strong> su 100</div>
            <div class="esito">{{ $riga['idoneo'] ? 'IDONEO' : 'NON IDONEO' }}</div>
        </div>
        <div class="nota">Il punteggio finale è calcolato sui valori esatti; i parziali sono mostrati troncati a 2 decimali e il finale a 1 decimale (mai arrotondati). Idoneo se il finale è almeno pari al LAP.</div>
    @endif

    <div class="nota" style="margin-top:14px;">Generato dal sistema LURCH il {{ now()->format('d/m/Y H:i') }}. Lo strumento non è una certificazione ufficiale: fa fede la scheda d'esame firmata dalla commissione.</div>
</body>
</html>
