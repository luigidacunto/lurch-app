@props([
    'slot',
    'label',
    'action',
    'accept' => '.pdf',
    'caricato' => false,
    'verificato' => false,
    'notaVerificato' => null,
    'richiesto' => false,   // true = documento dovuto anche se non ancora caricato (primo upload tardivo)
    'dicitura' => null,     // testo libero scritto dall'admin (documento aggiuntivo)
])

{{-- Riga di un documento caricato con possibilità di sostituzione finché non verificato.
     Condivisa da form pubblici (confermata) e gemelli PWA — parità di comportamento. --}}
@if($caricato || $richiesto)
    <div style="border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; margin-top:10px; text-align:left; background:#fff;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
            <span style="font-size:13px; font-weight:600; color:#374151;">{{ $label }}</span>
            @if(! $caricato)
                <span style="font-size:11px; font-weight:600; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:3px 8px; white-space:nowrap;">Da caricare</span>
            @elseif($verificato)
                <span style="font-size:11px; font-weight:600; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:3px 8px; white-space:nowrap;">✓ Verificato</span>
            @else
                <span style="font-size:11px; font-weight:600; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:3px 8px; white-space:nowrap;">In verifica</span>
            @endif
        </div>

        @if($dicitura)
            <p style="font-size:12px; color:#374151; margin-top:6px; white-space:pre-line; overflow-wrap:anywhere;">{{ $dicitura }}</p>
        @endif

        @if($verificato)
            <p style="font-size:11px; color:#9ca3af; margin-top:6px;">
                Documento verificato dalla segreteria{{ $notaVerificato ? ' ' . $notaVerificato : '' }}: non è più modificabile. Per correggerlo contatta la segreteria dell'evento.
            </p>
        @else
            <p style="font-size:11px; color:#6b7280; margin-top:6px; margin-bottom:8px;">
                @if($caricato)
                    Hai caricato il file sbagliato? Puoi sostituirlo finché la segreteria non lo verifica — senza rifare tutto il form.
                @else
                    Questo documento è richiesto per il tuo corso: caricalo qui, senza rifare il form.
                @endif
            </p>
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data"
                  style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                @csrf
                <input type="file" name="documento" accept="{{ $accept }}" required
                       style="font-size:12px; max-width:100%;">
                <button type="submit"
                        style="font-size:12px; font-weight:600; color:#fff; background:#4b5563; border:none; border-radius:7px; padding:7px 14px; cursor:pointer; white-space:nowrap;">
                    {{ $caricato ? 'Sostituisci' : 'Carica' }}
                </button>
            </form>
            @error('documento')
                <p style="font-size:11px; color:#b91c1c; margin-top:6px;">{{ $message }}</p>
            @enderror
        @endif
    </div>
@endif
