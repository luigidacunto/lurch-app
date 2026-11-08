<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; }

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: DejaVu Sans, sans-serif;
    padding: 10mm;
}

table.badge-grid {
    width: 190mm;
    border-collapse: collapse;
    border-spacing: 0;
}

table.badge-grid td {
    width: 63.33mm;
    height: 138.5mm;
    text-align: center;
    vertical-align: middle;
    padding: 0;
}

.badge-wrap {
    width: 61mm;
    height: 97mm;
    margin: 20.75mm auto;
    position: relative;
    overflow: hidden;
}

.badge-sfondo {
    position: absolute;
    top: 0;
    left: 0;
    width: 61mm;
    height: 97mm;
}

.badge-titolo {
    position: absolute;
    text-align: center;
    font-weight: bold;
    line-height: 1.2;
}

.badge-nome {
    position: absolute;
    text-align: center;
    font-weight: bold;
    line-height: 1.2;
}

.badge-corso {
    position: absolute;
    text-align: center;
    line-height: 1.2;
}

.badge-qr {
    position: absolute;
}
</style>
</head>
<body>

@foreach($badgeChunks as $pageIndex => $chunk)

@if($pageIndex > 0)
<div style="page-break-before: always;"></div>
@endif

<table class="badge-grid">
    {{-- Riga 1: badge 1-3 --}}
    <tr>
    @foreach($chunk->slice(0, 3) as $badge)
        <td>
            <div class="badge-wrap">

                @if($sfondo)
                <img class="badge-sfondo" src="{{ $sfondo }}" />
                @endif

                @if($config['titolo_evento']['attivo'] ?? false)
                <div class="badge-titolo" style="
                    top: {{ $config['titolo_evento']['y'] }}mm;
                    left: {{ $config['titolo_evento']['x'] }}mm;
                    width: {{ $config['titolo_evento']['width'] }}mm;
                    font-size: {{ $config['titolo_evento']['font_size'] }}pt;
                ">{{ $nomeEvento ?? '' }}</div>
                @endif

                <div class="badge-nome" style="
                    top: {{ $config['nome_cognome']['y'] }}mm;
                    left: {{ $config['nome_cognome']['x'] }}mm;
                    width: {{ $config['nome_cognome']['width'] }}mm;
                    font-size: {{ $config['nome_cognome']['font_size'] }}pt;
                ">{{ $badge['cognome'] }} {{ $badge['nome'] }}</div>

                <div class="badge-corso" style="
                    top: {{ $config['corso_ruolo']['y'] }}mm;
                    left: {{ $config['corso_ruolo']['x'] }}mm;
                    width: {{ $config['corso_ruolo']['width'] }}mm;
                    font-size: {{ $config['corso_ruolo']['font_size'] }}pt;
                ">{{ $badge['corso_ruolo'] }}</div>

                <img class="badge-qr" src="{{ $badge['qr_src'] }}" style="
                    top: {{ $config['qr']['y'] }}mm;
                    left: {{ $config['qr']['x'] }}mm;
                    width: {{ $config['qr']['size'] }}mm;
                    height: {{ $config['qr']['size'] }}mm;
                " />

            </div>
        </td>
    @endforeach
    @for($i = $chunk->slice(0, 3)->count(); $i < 3; $i++)
        <td></td>
    @endfor
    </tr>

    {{-- Riga 2: badge 4-6 --}}
    <tr>
    @foreach($chunk->slice(3, 3) as $badge)
        <td>
            <div class="badge-wrap">

                @if($sfondo)
                <img class="badge-sfondo" src="{{ $sfondo }}" />
                @endif

                @if($config['titolo_evento']['attivo'] ?? false)
                <div class="badge-titolo" style="
                    top: {{ $config['titolo_evento']['y'] }}mm;
                    left: {{ $config['titolo_evento']['x'] }}mm;
                    width: {{ $config['titolo_evento']['width'] }}mm;
                    font-size: {{ $config['titolo_evento']['font_size'] }}pt;
                ">{{ $nomeEvento ?? '' }}</div>
                @endif

                <div class="badge-nome" style="
                    top: {{ $config['nome_cognome']['y'] }}mm;
                    left: {{ $config['nome_cognome']['x'] }}mm;
                    width: {{ $config['nome_cognome']['width'] }}mm;
                    font-size: {{ $config['nome_cognome']['font_size'] }}pt;
                ">{{ $badge['cognome'] }} {{ $badge['nome'] }}</div>

                <div class="badge-corso" style="
                    top: {{ $config['corso_ruolo']['y'] }}mm;
                    left: {{ $config['corso_ruolo']['x'] }}mm;
                    width: {{ $config['corso_ruolo']['width'] }}mm;
                    font-size: {{ $config['corso_ruolo']['font_size'] }}pt;
                ">{{ $badge['corso_ruolo'] }}</div>

                <img class="badge-qr" src="{{ $badge['qr_src'] }}" style="
                    top: {{ $config['qr']['y'] }}mm;
                    left: {{ $config['qr']['x'] }}mm;
                    width: {{ $config['qr']['size'] }}mm;
                    height: {{ $config['qr']['size'] }}mm;
                " />

            </div>
        </td>
    @endforeach
    @for($i = $chunk->slice(3, 3)->count(); $i < 3; $i++)
        <td></td>
    @endfor
    </tr>
</table>

@endforeach

</body>
</html>
