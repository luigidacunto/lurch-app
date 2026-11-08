{{-- Modello "Presa visione del Presidente" per lo staff Logistica.
     $iscrizione (IscrizioneStaff) valorizzato → precompilato coi dati del form staff;
     null → modello generale vuoto (tutte le righe da compilare a mano).
     Reso in PDF da dompdf (ModelloNullaostaStaffController). Ricalca nella sostanza
     .local/Presa visione per staff.docx.
     NB: dompdf ha supporto flex/grid scarso — solo tabelle e inline-block. --}}
@php
    $iscrizione = $iscrizione ?? null;
    $socio    = $iscrizione?->partecipante;
    $evento   = $iscrizione?->evento;
    $comitato = $socio?->comitatoLocale?->nome;

    $inizio = $evento?->data_inizio?->locale('it')->isoFormat('D MMMM YYYY');
    $fine   = $evento?->data_fine?->locale('it')->isoFormat('D MMMM YYYY');
    $periodo = $inizio && $fine && $inizio !== $fine
        ? "dal {$inizio} al {$fine}"
        : ($inizio ?: null);

    $sede = trim(collect([$evento?->sede, $evento?->indirizzo])->filter()->implode(' — ')) ?: null;

    $volontario = trim(($socio?->cognome ?? '') . ' ' . ($socio?->nome ?? '')) ?: null;
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #111; background: #fff; padding: 24mm 22mm; line-height: 1.7; }
    h1 { font-size: 15px; text-align: center; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
    .oggetto { text-align: center; font-weight: bold; margin-bottom: 24px; font-size: 10.5px; }
    p { margin-bottom: 14px; text-align: justify; }
    .fill { display: inline-block; border-bottom: 1px solid #111; min-width: 45mm; line-height: 1.2; padding: 0 3px; }
    .fill.sm { min-width: 32mm; }
    .fill.lg { min-width: 70mm; }
    .dichiaro { text-align: center; font-weight: bold; letter-spacing: .15em; margin: 20px 0 12px; }
    table.decl { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.decl td { vertical-align: top; padding: 4px 0; }
    table.decl td.b { width: 16px; }
    table.firma { width: 100%; border-collapse: collapse; margin-top: 55px; }
    table.firma td { width: 50%; padding-top: 3px; border-top: 1px solid #111; font-size: 9.5px; color: #444; text-align: center; }
    table.firma td:first-child { padding-right: 24px; border-right: 0; }
    table.firma tr.sp td { border: 0; height: 34px; }
</style>
</head>
<body>

<h1>Presa visione del Presidente</h1>
<p class="oggetto">OGGETTO: presa visione del Presidente per la partecipazione di un volontario come staff</p>

<p>
    Io sottoscritto/a <span class="fill lg">&nbsp;</span>,
    Presidente del <span class="fill lg">{{ $comitato }}</span>,
    compilo il presente modulo di presa visione relativo al/alla volontario/a
    <span class="fill lg">{{ $volontario }}</span>,
    codice fiscale
    <span class="fill sm">{{ $socio?->codice_fiscale }}</span>.
</p>

<p class="dichiaro">DICHIARO</p>

<table class="decl">
    <tr>
        <td class="b">&bull;</td>
        <td>
            di essere a conoscenza che il/la volontario/a parteciperà come staff
            @if($evento?->nome)all'evento «{{ $evento->nome }}»@else all'evento <span class="fill lg">&nbsp;</span>@endif
            @if($sede) presso {{ $sede }}@elseif(!$evento) presso <span class="fill">&nbsp;</span>@endif
            @if($periodo) {{ $periodo }}@elseif(!$evento) nelle date <span class="fill">&nbsp;</span>@endif;
        </td>
    </tr>
    <tr><td class="b">&bull;</td><td>di essere a conoscenza del fatto che non saranno previsti rimborsi per il carburante;</td></tr>
    <tr><td class="b">&bull;</td><td>di essere a conoscenza che al/alla volontario/a sarà garantito eventuale vitto e alloggio;</td></tr>
    <tr><td class="b">&bull;</td><td>di confermare che il/la volontario/a è in regola con la formazione inerente la salute e sicurezza del volontario come previsto dal regolamento;</td></tr>
    <tr><td class="b">&bull;</td><td>di autorizzare il/la volontario/a a partecipare come staff all'evento.</td></tr>
</table>

<table class="firma">
    <tr class="sp"><td></td><td></td></tr>
    <tr>
        <td>Luogo e data</td>
        <td>Il Presidente (firma e timbro)</td>
    </tr>
</table>

</body>
</html>
