<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide all'uso del portale — LURCH</title>
    @include('guide.partials.stile')
</head>
<body>

<div class="guida-topbar">
    <div class="inner">
        <p>LURCH</p>
        <strong>Guide all'uso del portale</strong>
    </div>
</div>

<div class="guida-wrap">
    <article class="guida">
        <h1>Guide all'uso del portale</h1>
        <p>Istruzioni passo-passo per chi usa il portale come operatore o referente locale.
           Ogni guida copre una sezione o un'operazione ed è scaricabile in PDF.</p>

        @forelse($guide as $g)
            <div class="guida-voce">
                <a href="{{ route('guide.show', $g['slug']) }}">{{ $g['titolo'] }}</a>
                @if($g['descrizione'])
                    <p>{{ $g['descrizione'] }}</p>
                @endif
            </div>
        @empty
            <p><em>Nessuna guida disponibile al momento.</em></p>
        @endforelse
    </article>
</div>

<x-form-footer />

</body>
</html>
