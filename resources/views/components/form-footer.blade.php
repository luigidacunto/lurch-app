<footer style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 36px;
    background: #fff;
    border-top: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.25rem;
    z-index: 40;
    font-size: 0.7rem;
    color: #9ca3af;
    gap: 1rem;
">
    <span style="white-space: nowrap; flex-shrink: 0;">
        L.U.R.C.H. &mdash; v{{ config('app.version', '1.0.0') }}
    </span>
    <span style="flex: 1; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        &copy; {{ date('Y') }} Luigi D&apos;Acunto. Software sviluppato e mantenuto autonomamente. Uso interno per attività della Croce Rossa Italiana.
        &nbsp;&middot;&nbsp;<a href="{{ route('privacy.show') }}" style="color: inherit; text-decoration: underline;">Privacy</a>
    </span>
    <span style="white-space: nowrap; flex-shrink: 0;">
        PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} &nbsp;|&nbsp; Laravel {{ app()->version() }} &nbsp;|&nbsp; Ambiente: {{ config('app.env') }}
    </span>
</footer>
<div style="height: 36px;"></div>
