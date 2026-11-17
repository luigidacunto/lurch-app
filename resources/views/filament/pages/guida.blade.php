<x-filament-panels::page>
    <style>
        /* Larghezza decisa dallo spazio reale accanto alla sidebar del pannello, non dalla finestra. */
        .guida-contenitore { container-type: inline-size; }
        .guida { display: grid; grid-template-columns: 16rem minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        .guida-indice { position: sticky; top: 5rem; max-height: calc(100vh - 6rem); overflow-y: auto; }
        .guida-indice a { display: block; padding: .3rem .6rem; border-radius: .5rem; font-size: .875rem; color: inherit; }
        .guida-indice a:hover { background: var(--gray-100); }
        .guida-indice a.attiva { background: var(--primary-50); color: var(--primary-700); font-weight: 600; }
        .dark .guida-indice a:hover { background: var(--gray-800); }
        .dark .guida-indice a.attiva { background: var(--gray-800); color: var(--primary-400); }
        .guida-indice summary { cursor: pointer; padding: .4rem .6rem; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
        .guida-indice details { margin-top: .25rem; }
        .guida-indice details > div { padding-left: .5rem; }
        .guida-indice .paragrafi { margin: .1rem 0 .3rem .6rem; padding-left: .5rem; border-left: 1px solid var(--gray-200); }
        .guida-indice .paragrafi a { font-size: .8rem; padding: .15rem .5rem; color: var(--gray-500); }
        .dark .guida-indice .paragrafi { border-color: var(--gray-700); }
        .guida-indice-bottone { display: none; width: 100%; text-align: left; font-weight: 600; font-size: .875rem; }
        .guida-testo .fi-prose img { border: 1px solid var(--gray-200); border-radius: .5rem; cursor: zoom-in; }
        .dark .guida-testo .fi-prose img { border-color: var(--gray-700); }
        .guida-lightbox { position: fixed; inset: 0; z-index: 60; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .75rem; padding: 1.5rem; background: rgba(0, 0, 0, .85); cursor: zoom-out; }
        .guida-lightbox img { max-width: 100%; max-height: calc(100vh - 5rem); object-fit: contain; border-radius: .5rem; background: #fff; }
        .guida-lightbox span { color: rgba(255, 255, 255, .7); font-size: .8rem; }
        .guida-testo .fi-prose blockquote { quotes: none; }
        .guida-testo .fi-prose blockquote p::before, .guida-testo .fi-prose blockquote p::after { content: none; }
        .guida-testo .fi-prose h2 { scroll-margin-top: 5rem; }
        @container (max-width: 44rem) {
            .guida { grid-template-columns: minmax(0, 1fr); }
            .guida-indice { position: static; max-height: none; }
            .guida-indice-bottone { display: block; }
            .guida-indice .chiuso { display: none; }
        }
    </style>

    <div class="guida-contenitore"><div class="guida">
        <nav class="guida-indice" aria-label="Indice della guida" x-data="{ aperto: false }">
            <x-filament::section>
                {{-- Su schermi stretti l'indice è una tendina chiusa, per non spingere il testo in fondo. --}}
                <button type="button" class="guida-indice-bottone" x-on:click="aperto = ! aperto" x-bind:aria-expanded="aperto">
                    ☰ Indice
                </button>

                <div x-bind:class="{ 'chiuso': ! aperto }">
                    <a href="?" @class(['attiva' => $pagina['percorso'] === null])>Introduzione</a>

                    @foreach ($indice as $blocco)
                        @if ($blocco['titolo'] === null)
                            @foreach ($blocco['pagine'] as $voce)
                                @include('filament.pages.partials.guida-voce')
                            @endforeach
                        @else
                            <details @if (str_starts_with((string) $pagina['percorso'], explode('/', $blocco['pagine'][0]['percorso'] ?? '')[0] . '/') || $pagina['percorso'] === null) open @endif>
                                <summary>{{ $blocco['titolo'] }}</summary>
                                <div>
                                    @foreach ($blocco['pagine'] as $voce)
                                        @include('filament.pages.partials.guida-voce')
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endforeach
                </div>
            </x-filament::section>
        </nav>

        <article class="guida-testo">
            <x-filament::section>
                {{-- Clic su un'immagine: si apre a tutto schermo sopra la pagina; si chiude con un clic o con Esc. --}}
                <div x-data="{ zoom: null, alt: '' }" x-on:keydown.escape.window="zoom = null">
                    <div class="fi-prose" x-on:click="if ($event.target.tagName === 'IMG') { zoom = $event.target.src; alt = $event.target.alt }">
                        {!! $pagina['html'] !!}
                    </div>
                    <div class="guida-lightbox" x-cloak x-show="zoom" x-transition.opacity x-on:click="zoom = null" role="dialog" aria-modal="true" x-bind:aria-label="alt">
                        <img x-bind:src="zoom" x-bind:alt="alt">
                        <span>Clic o Esc per chiudere</span>
                    </div>
                </div>
            </x-filament::section>
        </article>
    </div></div>
</x-filament-panels::page>
