<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>{{ $titolo }}</title>
<style>
    @page { margin: 18mm 16mm 20mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.5; }
    .copertina { padding-top: 70mm; text-align: center; }
    .copertina h1 { font-size: 24pt; margin: 0 0 6mm; border: none; }
    .copertina p { color: #6b7280; margin: 1mm 0; }
    .pagina { page-break-before: always; }
    h1 { font-size: 16pt; margin: 0 0 4mm; padding-bottom: 2mm; border-bottom: 1px solid #e5e7eb; }
    h2 { font-size: 12.5pt; margin: 6mm 0 2mm; }
    h3 { font-size: 11pt; margin: 4mm 0 1.5mm; }
    p, li { margin: 1.5mm 0; }
    a { color: #b91c1c; text-decoration: none; }
    img { max-width: 100%; border: 1px solid #e5e7eb; margin: 2mm 0; }
    table { border-collapse: collapse; width: 100%; margin: 3mm 0; }
    th, td { border: 1px solid #e5e7eb; padding: 1.5mm 2mm; text-align: left; vertical-align: top; }
    th { background: #f9fafb; }
    blockquote { margin: 3mm 0; padding: 2mm 3mm; background: #f9fafb; border-left: 2pt solid #9ca3af; color: #4b5563; }
    blockquote p { margin: 0.5mm 0; }
    code { background: #f3f4f6; padding: 0 1mm; font-size: 9pt; }
    pre { background: #f3f4f6; padding: 3mm; font-size: 8.5pt; white-space: pre-wrap; }
</style>
</head>
<body>
    <div class="copertina">
        <h1>Guida di LURCH</h1>
        <p style="font-size: 14pt; color: #1f2937;">{{ $titolo }}</p>
        <p>Versione {{ $versione }} · {{ now()->locale('it')->isoFormat('D MMMM YYYY') }}</p>
    </div>

    @foreach ($pagine as $pagina)
        <div class="pagina">{!! $pagina['html'] !!}</div>
    @endforeach
</body>
</html>
