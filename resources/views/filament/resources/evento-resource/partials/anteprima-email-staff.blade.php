<div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; font-size:13px;">
    {{-- Header simulato email --}}
    <div style="background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:12px 16px; display:flex; flex-direction:column; gap:4px;">
        <div style="display:flex; gap:8px;">
            <span style="color:#9ca3af; min-width:60px;">Da:</span>
            <span style="color:#374151;">{{ config('mail.from.name') }} &lt;{{ config('mail.from.address') }}&gt;</span>
        </div>
        <div style="display:flex; gap:8px;">
            <span style="color:#9ca3af; min-width:60px;">Oggetto:</span>
            <span style="color:#111827; font-weight:600;">{{ $soggetto }}</span>
        </div>
    </div>

    {{-- Corpo email renderizzato --}}
    <div style="padding:0; background:#f3f4f6;">
        <div style="max-width:100%; margin:0 auto; background:#ffffff;">
            <div style="background:#dc2626; padding:18px 24px;">
                <div style="color:#ffffff; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.06em;">Croce Rossa Italiana</div>
            </div>
            <div style="padding:24px; color:#374151; font-size:14px; line-height:1.7;">
                {!! $corpo !!}
            </div>
            <div style="background:#f9fafb; border-top:1px solid #e5e7eb; padding:14px 24px; font-size:11px; color:#9ca3af; text-align:center;">
                Questa email è stata generata automaticamente — si prega di non rispondere direttamente.<br>
                Croce Rossa Italiana · {{ $comitatoNome }}
            </div>
        </div>
    </div>
</div>

@if(!$haStaff)
    <div style="margin-top:12px; padding:10px 14px; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; font-size:12px; color:#92400e;">
        ⚠ Nessun membro staff caricato — i segnaposto <code>{nome}</code> e <code>{cognome}</code> appaiono come generici.
    </div>
@else
    <div style="margin-top:12px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; font-size:12px; color:#166534;">
        ✓ Anteprima basata sui dati di <strong>{{ $nomeStaff }}</strong> (primo membro staff in elenco).
    </div>
@endif
