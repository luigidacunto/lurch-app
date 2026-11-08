<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lascia un messaggio — {{ config('app.name') }}</title>
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f3f4f6;
            font-family: system-ui, -apple-system, sans-serif;
            padding: 24px 16px 48px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 520px;
            margin: 0 auto;
            overflow: hidden;
        }
        .card-header {
            background: #C8102E;
            color: #fff;
            padding: 20px 24px;
        }
        .card-header .label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        .card-header .destinatario {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.2;
        }
        .card-header .evento-nome {
            font-size: 13px;
            opacity: 0.75;
            margin-top: 4px;
        }
        .card-body { padding: 20px 24px; }
        .avviso {
            background: #fef9ec;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            color: #78350f;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .avviso strong { display: block; margin-bottom: 4px; font-size: 13px; }
        .editor-label {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
        }
        #editor {
            min-height: 180px;
            font-size: 15px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .ql-toolbar.ql-snow {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            border-color: #e5e7eb;
        }
        .ql-container.ql-snow {
            border-color: #e5e7eb;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .firma-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 10px;
            line-height: 1.5;
        }
        .btn-invia {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            background: #C8102E;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .btn-invia:hover { opacity: 0.88; }
        .btn-invia:disabled { opacity: 0.5; cursor: not-allowed; }
        .error { color: #b91c1c; font-size: 13px; margin-top: 8px; display: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <div class="label">Stai lasciando un messaggio per</div>
            <div class="destinatario">{{ $socio->nome }} {{ $socio->cognome }}</div>
            <div class="evento-nome">{{ $evento->nome }}</div>
        </div>
        <div class="card-body">
            <div class="avviso">
                <strong>📩 Messaggio anonimo</strong>
                Il destinatario potrà leggere questo messaggio solo dopo aver effettuato l'accesso
                alla app con la propria email. Il tuo messaggio è anonimo: se vuoi essere
                riconosciuto, aggiungi il tuo nome in fondo al testo.
            </div>

            <div class="editor-label">Il tuo messaggio</div>
            <div id="editor"></div>
            <p class="firma-hint">
                💡 Suggerimento: scrivi il tuo nome in fondo al messaggio se vuoi che
                {{ $socio->nome }} sappia da chi viene.
            </p>
            <p class="error" id="error-vuoto">Il messaggio non può essere vuoto.</p>

            <form method="POST" action="{{ route('badge.messaggio.store', [$evento, $token]) }}" id="form-messaggio">
                @csrf
                <input type="hidden" name="corpo" id="corpo-hidden">
                <button type="submit" class="btn-invia" id="btn-invia">Invia messaggio</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script>
        const quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Scrivi qui il tuo messaggio…',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        document.getElementById('form-messaggio').addEventListener('submit', function (e) {
            const testo = quill.getText().trim();
            if (!testo) {
                e.preventDefault();
                document.getElementById('error-vuoto').style.display = 'block';
                return;
            }
            document.getElementById('error-vuoto').style.display = 'none';
            document.getElementById('corpo-hidden').value = quill.root.innerHTML;
            document.getElementById('btn-invia').disabled = true;
            document.getElementById('btn-invia').textContent = 'Invio in corso…';
        });
    </script>
</body>
</html>
