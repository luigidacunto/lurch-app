{{-- Anteprima documento dentro un modale Filament.
     Il contenuto vero (rendering PDF con pdf.js, download, fallback) sta nella pagina
     standalone `documento-preview.blade.php`: qui la si incorpora in un <iframe> così
     l'anteprima non dipende dal lettore PDF del browser. $url punta a quella pagina
     (rotta autenticata, il controller fa il proprio controllo permessi per tipo di file). --}}
<div style="margin-bottom:8px;">
    <a href="{{ $url }}" target="_blank" rel="noopener" style="font-size:13px;color:#2563eb;text-decoration:underline;">
        ↗ Apri a pagina intera
    </a>
</div>
<div style="height:75vh;">
    <iframe src="{{ $url }}" style="width:100%;height:100%;border:none;border-radius:8px;" title="Anteprima documento"></iframe>
</div>

{{-- Il pulsante "Chiudi" dentro l'iframe (documento-preview.blade.php) non può chiudere la modale
     da solo: avvisa qui via postMessage, e qui chiudiamo davvero la modale invocando close()
     sul suo componente Alpine (stesso metodo usato dal bottone "Annulla" nativo di Filament). --}}
<script>
    (function () {
        var dialog = document.currentScript.closest('[role="dialog"]');

        window.addEventListener('message', function chiudiSuMessaggio(event) {
            if (event.data !== 'lurch:chiudi-anteprima-documento') return;

            if (!dialog || !document.body.contains(dialog)) {
                window.removeEventListener('message', chiudiSuMessaggio);
                return;
            }

            window.Alpine?.$data(dialog)?.close();
        });
    })();
</script>
