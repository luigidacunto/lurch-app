{{-- Anteprima dell'email del questionario: intestazione e corpo generato dalla stessa Mailable dell'invio (in un iframe, così gli stili dell'email non si mescolano al pannello). --}}
<div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; font-size:13px;">
    <div style="background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:12px 16px; display:flex; flex-direction:column; gap:4px;">
        <div style="display:flex; gap:8px;">
            <span style="color:#9ca3af; min-width:60px;">Da:</span>
            @if($da)
                <span style="color:#374151;">{{ $da }}</span>
            @else
                <span style="color:#b91c1c;">posta in uscita della regione non configurata: l'invio è bloccato</span>
            @endif
        </div>
        <div style="display:flex; gap:8px;">
            <span style="color:#9ca3af; min-width:60px;">A:</span>
            <span style="color:#374151;">{{ $a }}</span>
        </div>
        <div style="display:flex; gap:8px;">
            <span style="color:#9ca3af; min-width:60px;">Oggetto:</span>
            <span style="color:#111827; font-weight:600;">{{ $oggetto }}</span>
        </div>
    </div>
    <iframe srcdoc="{{ $html }}" sandbox="allow-same-origin" title="Testo dell'email"
            style="display:block; width:100%; height:640px; border:0; background:#fff;"
            onload="const h = this.contentDocument.documentElement.scrollHeight; if (h > 100) this.style.height = (h + 4) + 'px'"></iframe>
</div>

<div style="margin-top:12px; padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; font-size:12px; color:#1e3a8a; line-height:1.6;">
    Esempio con i dati di <strong>{{ $a }}</strong> ({{ $selezionato ? 'primo dei selezionati' : 'primo iscritto con email' }}): ogni iscritto riceve la stessa email con il proprio nome.
    @if($conEsame)
        <br>Frase sui risultati dell'esame in questo esempio: {{ ['test' => 'test scritto', 'esito' => 'esito finale', 'entrambi' => 'test scritto ed esito finale'][$frase_esame] ?? 'nessuna (niente di pubblicato)' }}. Ognuno riceve la frase su ciò che ha di pubblicato (test scritto, esito finale o tutti e due); chi non ha nulla di pubblicato, i ritirati e i non ammessi ricevono solo l'invito al questionario.
        @if($selezionato)
            La riceveranno <strong>{{ $nConFrase }}</strong> dei {{ $nSelezionati }} selezionati.
        @endif
    @endif
</div>
