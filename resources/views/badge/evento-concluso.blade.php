<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evento concluso — {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f3f4f6;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 16px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: #9ca3af;
            color: #fff;
            padding: 20px 24px;
        }
        .card-header .label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .card-header .evento-nome {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
        }
        .card-body { padding: 28px 24px; }
        .icona {
            font-size: 48px;
            text-align: center;
            margin-bottom: 16px;
        }
        .titolo {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
            text-align: center;
            margin-bottom: 10px;
        }
        .testo {
            font-size: 14px;
            color: #6b7280;
            text-align: center;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <div class="label">Croce Rossa Italiana</div>
            <div class="evento-nome">{{ $evento->nome }}</div>
        </div>
        <div class="card-body">
            <div class="icona">🏁</div>
            <div class="titolo">Evento concluso</div>
            <p class="testo">
                Questo evento si è concluso.<br>
                Il QR code non è più attivo.
            </p>
        </div>
    </div>
</body>
</html>
