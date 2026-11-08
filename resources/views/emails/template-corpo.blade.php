<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width:620px; margin:32px auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .header { background:#dc2626; padding:24px 32px; }
  .header img { height:36px; }
  .header-title { color:#ffffff; font-size:13px; font-weight:600; margin-top:8px; letter-spacing:.04em; text-transform:uppercase; }
  .body { padding:32px; color:#374151; font-size:15px; line-height:1.7; }
  .body h2, .body h3 { color:#111827; margin-top:28px; margin-bottom:8px; }
  .body h2 { font-size:18px; }
  .body h3 { font-size:15px; }
  .body p  { margin:0 0 14px; }
  .body ul, .body ol { padding-left:20px; margin:0 0 14px; }
  .body a  { color:#2563eb; }
  .body strong { color:#111827; }
  .footer { background:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px; font-size:12px; color:#9ca3af; text-align:center; }
</style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="header-title">Croce Rossa Italiana</div>
    </div>
    <div class="body">
      {!! $corpo !!}
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
