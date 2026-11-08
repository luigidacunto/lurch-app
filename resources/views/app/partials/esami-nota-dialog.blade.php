{{-- Icona «i» che apre in sola lettura l'annotazione di un docente (PWA: riepilogo itinere del direttore, confronto dei
     laboratori tra docenti). Equivalente della modale del pannello (filament/resources/esame-resource/partials/nota-docente).
     Parametri: $docente, $testo, $titolo. Lo stile è nel partial stesso, una volta per pagina (@once). --}}
@once
<style>
    .nota-i {
        width: 26px; height: 26px; border-radius: 50%; border: 2px solid #2563eb; background: #fff; color: #2563eb;
        font: italic 800 14px Georgia, serif; cursor: pointer; vertical-align: middle; padding: 0;
    }
    /* margin:auto obbligatorio: il reset del layout PWA (* { margin: 0 }) toglie il centramento nativo del <dialog> */
    .nota-dialog { margin: auto; border: none; border-radius: 12px; padding: 16px; width: min(92vw, 480px); box-shadow: 0 10px 30px rgba(0,0,0,.25); }
    .nota-dialog::backdrop { background: rgba(0,0,0,.4); }
    .nota-testa { font-size: 14px; margin-bottom: 10px; color: #111827; }
    .nota-testa small { color: #6b7280; }
    .nota-testo { white-space: pre-line; overflow-wrap: anywhere; font-size: 14px; color: #111827; }
    .nota-chiudi { margin-top: 14px; width: 100%; min-height: 44px; border-radius: 10px; border: none; background: #e5e7eb; font-weight: 700; cursor: pointer; }
</style>
@endonce
<button type="button" class="nota-i" aria-label="Annotazione di {{ $docente }}" onclick="this.nextElementSibling.showModal()">i</button>
<dialog class="nota-dialog" onclick="if (event.target === this) this.close()">
    <div class="nota-testa"><strong>{{ $docente }}</strong><br><small>{{ $titolo }}</small></div>
    <div class="nota-testo">{{ $testo }}</div>
    <button type="button" class="nota-chiudi" onclick="this.closest('dialog').close()">Chiudi</button>
</dialog>
