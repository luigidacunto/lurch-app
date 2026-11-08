<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; background: #fff; padding: 12mm 14mm; }

    .header { margin-bottom: 10px; }
    .header h1 { font-size: 13px; font-weight: bold; color: #1e3a5f; margin-bottom: 2px; }
    .header .sub { font-size: 8px; color: #6b7280; }
    .header .stamp { font-size: 7.5px; color: #aaa; text-align: right; }

    .header-row { width: 100%; }
    .header-row td { vertical-align: top; }
    .header-row td.right { text-align: right; }

    /* Totali */
    .riepilogo { width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb; }
    .riepilogo td { text-align: center; padding: 5px 10px; border-right: 1px solid #e5e7eb; }
    .riepilogo td:last-child { border-right: none; }
    .riepilogo .val { font-size: 14px; font-weight: bold; color: #1e3a5f; display: block; line-height: 1.2; }
    .riepilogo .lbl { font-size: 7px; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; display: block; }

    /* Tabella principale */
    table.main { width: 100%; border-collapse: collapse; }
    table.main thead tr { background: #1e3a5f; color: #fff; }
    table.main thead th { padding: 5px 7px; text-align: left; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; }
    table.main thead th.center { text-align: center; }
    table.main tbody tr { border-bottom: 1px solid #f0f0f0; }
    table.main tbody tr.alt { background: #f8fafc; }
    table.main tbody td { padding: 6px 7px; vertical-align: top; font-size: 8.5px; }
    table.main tbody td.center { text-align: center; }
    table.main tbody td.num { font-size: 12px; font-weight: bold; text-align: center; color: #111827; }
    table.main tbody td.num-gray { font-size: 10px; font-weight: bold; text-align: center; color: #9ca3af; }

    /* Badge tipo pasto */
    .badge-pranzo { background: #fef3c7; color: #92400e; font-size: 7px; font-weight: bold; text-transform: uppercase; padding: 1px 5px; border-radius: 3px; }
    .badge-cena   { background: #ede9fe; color: #5b21b6; font-size: 7px; font-weight: bold; text-transform: uppercase; padding: 1px 5px; border-radius: 3px; }

    /* Tag allergeni / diete */
    .tag { display: inline-block; font-size: 7.5px; padding: 1px 5px; border-radius: 3px; margin: 1px 2px 1px 0; }
    .tag-all { background: #fee2e2; color: #991b1b; }
    .tag-diet { background: #fef3c7; color: #92400e; }

    .note-col { font-size: 7.5px; color: #374151; font-style: italic; }
    .dash { color: #d1d5db; }

    .footer { margin-top: 8px; font-size: 7px; color: #bbb; border-top: 1px solid #eee; padding-top: 4px; }
</style>
</head>
<body>

@php
    $coloreTipo = ['pranzo' => '#f59e0b', 'cena' => '#8b5cf6'];
    $labelTipo  = ['pranzo' => 'Pranzo', 'cena' => 'Cena'];
    $totPasti   = $totali['pasti'];
@endphp

{{-- Header --}}
<table class="header-row" style="margin-bottom:10px;">
    <tr>
        <td>
            <div class="header">
                <div class="h1" style="font-size:13px; font-weight:bold; color:#1e3a5f; margin-bottom:2px;">{{ $evento->nome }}</div>
                <div class="sub">
                    {{ \Carbon\Carbon::parse($evento->data_inizio)->translatedFormat('d/m/Y') }}
                    @if($evento->data_inizio != $evento->data_fine)
                        – {{ \Carbon\Carbon::parse($evento->data_fine)->translatedFormat('d/m/Y') }}
                    @endif
                    @if($evento->comitatoLocale)  · {{ $evento->comitatoLocale->nome }} @endif
                    @if($evento->comitatoRegionale)  · {{ $evento->comitatoRegionale->nome }} @endif
                </div>
                <div class="sub" style="margin-top:2px; font-weight:bold;">Griglia pasti — riepilogo per la cucina</div>
            </div>
        </td>
        <td class="right" style="white-space:nowrap;">
            <div class="stamp">Generato il {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

{{-- Riepilogo totali --}}
<table class="riepilogo" style="margin-bottom:10px;">
    <tr>
        <td><span class="val">{{ $totPasti['eff'] }}</span><span class="lbl">Pasti effettivi</span></td>
        <td><span class="val" style="color:#9ca3af;">{{ $totPasti['pot'] }}</span><span class="lbl">Pasti potenziali</span></td>
        <td><span class="val">{{ count($righePresenze) }}</span><span class="lbl">Turni pasto</span></td>
        @php $conEsigenze = collect($righePresenze)->sum(fn($r) => count($r['personeConEsigenze'])); @endphp
        <td><span class="val" style="color:#dc2626;">{{ $conEsigenze }}</span><span class="lbl">Esigenze totali</span></td>
    </tr>
</table>

{{-- Tabella principale --}}
<table class="main">
    <thead>
        <tr>
            <th style="width:14%;">Pasto</th>
            <th class="center" style="width:7%;">Effettivi</th>
            <th class="center" style="width:7%;">Potenziali</th>
            <th style="width:25%;">Allergeni</th>
            <th style="width:22%;">Regimi alimentari</th>
            <th style="width:25%;">Altre annotazioni</th>
        </tr>
    </thead>
    <tbody>
        @foreach($righePresenze as $idx => $riga)
        @php
            $allergeniPasto = collect();
            $dietePasto     = collect();
            $notePasto      = collect();
            foreach ($riga['personeConEsigenze'] as $pe) {
                foreach ($pe['allergeni'] as $a) $allergeniPasto->push($a);
                foreach ($pe['diete']     as $d) $dietePasto->push($d);
                if ($pe['note']) $notePasto->push($pe['note']);
            }
            $allergeniPasto = $allergeniPasto->countBy()->sortDesc();
            $dietePasto     = $dietePasto->countBy()->sortDesc();
            $noteUniche     = $notePasto->unique()->values();
        @endphp
        <tr class="{{ $idx % 2 === 1 ? 'alt' : '' }}">
            <td>
                <span class="badge-{{ $riga['tipo'] }}">{{ $labelTipo[$riga['tipo']] ?? $riga['tipo'] }}</span>
                <span style="font-weight:bold; margin-left:4px;">{{ \Carbon\Carbon::parse($riga['data'])->format('d/m') }}</span>
                <div style="font-size:7px; color:#9ca3af; margin-top:1px;">{{ \Carbon\Carbon::parse($riga['data'])->translatedFormat('l') }}</div>
            </td>
            <td class="num">{{ $riga['effettivo'] }}</td>
            <td class="num-gray">{{ $riga['potenziale'] }}</td>
            <td>
                @if($allergeniPasto->isNotEmpty())
                    @foreach($allergeniPasto as $nome => $count)
                        <span class="tag tag-all">{{ $nome }} ×{{ $count }}</span>
                    @endforeach
                @else
                    <span class="dash">—</span>
                @endif
            </td>
            <td>
                @if($dietePasto->isNotEmpty())
                    @foreach($dietePasto as $nome => $count)
                        <span class="tag tag-diet">{{ $nome }} ×{{ $count }}</span>
                    @endforeach
                @else
                    <span class="dash">—</span>
                @endif
            </td>
            <td class="note-col">
                @if($noteUniche->isNotEmpty())
                    @foreach($noteUniche as $nota)
                        <div style="margin-bottom:1px;">– {{ $nota }}</div>
                    @endforeach
                @else
                    <span class="dash">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    <table style="width:100%;"><tr>
        <td>LURCH — Gestione Corsi e Formazione CRI</td>
        <td style="text-align:right;">{{ $evento->nome }} · Griglia pasti</td>
    </tr></table>
</div>

</body>
</html>
