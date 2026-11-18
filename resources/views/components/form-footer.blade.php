<style>
    .lurch-footer {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 40;
        min-height: 36px; padding: 4px 1.25rem;
        background: #fff; border-top: 1px solid #e5e7eb;
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.1rem 1rem;
        font-size: 0.7rem; color: #9ca3af;
    }
    .lurch-footer-centro { flex: 1; text-align: center; }
    .lurch-footer-spazio { height: 36px; }
    /* Telefono: le righe vanno a capo invece di sparire; lo spazio in fondo copre il piè di pagina più alto. */
    @media (max-width: 640px) {
        .lurch-footer { justify-content: center; text-align: center; padding: 6px 1rem; }
        .lurch-footer-centro { flex-basis: 100%; }
        .lurch-footer-spazio { height: 104px; }
    }
</style>
<footer class="lurch-footer">
    <span style="white-space: nowrap;">
        L.U.R.C.H. &mdash; v{{ config('app.version', '1.0.0') }}
    </span>
    <span class="lurch-footer-centro">
        &copy; {{ \App\Support\Copyright::anni() }} Luigi D&apos;Acunto. Software sviluppato e mantenuto autonomamente. Uso interno per attività della Croce Rossa Italiana.
        &nbsp;&middot;&nbsp;<a href="{{ route('privacy.show') }}" style="color: inherit; text-decoration: underline;">Privacy</a>
    </span>
    {{-- Versioni del server e ambiente solo fuori produzione: in pubblico aiuterebbero chi cerca falle note. --}}
    @unless(app()->environment('production'))
        <span style="white-space: nowrap;">
            PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} &nbsp;|&nbsp; Laravel {{ app()->version() }} &nbsp;|&nbsp; Ambiente: {{ config('app.env') }}
        </span>
    @endunless
</footer>
<div class="lurch-footer-spazio"></div>
