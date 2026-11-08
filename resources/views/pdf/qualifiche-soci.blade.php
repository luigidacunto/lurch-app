<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; background: #fff; padding: 10mm 12mm; }

    /* Intestazione filtri */
    .filtri { width: 100%; border-collapse: collapse; margin-bottom: 8px; background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 8px; }
    .filtri td { padding: 4px 8px; vertical-align: top; }
    .filtri .lbl { font-weight: bold; color: #1e3a5f; white-space: nowrap; width: 1px; }
    .filtri .val { color: #334155; }
    .filtri .val.escluse { color: #b45309; }
    .filtri .totale { font-size: 9px; font-weight: bold; color: #1e3a5f; }
    .filtri-sep { border-top: 1px solid #e2e8f0; }

    /* Summary */
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: 1px solid #e5e7eb; }
    .summary td { text-align: center; padding: 4px 8px; border-right: 1px solid #e5e7eb; }
    .summary td:last-child { border-right: none; }
    .summary .val { font-size: 13px; font-weight: bold; color: #1e3a5f; line-height: 1.1; display: block; }
    .summary .val.green { color: #15803d; }
    .summary .val.amber { color: #b45309; }
    .summary .val.red   { color: #dc2626; }
    .summary .lbl { font-size: 7px; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; display: block; margin-top: 1px; }

    /* Tabella principale */
    table.main { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.main thead tr { background: #1e3a5f; color: #fff; }
    table.main thead th { padding: 5px 5px; text-align: left; font-weight: bold; font-size: 7.5px; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    table.main thead th.center { text-align: center; }
    table.main tbody tr { border-bottom: 1px solid #f0f0f0; }
    table.main tbody tr:nth-child(even) { background: #f8fafc; }
    table.main tbody td { padding: 3px 5px; vertical-align: middle; }
    table.main tbody td.nr { width: 20px; color: #aaa; text-align: center; font-size: 7px; }
    table.main tbody td.center { text-align: center; }
    table.main tbody td.mono { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; letter-spacing: .02em; }
    table.main tbody td.rel { text-align: center; font-size: 7.5px; color: #555; }
    table.main tbody td.stato-valida   { color: #15803d; font-weight: bold; text-align: center; }
    table.main tbody td.stato-scadenza { color: #b45309; text-align: center; }
    table.main tbody td.stato-scaduta  { color: #dc2626; text-align: center; }
    table.main tbody td.stato-nessuna  { color: #9ca3af; text-align: center; }

    .stamp { font-size: 7px; color: #aaa; text-align: right; margin-bottom: 5px; }
    .footer { margin-top: 8px; font-size: 7px; color: #bbb; border-top: 1px solid #eee; padding-top: 4px; display: table; width: 100%; }
    .footer-l { display: table-cell; }
    .footer-r { display: table-cell; text-align: right; }
</style>
</head>
<body>

@php
    $oggi    = \Carbon\Carbon::today();
    $totale  = $soci->count();
    $scadute = 0; $inScadenza = 0; $valide = 0; $nessuna = 0;

    $primaQualId  = $qualificheRichieste[0] ?? null;
    $primaQualIds = $primaQualId ? ($qualificheIds[$primaQualId] ?? []) : [];

    function statoQualificaPdf($sq, $regioneId, $oggi): string {
        if (! $sq || ! $sq->scadenza_calcolata) return 'nessuna';
        $regola   = $sq->catalogoCorso->scadenzaPerRegione($regioneId);
        $scadenza = $sq->scadenza_calcolata;
        if ($regola['tipo'] === 'mesi_esatti') {
            if ($scadenza->lt($oggi)) return 'scaduta';
            return $oggi->diffInMonths($scadenza) > 6 ? 'valida' : 'in_scadenza';
        }
        if ($regola['tipo'] === 'anno_solare') {
            $diff = $scadenza->year - $oggi->year;
            if ($diff > 0) return 'valida';
            if ($diff === 0) return 'in_scadenza';
            return 'scaduta';
        }
        return 'nessuna';
    }

    function tipoRelazionePdf($socio, $localeId): string {
        if (! $localeId) return '—';
        if ($socio->comitato_locale_dipendente_id === $localeId) return 'Dipendente';
        if ($socio->comitato_locale_estensione_id === $localeId) return 'In estensione';
        return 'Volontario';
    }

    if ($primaQualId) {
        foreach ($soci as $socio) {
            $sq = $socio->qualificheSoci->first(fn ($s) => in_array($s->catalogo_corso_id, $primaQualIds));
            match (statoQualificaPdf($sq, $socio->comitatoRegionaleEffettivoId(), $oggi)) {
                'scaduta'     => $scadute++,
                'in_scadenza' => $inScadenza++,
                'valida'      => $valide++,
                default       => $nessuna++,
            };
        }
    }

    // Testo qualifiche richieste
    $testoRichieste = '';
    if (! empty($qualificheRichieste)) {
        $op = $operatore === 'or' ? 'almeno una tra' : 'tutte';
        $testoRichieste = $op . ': ' . $corsiRichiesti->pluck('codice')->join(', ');
    } else {
        $testoRichieste = 'nessuna';
    }

    // Testo escluse
    $testoEscluse = ! empty($qualificheEscluse) ? $corsiEsclusi->pluck('codice')->join(', ') : '';

    // Testo area
    $testoRegione = $regione?->nome ?? 'Tutte';
    $testoLocale  = $locale?->nome  ?? 'Tutti';

    // Flag inclusioni
    $flags = [];
    if ($includiEstensioni) $flags[] = 'estensioni';
    if ($includiDipendenti) $flags[] = 'dipendenti';
    $testoInclusi = ! empty($flags) ? implode(', ', $flags) : '';

    // Filtro giovani
    $testoGiovani = match ($filtroGiovani ?? null) {
        'attuale'  => 'solo età attuale < 32 anni',
        'ingresso' => 'solo entrati come giovani (< 32 anni all\'ingresso)',
        default    => '',
    };
@endphp

<div class="stamp">Stampato il {{ now()->format('d/m/Y H:i') }}</div>

{{-- Intestazione filtri (stessa struttura dell'Excel) --}}
<table class="filtri">
    <tr>
        <td class="lbl">Qualifiche richieste:</td>
        <td class="val">{{ $testoRichieste }}</td>
        @if($testoEscluse)
        <td class="lbl" style="border-left:1px solid #e2e8f0;">Soci PRIVI DI:</td>
        <td class="val escluse">{{ $testoEscluse }}</td>
        @endif
    </tr>
    <tr class="filtri-sep">
        <td class="lbl">Regione:</td>
        <td class="val">{{ $testoRegione }}</td>
        <td class="lbl" style="border-left:1px solid #e2e8f0;">Comitato Locale:</td>
        <td class="val">{{ $testoLocale }}{{ $testoInclusi ? ' · Inclusi: ' . $testoInclusi : '' }}</td>
    </tr>
    @if($testoGiovani)
    <tr class="filtri-sep">
        <td class="lbl">Giovani:</td>
        <td class="val" colspan="3">{{ $testoGiovani }}</td>
    </tr>
    @endif
    <tr class="filtri-sep">
        <td class="totale" colspan="4">Totale soci: {{ $totale }}</td>
    </tr>
</table>

@if($primaQualId)
<table class="summary">
    <tr>
        <td><span class="val">{{ $totale }}</span><span class="lbl">Trovati</span></td>
        <td><span class="val green">{{ $valide }}</span><span class="lbl">Valide</span></td>
        <td><span class="val amber">{{ $inScadenza }}</span><span class="lbl">In scadenza</span></td>
        <td><span class="val red">{{ $scadute }}</span><span class="lbl">Scadute</span></td>
        @if($nessuna > 0)
        <td><span class="val" style="color:#9ca3af;">{{ $nessuna }}</span><span class="lbl">Nessuna scad.</span></td>
        @endif
    </tr>
</table>
@endif

<table class="main">
    <thead>
        <tr>
            <th class="center">NR</th>
            <th>COGNOME</th>
            <th>NOME</th>
            <th class="center">CODICE FISCALE</th>
            <th>COMITATO / SOTTOSEDE</th>
            <th class="center">TIPO REL.</th>
            <th class="center">NASCITA</th>
            <th class="center">ETÀ</th>
            <th class="center">INGRESSO</th>
            @foreach($qualificheRichieste as $qid)
                @php $qCorso = $corsiRichiesti[$qid] ?? null; $cod = $qCorso?->codice ?? "#{$qid}"; @endphp
                <th class="center">QUALIFICA<br>({{ $cod }})</th>
                <th class="center">SCADENZA<br>({{ $cod }})</th>
                <th class="center">STATO<br>({{ $cod }})</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($soci as $index => $socio)
            <tr>
                <td class="nr">{{ $index + 1 }}</td>
                <td><strong>{{ $socio->cognome }}</strong></td>
                <td>{{ $socio->nome }}</td>
                <td class="center mono">{{ $socio->codice_fiscale }}</td>
                <td>{{ $socio->comitato_display }}</td>
                <td class="rel">{{ tipoRelazionePdf($socio, $localeId) }}</td>
                <td class="center">{{ $socio->data_nascita?->format('d/m/Y') ?? '—' }}</td>
                <td class="center">{{ $socio->data_nascita ? (int) $socio->data_nascita->diffInYears($oggi) : '—' }}</td>
                <td class="center">{{ $socio->volontario_da?->format('d/m/Y') ?? '—' }}</td>
                @foreach($qualificheRichieste as $qid)
                    @php
                        $ids        = $qualificheIds[$qid] ?? [];
                        $sq         = $socio->qualificheSoci->first(fn ($s) => in_array($s->catalogo_corso_id, $ids));
                        $stato      = statoQualificaPdf($sq, $socio->comitatoRegionaleEffettivoId(), $oggi);
                        $statoClass = match ($stato) {
                            'valida'      => 'stato-valida',
                            'in_scadenza' => 'stato-scadenza',
                            'scaduta'     => 'stato-scaduta',
                            default       => 'stato-nessuna',
                        };
                        $statoLabel = match ($stato) {
                            'valida'      => 'Valida',
                            'in_scadenza' => 'In scadenza',
                            'scaduta'     => 'Scaduta',
                            default       => '—',
                        };
                        $scadenza   = $sq?->scadenza_calcolata;
                        $codiceQual = $sq?->catalogoCorso?->codice ?? '—';
                    @endphp
                    <td class="center">{{ $codiceQual }}</td>
                    <td class="center">{{ $scadenza ? $scadenza->format('d/m/Y') : '—' }}</td>
                    <td class="{{ $statoClass }}">{{ $statoLabel }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    <div class="footer-l">LURCH — Logistica Unificata Registrazione Corsi e Hospitality</div>
    <div class="footer-r">{{ $regione?->nome ?? '' }}{{ $locale ? ' · ' . $locale->nome : '' }}</div>
</div>

</body>
</html>
