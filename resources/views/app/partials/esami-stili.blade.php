<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 4px; overflow-wrap: anywhere; }
    .page-sub { font-size: 13px; color: #6b7280; margin-bottom: 14px; overflow-wrap: anywhere; }
    .avviso-personale { background: #eff6ff; color: #1e40af; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
    .avviso-errore { background: #fef2f2; color: #991b1b; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 14px; }
    .avviso-sola-lettura { background: #f3f4f6; color: #4b5563; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
    .vuoto { background: #fff; border-radius: 12px; padding: 16px; font-size: 14px; color: #6b7280; }
    .barra {
        position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb;
        margin: 16px -16px 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0));
        box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
    }
    .barra-riga { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; margin-bottom: 8px; }
    .barra-stato { font-size: 12px; font-weight: 600; color: #6b7280; }
    .barra-stato.errore { color: #b91c1c; }
    .totale { font-size: 13px; color: #374151; }
</style>
