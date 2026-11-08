<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaggio inviato — {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f3f4f6;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 400px;
            width: 100%;
            padding: 40px 28px;
            text-align: center;
        }
        .icona {
            width: 64px; height: 64px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
        }
        h1 {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 10px;
        }
        p {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
        }
        p strong { color: #374151; }
        .divider { border: none; border-top: 1px solid #f3f4f6; margin: 24px 0; }
        .nota {
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icona">✉️</div>
        <h1>Messaggio inviato!</h1>
        <p>
            Il tuo messaggio per <strong>{{ $socio->nome }} {{ $socio->cognome }}</strong>
            è stato recapitato correttamente.
        </p>
        <hr class="divider">
        <p class="nota">
            {{ $socio->nome }} potrà leggerlo accedendo all'app con la propria email.<br>
            Il messaggio è anonimo, a meno che tu non abbia incluso il tuo nome nel testo.
        </p>
    </div>
</body>
</html>
