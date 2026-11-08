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
</style>
</head>
<body>
@php
    $titoloAttivo = $config['titolo_evento']['attivo'] ?? false;
@endphp
<table class="badge-grid">
    <tr>
        @for ($i = 0; $i < 3; $i++)
        <td>
            <div class="badge-wrap">
                @if($sfondo)
                <img class="badge-sfondo" src="{{ $sfondo }}" />
                @endif
                @if($titoloAttivo)
                <div class="badge-titolo" style="
                    top: {{ $config['titolo_evento']['y'] }}mm;
                    left: {{ $config['titolo_evento']['x'] }}mm;
                    width: {{ $config['titolo_evento']['width'] }}mm;
                    font-size: {{ $config['titolo_evento']['font_size'] }}pt;
                ">{{ $nomeEvento ?? '' }}</div>
                @endif
            </div>
        </td>
        @endfor
    </tr>
    <tr>
        @for ($i = 0; $i < 3; $i++)
        <td>
            <div class="badge-wrap">
                @if($sfondo)
                <img class="badge-sfondo" src="{{ $sfondo }}" />
                @endif
                @if($titoloAttivo)
                <div class="badge-titolo" style="
                    top: {{ $config['titolo_evento']['y'] }}mm;
                    left: {{ $config['titolo_evento']['x'] }}mm;
                    width: {{ $config['titolo_evento']['width'] }}mm;
                    font-size: {{ $config['titolo_evento']['font_size'] }}pt;
                ">{{ $nomeEvento ?? '' }}</div>
                @endif
            </div>
        </td>
        @endfor
    </tr>
</table>
</body>
</html>
