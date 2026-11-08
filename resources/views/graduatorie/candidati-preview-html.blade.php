<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anteprima graduatoria — {{ $graduatoria->titolo }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; color: #111827; background: #f8fafc; }
    .page { max-width: 1000px; margin: 0 auto; padding: 24px 20px 48px; }

    .header { background: #1e3a5f; color: #fff; border-radius: 10px; padding: 18px 24px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
    .header h1 { font-size: 15px; font-weight: 700; margin-bottom: 5px; letter-spacing: .02em; }
    .header .meta { font-size: 11px; color: #93c5fd; }
    .stamp { font-size: 10px; color: #7cb9f7; white-space: nowrap; text-align: right; padding-top: 2px; }

    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .toolbar h2 { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; }
    .btn-download { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: #fff; background: #15803d; border: none; border-radius: 6px; padding: 7px 14px; text-decoration: none; }
    .btn-download:hover { background: #166534; }

    .wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    thead tr { background: #1e3a5f; }
    thead th { padding: 9px 12px; color: #fff; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; text-align: left; }
    thead th.center { text-align: center; }
    tbody tr { border-bottom: 1px solid #f3f4f6; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 8px 12px; vertical-align: middle; }
    td.pos { width: 48px; text-align: center; color: #1e3a5f; font-weight: 700; }
    td.punteggio { text-align: center; font-weight: 600; color: #1e3a5f; }

    .btn-download.disabled { background: #9ca3af; pointer-events: none; }
    .anomalia { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 12px; margin-bottom: 16px; }

    @media print { body { background: #fff; } .btn-download { display: none; } }
</style>
</head>
<body>
<div class="page">

<div class="header">
    <div>
        <h1>GRADUATORIA — {{ strtoupper($graduatoria->titolo) }}</h1>
        <div class="meta">Prot. <strong>{{ $graduatoria->codice_protocollo }}</strong></div>
    </div>
    <div class="stamp">Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

@if($graduatoria->haCandidatiNonCollegati())
<div class="anomalia">⚠️ Ci sono candidati non collegati a un socio esistente (vedi "Anagrafica non collegata a Socio" in tabella): l'export Excel resta bloccato finché non vengono collegati.</div>
@endif

<div class="toolbar">
    <h2>Elenco candidati</h2>
    @if($graduatoria->haCandidatiNonCollegati())
        <span class="btn-download disabled" title="Export bloccato: completare i collegamenti a socio">↓ Scarica Excel</span>
    @else
        <a href="{{ route('graduatorie.candidati-excel', $graduatoria) }}" class="btn-download">
            ↓ Scarica Excel
        </a>
    @endif
</div>

<div class="wrap">
<table>
    <thead>
        <tr>
            <th class="center">Pos.</th>
            <th>Cognome</th>
            <th>Nome</th>
            <th>Codice fiscale</th>
            <th>Comitato</th>
            <th>Tipo</th>
            <th>Email</th>
            <th>Telefono</th>
            <th class="center">Punteggio</th>
        </tr>
    </thead>
    <tbody>
        @foreach($candidati as $i => $c)
            <tr>
                <td class="pos">{{ $c->posizione_classifica ?? ($i + 1) }}</td>
                <td>{{ $c->cognome }}</td>
                <td>{{ $c->nome }}</td>
                <td>{{ $c->codice_fiscale }}</td>
                <td>{{ $c->comitatoSocio() ?? ($c->comitatoLocale?->nome ?? '—') }}</td>
                <td>{{ $c->tipoSocio() ?? 'Anagrafica non collegata a Socio' }}</td>
                <td>{{ $c->email }}</td>
                <td>{{ $c->telefono ?: '—' }}</td>
                <td class="punteggio">{{ $c->punteggio_finale !== null ? number_format((float) $c->punteggio_finale, 2, ',', '') : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>

@if($esclusi->isNotEmpty())
<div class="toolbar" style="margin-top: 28px;">
    <h2>Esclusi dalla graduatoria</h2>
</div>
<div class="wrap">
<table>
    <thead>
        <tr style="background:#991b1b;">
            <th>Cognome</th>
            <th>Nome</th>
            <th>Codice fiscale</th>
            <th>Comitato</th>
            <th>Tipo</th>
            <th>Email</th>
            <th>Telefono</th>
            <th>Motivo esclusione</th>
        </tr>
    </thead>
    <tbody>
        @foreach($esclusi as $r)
            @php($c = $r['candidato'])
            <tr>
                <td>{{ $c->cognome }}</td>
                <td>{{ $c->nome }}</td>
                <td>{{ $c->codice_fiscale }}</td>
                <td>{{ $c->comitatoSocio() ?? ($c->comitatoLocale?->nome ?? '—') }}</td>
                <td>{{ $c->tipoSocio() ?? 'Anagrafica non collegata a Socio' }}</td>
                <td>{{ $c->email }}</td>
                <td>{{ $c->telefono ?: '—' }}</td>
                <td>{{ $r['motivo'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
@endif

</div>
</body>
</html>
