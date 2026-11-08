<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titolo }} — Guida LURCH</title>
    @include('guide.partials.stile')
</head>
<body>

@unless($pdf)
    <div class="guida-topbar">
        <div class="inner">
            <p>Guida all'uso del portale</p>
            <strong>{{ $titolo }}</strong>
        </div>
    </div>
    <div class="guida-actions">
        <a href="{{ route('guide.index') }}">&larr; Tutte le guide</a>
        <a href="{{ route('guide.pdf', $slug) }}" target="_blank" rel="noopener">Scarica PDF</a>
    </div>
@endunless

<div class="guida-wrap">
    <article class="guida">
        {!! $html !!}
    </article>
</div>

@unless($pdf)
    <x-form-footer />
@endunless

</body>
</html>
