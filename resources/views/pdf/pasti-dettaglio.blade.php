<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; background: #fff; padding: 12mm 14mm; }

    /* Intestazione evento (solo prima pagina) */
    .evento-header { margin-bottom: 12px; border-bottom: 2px solid #1e3a5f; padding-bottom: 8px; }
    .evento-header h1 { font-size: 13px; font-weight: bold; color: #1e3a5f; margin-bottom: 2px; }
    .evento-header .sub { font-size: 8px; color: #6b7280; }

    /* Intestazione pasto (ripetuta per ogni pagina pasto) */
    .pasto-header { margin-bottom: 8px; }
    .pasto-badge { display: inline-block; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; padding: 3px 10px; border-radius: 4px; }
    .badge-pranzo { background: #fef3c7; color: #92400e; }
    .badge-cena   { background: #ede9fe; color: #5b21b6; }
    .pasto-data   { font-size: 14px; font-weight: bold; color: #111827; margin-left: 8px; }
    .pasto-label  { font-size: 8px; color: #9ca3af; margin-top: 2px; }
    .pasto-count  { font-size: 8px; color: #6b7280; margin-top: 1px; }

    /* Tabella persone */
    table.persone { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.persone thead tr { background: #374151; color: #fff; }
    table.persone thead th { padding: 5px 7px; text-align: left; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; }
    table.persone tbody tr { border-bottom: 1px solid #f0f0f0; }
    table.persone tbody tr.alt { background: #f9fafb; }
    table.persone tbody td { padding: 6px 7px; vertical-align: top; font-size: 8.5px; }

    .cf { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; color: #6b7280; display: block; margin-top: 1px; }

    /* Tag */
    .tag { display: inline-block; font-size: 7.5px; padding: 1px 5px; border-radius: 3px; margin: 1px 2px 1px 0; }
    .tag-all  { background: #fee2e2; color: #991b1b; }
    .tag-diet { background: #fef3c7; color: #92400e; }
    .note-text { font-size: 7.5px; color: #374151; font-style: italic; margin-top: 2px; }

    /* Separatore pagine */
    .page-break { page-break-after: always; }

    .footer { margin-top: 10px; font-size: 7px; color: #bbb; border-top: 1px solid #eee; padding-top: 4px; }
</style>
</head>
<body>

@php
    $labelTipo = ['pranzo' => 'Pranzo', 'cena' => 'Cena'];
@endphp

@foreach($pastiConEsigenze as $idx => $riga)
<div class="{{ $idx < count($pastiConEsigenze) - 1 ? 'page-break' : '' }}">

    {{-- Intestazione evento (prima pagina) o mini-header (pagine successive) --}}
    @if($idx === 0)
    <div class="evento-header">
        <table style="width:100%;"><tr>
            <td>
                <div style="font-size:13px; font-weight:bold; color:#1e3a5f; margin-bottom:2px;">{{ $evento->nome }}</div>
                <div style="font-size:8px; color:#6b7280;">
                    {{ \Carbon\Carbon::parse($evento->data_inizio)->translatedFormat('d/m/Y') }}
                    @if($evento->data_inizio != $evento->data_fine)
                        – {{ \Carbon\Carbon::parse($evento->data_fine)->translatedFormat('d/m/Y') }}
                    @endif
                    @if($evento->comitatoLocale)  · {{ $evento->comitatoLocale->nome }} @endif
                    @if($evento->comitatoRegionale)  · {{ $evento->comitatoRegionale->nome }} @endif
                </div>
                <div style="font-size:8px; color:#6b7280; margin-top:2px; font-weight:bold;">Esigenze alimentari — dettaglio nominativo per pasto</div>
            </td>
            <td style="text-align:right; white-space:nowrap;">
                <div style="font-size:7.5px; color:#aaa;">Generato il {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr></table>
    </div>
    @else
    <div style="margin-bottom:10px; border-bottom:1px solid #e5e7eb; padding-bottom:6px;">
        <table style="width:100%;"><tr>
            <td style="font-size:8px; color:#9ca3af;">{{ $evento->nome }}</td>
            <td style="text-align:right; font-size:7.5px; color:#aaa;">Esigenze alimentari — dettaglio nominativo</td>
        </tr></table>
    </div>
    @endif

    {{-- Intestazione pasto --}}
    <div class="pasto-header">
        <table style="width:100%;"><tr>
            <td>
                <span class="pasto-badge badge-{{ $riga['tipo'] }}">{{ $labelTipo[$riga['tipo']] ?? $riga['tipo'] }}</span>
                <span class="pasto-data">{{ \Carbon\Carbon::parse($riga['data'])->translatedFormat('l d/m/Y') }}</span>
                <div class="pasto-count" style="margin-top:4px;">
                    {{ count($riga['personeConEsigenze']) }} {{ count($riga['personeConEsigenze']) === 1 ? 'persona con esigenze' : 'persone con esigenze' }}
                    su {{ $riga['effettivo'] }} {{ $riga['effettivo'] === 1 ? 'presente' : 'presenti' }}
                </div>
            </td>
            <td style="text-align:right; font-size:9px; color:#6b7280;">
                Totale presenti: <strong>{{ $riga['effettivo'] }}</strong>
            </td>
        </tr></table>
    </div>

    {{-- Tabella nominativi --}}
    <table class="persone">
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th style="width:22%;">Nominativo</th>
                <th style="width:35%;">Allergeni</th>
                <th style="width:25%;">Regime alimentare</th>
                <th style="width:14%;">Note aggiuntive</th>
            </tr>
        </thead>
        <tbody>
            @foreach($riga['personeConEsigenze'] as $pi => $persona)
            <tr class="{{ $pi % 2 === 1 ? 'alt' : '' }}">
                <td style="color:#9ca3af; text-align:center; font-size:8px;">{{ $pi + 1 }}</td>
                <td><strong>{{ $persona['nome'] }}</strong></td>
                <td>
                    @forelse($persona['allergeni'] as $a)
                        <span class="tag tag-all">{{ $a }}</span>
                    @empty
                        <span style="color:#d1d5db; font-size:8px;">—</span>
                    @endforelse
                </td>
                <td>
                    @forelse($persona['diete'] as $d)
                        <span class="tag tag-diet">{{ $d }}</span>
                    @empty
                        <span style="color:#d1d5db; font-size:8px;">—</span>
                    @endforelse
                </td>
                <td>
                    @if($persona['note'])
                        <span class="note-text">{{ $persona['note'] }}</span>
                    @else
                        <span style="color:#d1d5db; font-size:8px;">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <table style="width:100%;"><tr>
            <td>LURCH — Gestione Corsi e Formazione CRI</td>
            <td style="text-align:right;">{{ $evento->nome }} · {{ $labelTipo[$riga['tipo']] ?? $riga['tipo'] }} {{ \Carbon\Carbon::parse($riga['data'])->format('d/m/Y') }}</td>
        </tr></table>
    </div>
</div>
@endforeach

</body>
</html>
