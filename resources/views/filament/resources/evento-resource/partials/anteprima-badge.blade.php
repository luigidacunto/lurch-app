<style>
    /* Stesso font usato da dompdf per il PDF reale (vendor/dompdf/dompdf/lib/fonts) —
       senza questo l'anteprima usa il sans-serif di sistema del browser, con metriche
       diverse: lo stesso testo va a capo in punti diversi e la posizione non è realistica. */
    @font-face {
        font-family: 'DejaVu Sans Anteprima Badge';
        src: url('{{ asset('fonts/badge-preview/DejaVuSans.ttf') }}') format('truetype');
        font-weight: normal;
    }
    @font-face {
        font-family: 'DejaVu Sans Anteprima Badge';
        src: url('{{ asset('fonts/badge-preview/DejaVuSans-Bold.ttf') }}') format('truetype');
        font-weight: bold;
    }
</style>

<div style="display:flex; justify-content:center; padding:20px; background:#f3f4f6;">
    <div style="width:61mm; height:97mm; position:relative; overflow:hidden; background:#ffffff; box-shadow:0 2px 10px rgba(0,0,0,.18); flex-shrink:0;">

        @if($sfondo)
            <img src="{{ $sfondo }}" style="position:absolute; top:0; left:0; width:61mm; height:97mm;" />
        @endif

        @if($config['titolo_evento']['attivo'] ?? false)
            <div style="position:absolute; text-align:center; font-weight:bold; line-height:1.2; font-family:'DejaVu Sans Anteprima Badge', sans-serif;
                top: {{ $config['titolo_evento']['y'] }}mm;
                left: {{ $config['titolo_evento']['x'] }}mm;
                width: {{ $config['titolo_evento']['width'] }}mm;
                font-size: {{ $config['titolo_evento']['font_size'] }}pt;
            ">{{ $nomeEvento }}</div>
        @endif

        <div style="position:absolute; text-align:center; font-weight:bold; line-height:1.2; font-family:'DejaVu Sans Anteprima Badge', sans-serif;
            top: {{ $config['nome_cognome']['y'] }}mm;
            left: {{ $config['nome_cognome']['x'] }}mm;
            width: {{ $config['nome_cognome']['width'] }}mm;
            font-size: {{ $config['nome_cognome']['font_size'] }}pt;
        ">Cognome Nome</div>

        <div style="position:absolute; text-align:center; line-height:1.2; font-family:'DejaVu Sans Anteprima Badge', sans-serif;
            top: {{ $config['corso_ruolo']['y'] }}mm;
            left: {{ $config['corso_ruolo']['x'] }}mm;
            width: {{ $config['corso_ruolo']['width'] }}mm;
            font-size: {{ $config['corso_ruolo']['font_size'] }}pt;
        ">Protocollo corso / Staff</div>

        <img src="{{ $qrSrc }}" style="position:absolute;
            top: {{ $config['qr']['y'] }}mm;
            left: {{ $config['qr']['x'] }}mm;
            width: {{ $config['qr']['size'] }}mm;
            height: {{ $config['qr']['size'] }}mm;
        " />

    </div>
</div>

<p style="text-align:center; font-size:11px; color:#9ca3af; margin-top:10px;">
    Anteprima indicativa (dimensioni reali 61×97mm) — nome, protocollo e QR sono di esempio, il resto rispecchia le impostazioni della sezione "Badge" nella modifica evento.
    @if(! $sfondo)
        <br>Nessuno sfondo caricato per questo evento.
    @endif
</p>
