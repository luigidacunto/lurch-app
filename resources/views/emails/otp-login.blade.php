<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codice di accesso</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:system-ui,-apple-system,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:520px;width:100%;">
                    <tr>
                        <td style="background:#C8102E;padding:24px 32px;">
                            <p style="margin:0;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:0.5px;">Lurch</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;color:#111827;font-size:16px;">Ciao {{ $socio->nome }},</p>
                            <p style="margin:0 0 28px;color:#6b7280;font-size:15px;">Hai richiesto l'accesso al portale Lurch. Usa il codice qui sotto per completare il login.</p>

                            <div style="background:#f9fafb;border:2px dashed #e5e7eb;border-radius:10px;padding:24px;text-align:center;margin-bottom:28px;">
                                <p style="margin:0 0 8px;color:#6b7280;font-size:12px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Il tuo codice</p>
                                <p style="margin:0;color:#111827;font-size:40px;font-weight:800;letter-spacing:10px;font-variant-numeric:tabular-nums;">{{ $codice }}</p>
                            </div>

                            <p style="margin:0 0 8px;color:#6b7280;font-size:13px;">Il codice è valido per <strong>15 minuti</strong>.</p>
                            <p style="margin:0;color:#9ca3af;font-size:12px;">Se non hai richiesto questo accesso, ignora questa email.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;color:#9ca3af;font-size:12px;">Lurch — Gestione eventi Croce Rossa Italiana</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
