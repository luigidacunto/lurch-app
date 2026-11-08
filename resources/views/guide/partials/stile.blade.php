{{-- Layout condiviso delle guide: vale per la pagina web e per il PDF (dompdf). --}}
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1f2937; background: #f9fafb; }

    .guida-topbar { background: #b91c1c; color: #fff; padding: .9rem 1.25rem; }
    .guida-topbar .inner { max-width: 820px; margin: 0 auto; }
    .guida-topbar p { margin: 0; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; opacity: .85; }
    .guida-topbar strong { font-size: 1rem; }

    .guida-actions { max-width: 820px; margin: 0 auto; padding: 1rem 1.25rem 0; display: flex; gap: 1.25rem; font-size: .85rem; }
    .guida-actions a { color: #b91c1c; text-decoration: none; }
    .guida-actions a:hover { text-decoration: underline; }

    .guida-wrap { max-width: 820px; margin: 0 auto; padding: 1.5rem 1.25rem 4rem; }
    .guida { background: #fff; border: 1px solid #eef0f2; border-radius: 16px; padding: 2rem 2.25rem; }

    .guida h1 { font-size: 1.5rem; margin: .2rem 0 1.2rem; line-height: 1.25; }
    .guida h2 { font-size: 1.15rem; margin: 2rem 0 .6rem; padding-top: 1rem; border-top: 1px solid #f0f0f0; }
    .guida h3 { font-size: 1rem; margin: 1.4rem 0 .4rem; }
    .guida p, .guida li { line-height: 1.7; font-size: .95rem; }
    .guida ol, .guida ul { padding-left: 1.3rem; }
    .guida li { margin: .3rem 0; }
    .guida a { color: #b91c1c; }

    .guida img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px; margin: .8rem 0; display: block; background: #f9fafb; padding: .4rem; font-size: .85rem; color: #9ca3af; min-height: 2rem; }

    .guida code { background: #f3f4f6; padding: .1rem .35rem; border-radius: 4px; font-size: .85em; }
    .guida pre { background: #f3f4f6; padding: 1rem; border-radius: 8px; overflow-x: auto; }
    .guida pre code { background: none; padding: 0; }

    .guida table { border-collapse: collapse; width: 100%; margin: 1rem 0; font-size: .9rem; }
    .guida th, .guida td { border: 1px solid #e5e7eb; padding: .45rem .6rem; text-align: left; vertical-align: top; }
    .guida th { background: #f9fafb; }

    .guida blockquote { margin: 1rem 0; padding: .75rem 1rem; background: #fdf2f8; border-left: 3px solid #a72863; border-radius: 4px; color: #4b5563; }
    .guida blockquote p { margin: .2rem 0; }

    {{-- Riquadro-nota riutilizzabile: <div class="nota">…</div> dentro il Markdown. --}}
    .guida .nota { margin: 1rem 0; padding: .85rem 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; font-size: .9rem; }

    .guida-voce { padding: .9rem 0; border-bottom: 1px solid #f0f0f0; }
    .guida-voce:last-child { border-bottom: none; }
    .guida-voce a { font-size: 1rem; text-decoration: none; color: #b91c1c; }
    .guida-voce p { margin: .3rem 0 0; color: #6b7280; font-size: .9rem; }

    @media print {
        body { background: #fff; }
        .guida { border: none; border-radius: 0; padding: 0; }
        .guida-topbar, .guida-actions { display: none; }
        .guida h2 { border-top: none; }
    }
</style>
