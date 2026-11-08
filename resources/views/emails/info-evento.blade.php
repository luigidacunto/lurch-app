<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width:620px; margin:32px auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .header { background:#dc2626; padding:24px 32px; }
  .header-title { color:#ffffff; font-size:13px; font-weight:600; margin-top:4px; letter-spacing:.04em; text-transform:uppercase; }
  .header-evento { color:rgba(255,255,255,.85); font-size:18px; font-weight:700; margin-top:8px; }
  .logistica { background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:20px 32px; }
  .logistica-row { display:flex; align-items:flex-start; gap:10px; margin-bottom:10px; font-size:14px; color:#374151; }
  .logistica-row:last-child { margin-bottom:0; }
  .logistica-icon { width:18px; flex-shrink:0; color:#6b7280; margin-top:1px; }
  .logistica-label { font-weight:600; color:#111827; margin-right:6px; }
  .body { padding:32px; color:#374151; font-size:15px; line-height:1.7; }
  .body h2, .body h3 { color:#111827; margin-top:28px; margin-bottom:8px; }
  .body h2 { font-size:18px; }
  .body h3 { font-size:15px; }
  .body p  { margin:0 0 14px; }
  .body ul, .body ol { padding-left:20px; margin:0 0 14px; }
  .body a  { color:#2563eb; }
  .body strong { color:#111827; }
  .body table { border-collapse:collapse; width:100%; margin:0 0 14px; }
  .body table td, .body table th { border:1px solid #e5e7eb; padding:8px 10px; font-size:14px; }
  .body table th { background:#f9fafb; font-weight:600; }
  .footer { background:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px; font-size:12px; color:#9ca3af; text-align:center; }
</style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="header-title">Croce Rossa Italiana — Informazioni evento</div>
      <div class="header-evento">{{ $nomeEvento }}</div>
    </div>

    @php
      $haLogistica = filled($dateTesto) || $sede || $indirizzo;
      $mapsUrl     = filled($coordinateMaps)
          ? 'https://www.google.com/maps?q=' . urlencode($coordinateMaps)
          : (filled($indirizzo) ? 'https://www.google.com/maps/search/' . urlencode($indirizzo) : null);
    @endphp

    @if($haLogistica)
    <div class="logistica">

      @if(filled($dateTesto))
      <div class="logistica-row">
        <svg class="logistica-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
        <div>
          <span class="logistica-label">Date</span>
          {{ $dateTesto }}
        </div>
      </div>
      @endif

      @if($sede)
      <div class="logistica-row">
        <svg class="logistica-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
        <div>
          <span class="logistica-label">Sede</span>{{ $sede }}
        </div>
      </div>
      @endif

      @if($indirizzo)
      <div class="logistica-row">
        <svg class="logistica-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
        <div>
          <span class="logistica-label">Indirizzo</span>{{ $indirizzo }}
          @if($mapsUrl)
            &nbsp;·&nbsp;<a href="{{ $mapsUrl }}" style="color:#2563eb;font-size:13px;">Apri su Google Maps →</a>
          @endif
        </div>
      </div>
      @endif

    </div>
    @endif

    <div class="body">
      @if(!empty($intestazioneSrc))
        <div style="margin:-32px -32px 24px -32px;">
          <img src="{{ $intestazioneSrc }}" style="width:100%;max-height:240px;object-fit:cover;display:block;">
        </div>
      @endif
      {!! $corpo !!}

      @php
        // QR check-in inline: nelle Mailable è $message->embedData() (immagine CID, i client
        // email bloccano le data: URI); nell'anteprima modale $message non esiste e si usa la
        // data: URI passata da renderAnteprimaInfoEvento().
        $qrSrc = (! empty($qrCheckinPng ?? null) && isset($message))
            ? $message->embedData($qrCheckinPng, 'qr-checkin.png', 'image/png')
            : ($qrCheckinPreviewSrc ?? null);
      @endphp

      @if(($allegaBadge ?? false) && $qrSrc)
      <div style="margin-top:24px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;padding:20px;text-align:center;">
        <div style="font-size:15px;font-weight:700;color:#92400e;margin-bottom:12px;">📲 Il tuo QR per il check-in</div>
        <img src="{{ $qrSrc }}" alt="Codice QR per il check-in" width="190" height="190"
             style="display:block;margin:0 auto 12px;width:190px;height:190px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffff;">
        <p style="margin:0;font-size:14px;line-height:1.7;color:#78350f;text-align:left;">
          <strong>Conserva questa email.</strong> All'arrivo presso la struttura mostra questo codice QR
          al personale della segreteria, sia in <strong>entrata</strong> sia in <strong>uscita</strong>,
          ogni volta che accedi o lasci il campo: serve a registrare i tuoi ingressi e le tue uscite.
          Puoi mostrarlo direttamente dallo schermo del cellulare.
        </p>
      </div>
      @endif
    </div>
    <div class="footer">
      Questa email è stata generata automaticamente — si prega di non rispondere direttamente.<br>
      Croce Rossa Italiana · {{ $comitatoNome }}
      @if(!empty($referenti) && $referenti->isNotEmpty())
      <div style="margin-top:10px; padding-top:10px; border-top:1px solid #e5e7eb;">
        <strong style="color:#6b7280;">Referenti:</strong><br>
        @foreach($referenti as $r)
          {{ $r->name }} — <a href="mailto:{{ $r->email }}" style="color:#9ca3af;">{{ $r->email }}</a><br>
        @endforeach
      </div>
      @endif
    </div>
  </div>
</body>
</html>
