<!doctype html>
{{--
    Pagina standalone di anteprima di un documento PDF privato, autenticata (middleware
    web+auth sulla rotta) e senza la chrome del pannello. L'autorizzazione vera (chi può
    vedere questo file) la fa il controller che rende questa view.

    Generica e riusabile: qualsiasi Preview*Controller può fare
        return view('documento-preview', ['titolo' => ..., 'rawUrl' => ..., 'scaricaUrl' => ...]);
    dove $rawUrl serve lo stesso file con Content-Disposition: inline e
    $scaricaUrl (opzionale) lo serve con `attachment` per il download esplicito.

    Il PDF viene renderizzato con pdf.js (Mozilla) su <canvas>: l'anteprima è quindi
    indipendente dalle impostazioni/estensioni del browser ("scarica sempre i PDF",
    lettore PDF disattivato, ecc.). Se pdf.js non si carica si ripiega sull'<iframe>
    nativo. Il pulsante "Scarica" resta comunque sempre funzionante.
--}}
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $titolo ?? 'Anteprima documento' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; background: #f1f5f9; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #0f172a; }
        header { display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e2e8f0; flex-shrink: 0; }
        header .titolo { font-weight: 600; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        header .spacer { flex: 1; }
        header a { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; text-decoration: none; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; color: #334155; background: #fff; }
        header a:hover { background: #f8fafc; }
        header a.primario { border-color: #2563eb; color: #2563eb; }
        main { flex: 1; min-height: 0; overflow: auto; padding: 16px 12px; }
        #pagine { display: flex; flex-direction: column; align-items: center; gap: 16px; }
        #pagine canvas { max-width: 100%; height: auto; border-radius: 8px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
        #stato { text-align: center; color: #64748b; font-size: 0.9rem; padding: 24px; }
        iframe { width: 100%; height: 100%; border: none; border-radius: 8px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <header>
        <span class="titolo">{{ $titolo ?? 'Anteprima documento' }}</span>
        <span class="spacer"></span>
        <a href="{{ $scaricaUrl ?? $rawUrl }}" class="primario" download>↓ Scarica</a>
        <a href="#" onclick="chiudiAnteprima(); return false;">Chiudi</a>
    </header>
    <main>
        <div id="pagine"></div>
        <div id="stato">Caricamento anteprima…</div>
    </main>

    {{-- pdf.js 3.x servito localmente da public/vendor/pdfjs (niente CDN: funziona offline).
         Aggiornamento versione: riscaricare pdf.min.js + pdf.worker.min.js da cdnjs. --}}
    <script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
    <script>
        // Incorporata in un <iframe> dentro una modale Filament (vedi anteprima-documento.blade.php):
        // window.close() lì non fa nulla (non è una finestra aperta da script). In quel caso
        // avvisiamo il genitore via postMessage, che chiude la modale al posto nostro.
        function chiudiAnteprima() {
            if (window.self !== window.top) {
                window.parent.postMessage('lurch:chiudi-anteprima-documento', '*');
            } else {
                window.close();
            }
        }

        (function () {
            var rawUrl = @json($rawUrl);
            var pdfjsBase = @json(asset('vendor/pdfjs') . '/');
            var stato = document.getElementById('stato');
            var pagine = document.getElementById('pagine');

            var titolo = @json($titolo ?? 'Anteprima documento');

            function fallbackIframe() {
                var main = document.querySelector('main');
                main.style.padding = '12px';
                main.style.overflow = 'hidden';
                var frame = document.createElement('iframe');
                frame.src = rawUrl + '#view=FitH';
                frame.title = titolo;
                main.innerHTML = '';
                main.appendChild(frame);
            }

            if (!window.pdfjsLib) { fallbackIframe(); return; }

            pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsBase + 'pdf.worker.min.js';

            pdfjsLib.getDocument({ url: rawUrl, withCredentials: true }).promise.then(function (pdf) {
                stato.remove();
                var scale = Math.min(2, (window.devicePixelRatio || 1) * 1.5);
                var chain = Promise.resolve();
                for (var n = 1; n <= pdf.numPages; n++) {
                    (function (num) {
                        chain = chain.then(function () {
                            return pdf.getPage(num).then(function (page) {
                                var viewport = page.getViewport({ scale: scale });
                                var canvas = document.createElement('canvas');
                                canvas.width = viewport.width;
                                canvas.height = viewport.height;
                                canvas.style.width = (viewport.width / scale) + 'px';
                                pagine.appendChild(canvas);
                                return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                            });
                        });
                    })(n);
                }
                return chain;
            }).catch(function () {
                fallbackIframe();
            });
        })();
    </script>
</body>
</html>
