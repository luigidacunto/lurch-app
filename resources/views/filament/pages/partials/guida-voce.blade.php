{{-- Voce dell'indice della guida; sotto la pagina aperta, i suoi paragrafi. --}}
@php($attiva = $pagina['percorso'] === $voce['percorso'])
<a href="?p={{ $voce['percorso'] }}" @class(['attiva' => $attiva])>{{ $voce['titolo'] }}</a>
@if ($attiva && count($pagina['paragrafi']) >= 3)
    <div class="paragrafi">
        @foreach ($pagina['paragrafi'] as $paragrafo)
            <a href="#{{ $paragrafo['id'] }}">{{ $paragrafo['testo'] }}</a>
        @endforeach
    </div>
@endif
@if (! empty($voce['figli']))
    <div class="figli">
        @foreach ($voce['figli'] as $figlio)
            @include('filament.pages.partials.guida-voce', ['voce' => $figlio])
        @endforeach
    </div>
@endif
