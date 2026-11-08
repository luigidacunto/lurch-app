@php
    $badgeStato = match ($record->stato) {
        'confermato' => ['Confermata', '#15803d', '#f0fdf4', '#bbf7d0'],
        'rifiutato'  => ['Rifiutata', '#b91c1c', '#fef2f2', '#fecaca'],
        default      => ['In attesa', '#b45309', '#fffbeb', '#fde68a'],
    };

    $pill = fn (string $testo, string $colore, string $sfondo, string $bordo) =>
        '<span style="display:inline-flex;align-items:center;border-radius:9999px;padding:2px 9px;'
        . 'font-size:11px;font-weight:600;color:' . $colore . ';background:' . $sfondo . ';border:1px solid ' . $bordo . ';">'
        . e($testo) . '</span>';
@endphp

<div style="font-size:13px;color:#374151;">

    <div style="margin-bottom:18px;">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;margin-bottom:8px;">Dati anagrafici</p>
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;">
            <div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;">Nome e cognome</p>
                <p style="font-weight:600;color:#111827;margin:0;">{{ $record->cognome }} {{ $record->nome }}</p>
            </div>
            <div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;">Codice fiscale</p>
                <p style="font-family:monospace;color:#111827;margin:0;">{{ $record->codice_fiscale }}</p>
            </div>
            <div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;">Telefono</p>
                <p style="color:#111827;margin:0;">{{ $record->telefono ?: '—' }}</p>
            </div>
            <div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;">Comitato regionale</p>
                <p style="color:#111827;margin:0;">{{ $record->comitatoRegionale?->nome ?? '—' }}</p>
            </div>
            <div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;">Comitato locale</p>
                <p style="color:#111827;margin:0;">{{ $record->comitatoLocale?->nome ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div style="margin-bottom:18px;">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;margin-bottom:8px;">Disponibilità</p>
        <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            @foreach(collect($record->disponibilita)->sortBy([['data', 'asc'], ['porzione', 'asc']])->groupBy('data') as $data => $slots)
                <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding:9px 14px;{{ $loop->last ? '' : 'border-bottom:1px solid #f3f4f6;' }}">
                    <span style="font-weight:600;color:#111827;">{{ \Carbon\Carbon::parse($data)->translatedFormat('l j F') }}</span>
                    <span style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach($slots as $s)
                            @php
                                $testo = ucfirst($s['porzione']) . ' · ' . ($s['tipo'] === 'docente' ? 'Docenza' : 'Logistica');
                                $colori = $s['tipo'] === 'docente' ? ['#7c3aed', '#f5f3ff', '#ddd6fe'] : ['#0f766e', '#f0fdfa', '#99f6e4'];
                            @endphp
                            {!! $pill($testo, ...$colori) !!}
                        @endforeach
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @if($record->haDisponibilitaDocente())
        <div style="margin-bottom:18px;">
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;margin-bottom:8px;">Corsi in cui farà docenza</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($record->corsi as $corso)
                    <span style="display:inline-flex;align-items:center;gap:4px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:500;color:#b91c1c;">
                        {{ $corso->nome }}
                        @if($corso->numero_protocollo)
                            <span style="color:#f0a3a3;">· Prot. {{ $corso->numero_protocollo }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;margin-bottom:8px;">Stato candidatura</p>
        <div style="display:flex;align-items:center;gap:10px;">
            {!! $pill($badgeStato[0], $badgeStato[1], $badgeStato[2], $badgeStato[3]) !!}
            <span style="font-size:12px;color:#6b7280;">
                Candidato il {{ $record->created_at->format('d/m/Y H:i') }}
                @if($record->stato === 'confermato')
                    — confermato il {{ $record->confermato_at?->format('d/m/Y H:i') }}
                @endif
            </span>
        </div>
    </div>
</div>
