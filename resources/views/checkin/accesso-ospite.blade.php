<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in Ospite — {{ $evento->nome }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .header { background: #7c3aed; color: #fff; padding: 14px 16px; }
        .header h1 { margin: 0; font-size: 1rem; font-weight: 700; }
        .header p  { margin: 4px 0 0; font-size: .8rem; opacity: .8; }
        .card { background: #fff; border-radius: 12px; margin: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .nome { font-size: 1.3rem; font-weight: 700; color: #111; margin: 0 0 2px; }
        .cf   { font-size: .8rem; color: #6b7280; font-family: monospace; margin: 0 0 4px; }
        .tipo-badge { display: inline-block; font-size: .72rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; background: #ede9fe; color: #5b21b6; }
        .stato-box { border-radius: 10px; padding: 10px 14px; margin: 12px 0; font-size: .9rem; font-weight: 600; }
        .stato-dentro { background: #dcfce7; color: #15803d; }
        .stato-fuori  { background: #fee2e2; color: #b91c1c; }
        .stato-mai    { background: #f3f4f6; color: #6b7280; }
        .azione-box { border-radius: 10px; padding: 12px 14px; margin: 12px 0; border: 2px solid; }
        .azione-entrata { border-color: #16a34a; background: #f0fdf4; }
        .azione-uscita  { border-color: #dc2626; background: #fef2f2; }
        .azione-label { font-size: .8rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: .05em; }
        .azione-tipo  { font-size: 1.2rem; font-weight: 800; margin: 2px 0 10px; }
        .entrata-color { color: #15803d; }
        .uscita-color  { color: #b91c1c; }
        input[type=datetime-local], input[type=text] {
            width: 100%; padding: 10px 12px; border: 1.5px solid #d1d5db; border-radius: 8px;
            font-size: .95rem; margin-top: 4px; background: #fff; color: #111;
        }
        input:focus { outline: none; border-color: #2563eb; }
        label { font-size: .82rem; font-weight: 600; color: #374151; }
        .btn-primary { display: block; width: 100%; padding: 14px; background: #2563eb; color: #fff;
            border: none; border-radius: 10px; font-size: 1.05rem; font-weight: 700; cursor: pointer; margin-top: 12px; }
        .btn-primary.verde { background: #16a34a; }
        .btn-primary.rosso { background: #dc2626; }
        .btn-secondary { display: block; width: 100%; padding: 12px; background: #f9fafb; color: #374151;
            border: 1.5px solid #d1d5db; border-radius: 10px; font-size: .95rem; font-weight: 600;
            cursor: pointer; margin-top: 8px; text-align: center; text-decoration: none; }
        .storico-item { display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: .85rem; }
        .storico-item:last-child { border-bottom: none; }
        .pill-in  { background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 20px; font-weight: 600; font-size: .75rem; }
        .pill-out { background: #fee2e2; color: #b91c1c; padding: 2px 8px; border-radius: 20px; font-weight: 600; font-size: .75rem; }
        .section-title { font-size: .75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .08em; margin: 16px 0 8px; }
        .alert-success { background: #dcfce7; color: #15803d; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .alert-error   { background: #fee2e2; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .form-row { margin-bottom: 12px; }
        .checkbox-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 8px; font-size: .88rem; }
        .checkbox-row input { width: auto; margin-top: 2px; flex-shrink: 0; }
        .checkbox-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 6px; }
        .checkbox-grid label { font-weight: 400; font-size: .83rem; display: flex; align-items: center; gap: 6px; }
        details summary { cursor: pointer; list-style: none; }
        details summary::-webkit-details-marker { display: none; }
        .details-header { font-size: .8rem; font-weight: 700; color: #6b7280; text-transform: uppercase;
            letter-spacing: .06em; padding: 10px 0; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f3f4f6; }
        details[open] .details-chevron { transform: rotate(90deg); }
        .details-chevron { display: inline-block; transition: transform .2s; }
    </style>
</head>
<body>

<div class="header">
    <h1>Check-in / Check-out Ospite</h1>
    <p>{{ $evento->nome }}</p>
</div>

<div style="padding: 12px;">

    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    {{-- Card ospite --}}
    <div class="card">
        <p class="nome">{{ $ospite->nome_completo }}</p>
        @if($ospite->cf)
            <p class="cf">{{ $ospite->cf }}</p>
        @endif
        <span class="tipo-badge">Ospite</span>
    </div>

    {{-- Stato attuale --}}
    <div class="card" style="padding: 12px 16px;">
        @if($ultimoAccesso)
            <div class="stato-box {{ $ultimoAccesso->tipo === 'entrata' ? 'stato-dentro' : 'stato-fuori' }}">
                {{ $ultimoAccesso->tipo === 'entrata' ? '✓ Attualmente DENTRO al campo' : '✗ Attualmente FUORI dal campo' }}
                <div style="font-size:.78rem;font-weight:400;margin-top:2px;">
                    Ultimo: {{ $ultimoAccesso->timestamp_accesso->format('d/m/Y H:i') }}
                    — {{ $ultimoAccesso->operatore->name ?? 'operatore' }}
                </div>
            </div>
        @else
            <div class="stato-box stato-mai">Nessun accesso registrato</div>
        @endif
    </div>

    {{-- Form registrazione accesso --}}
    <div class="card">
        <form method="POST" action="{{ route('checkin.accesso-ospite', ['evento' => $evento->id, 'ospite' => $ospite->id]) }}">
            @csrf

            <div class="azione-box azione-{{ $tipoSuccessivo }}">
                <div class="azione-label">Stai registrando</div>
                <div class="azione-tipo {{ $tipoSuccessivo }}-color">{{ strtoupper($tipoSuccessivo) }}</div>
                <div class="form-row">
                    <label>Data e ora</label>
                    <input type="datetime-local" name="timestamp_accesso"
                        value="{{ now()->format('Y-m-d\TH:i') }}" required>
                </div>
            </div>

            {{-- Sezione dati — solo al primo check-in (prima entrata) --}}
            @if($primoCheckin)
                <details open>
                    <summary>
                        <div class="details-header">
                            <span class="details-chevron">▶</span>
                            Dati ospite
                            <span style="color:#dc2626;margin-left:2px;">*</span>
                        </div>
                    </summary>

                    <div style="padding: 12px 0;">

                        <div class="section-title">Alimentazione</div>
                        <div class="form-row">
                            <label>Allergeni</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['allergeniList'] as $all)
                                    <label>
                                        <input type="checkbox" name="allergeni[]" value="{{ $all->nome }}">
                                        {{ $all->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Intolleranze</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['intolleranzeList'] as $intolleranza)
                                    <label>
                                        <input type="checkbox" name="diete[]" value="{{ $intolleranza->nome }}">
                                        {{ $intolleranza->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Diete particolari</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['dieteList'] as $dieta)
                                    <label>
                                        <input type="checkbox" name="diete[]" value="{{ $dieta->nome }}">
                                        {{ $dieta->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Esclusioni alimentari</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['esclusioniList'] as $esclusione)
                                    <label>
                                        <input type="checkbox" name="diete[]" value="{{ $esclusione->nome }}">
                                        {{ $esclusione->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Note alimentari</label>
                            <input type="text" name="note_extra" maxlength="50" placeholder="es. kiwi, fragole (solo alimentare, max 50 caratteri)">
                        </div>

                        @if(!empty($sezioni))
                            @php
                                $sezioniPerGiorno = collect($sezioni)->groupBy('data');
                            @endphp
                            <div class="section-title">Pasti e pernottamenti</div>
                            @foreach($sezioniPerGiorno as $data => $voci)
                                <div style="margin-bottom:10px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                    <div style="background:#f9fafb;padding:6px 12px;font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">
                                        {{ \Carbon\Carbon::parse($data)->translatedFormat('l j F') }}
                                    </div>
                                    <div style="padding:8px 12px;">
                                        @foreach($voci as $sezione)
                                            @php
                                                $emoji = match($sezione['tipo']) {
                                                    'colazione' => '☕',
                                                    'pranzo'    => '🍽',
                                                    'cena'      => '🌙',
                                                    'pernotto'  => '🛏',
                                                    default     => '•',
                                                };
                                            @endphp
                                            <div class="checkbox-row" style="margin-bottom:6px;">
                                                <input type="checkbox" name="{{ $sezione['chiave'] }}" value="1">
                                                <span>{{ $emoji }} {{ ucfirst($sezione['tipo']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <div class="section-title">Consensi</div>
                        <div class="checkbox-row">
                            <input type="checkbox" name="privacy_accettata" value="1" required>
                            <span>Informativa privacy accettata *</span>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" name="consenso_salute_accettata" value="1">
                            <span>Consenso trattamento dati salute</span>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" name="foto_video_accettata" value="1">
                            <span>Consenso foto/video</span>
                        </div>

                    </div>
                </details>
            @endif

            <button type="submit" class="btn-primary {{ $tipoSuccessivo === 'entrata' ? 'verde' : 'rosso' }}">
                Conferma {{ strtoupper($tipoSuccessivo) }}
            </button>
        </form>

        <a href="{{ route('checkin.ricerca', $evento) }}" class="btn-secondary">← Nuova ricerca</a>
    </div>

    {{-- Storico oggi --}}
    @if($storiciOggi->isNotEmpty())
        <div class="card">
            <div class="section-title">Storico di oggi</div>
            @foreach($storiciOggi as $acc)
                <div class="storico-item">
                    <div>
                        <span class="pill-{{ $acc->tipo === 'entrata' ? 'in' : 'out' }}">
                            {{ $acc->tipo === 'entrata' ? 'ENTRATA' : 'USCITA' }}
                        </span>
                        <span style="margin-left:8px;color:#374151;">
                            {{ $acc->timestamp_accesso->format('H:i') }}
                        </span>
                    </div>
                    <span style="color:#9ca3af;font-size:.78rem;">{{ $acc->operatore->name ?? '—' }}</span>
                </div>
            @endforeach
        </div>
    @endif

</div>
</body>
</html>
