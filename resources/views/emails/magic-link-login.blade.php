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
  .body { padding:32px; color:#374151; font-size:15px; line-height:1.7; }
  .body p { margin:0 0 14px; }
  .bottone { display:inline-block; background:#dc2626; color:#ffffff !important; text-decoration:none; border-radius:8px; padding:12px 24px; font-weight:600; font-size:14px; }
  .divider { text-align:center; color:#9ca3af; font-size:12px; margin:24px 0; text-transform:uppercase; letter-spacing:.05em; }
  .codice { display:block; text-align:center; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; font-size:28px; font-weight:700; letter-spacing:6px; color:#111827; }
  .avviso { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px 16px; font-size:13px; color:#92400e; margin-top:20px; }
  .footer { background:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px; font-size:12px; color:#9ca3af; text-align:center; }
</style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="header-title">Croce Rossa Italiana — Accesso al pannello</div>
    </div>
    <div class="body">
      <p>Gentile {{ $nomeUtente }},</p>
      <p>hai richiesto l'accesso al pannello senza password. Clicca il pulsante sottostante se ti trovi sullo stesso dispositivo da cui hai fatto la richiesta.</p>
      <p style="text-align:center;margin:24px 0;">
        <a href="{{ $linkLogin }}" class="bottone">Accedi al pannello</a>
      </p>
      <p class="divider">— oppure —</p>
      <p>Se ti trovi su un altro dispositivo, inserisci questo codice nella pagina da cui hai fatto la richiesta:</p>
      <span class="codice">{{ $codice }}</span>
      <p style="margin-top:24px;">Link e codice sono validi per 15 minuti.</p>
      @if($disabilitaPassword)
        <div class="avviso">Completando l'accesso, il login con password verrà disabilitato per questo account: da questo momento potrai accedere solo tramite email. Per problemi con questa casella di posta, contatta un superadmin per modificare l'indirizzo associato.</div>
      @endif
      <p style="margin-top:20px;">Se non hai richiesto questo accesso, ignora questa email: nessuna modifica verrà applicata al tuo account.</p>
    </div>
    <div class="footer">
      Questa email è stata generata automaticamente — si prega di non rispondere direttamente.<br>
      Croce Rossa Italiana
    </div>
  </div>
</body>
</html>
