<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 4px; overflow-wrap: anywhere; }
    .page-sub { font-size: 13px; color: #6b7280; margin-bottom: 14px; }
    .avviso-personale { background: #eff6ff; color: #1e40af; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
    .avviso-errore { background: #fef2f2; color: #991b1b; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 14px; }
    .avviso-sola-lettura { background: #f3f4f6; color: #4b5563; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
    details.comp { background: #fff; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    details.comp > summary {
        list-style: none; cursor: pointer; padding: 14px 16px; min-height: 52px;
        display: flex; justify-content: space-between; align-items: center; gap: 8px;
        font-size: 15px; font-weight: 700; color: #111827;
    }
    details.comp > summary::-webkit-details-marker { display: none; }
    .comp-conta { font-size: 12px; font-weight: 700; color: #6b7280; white-space: nowrap; }
    .comp-conta.ok { color: #047857; }
    .comp-corpo { padding: 0 16px 8px; }
    .comp-obiettivo { font-size: 13px; color: #6b7280; margin-bottom: 12px; overflow-wrap: anywhere; }
    .voce { padding: 12px 0; border-top: 1px solid #f3f4f6; }
    .voce-testo { font-size: 14px; color: #111827; margin-bottom: 10px; overflow-wrap: anywhere; line-height: 1.4; }
    .semaforo { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .semaforo button {
        min-height: 52px; border-radius: 10px; border: 2px solid #e5e7eb; background: #fff;
        font-size: 15px; font-weight: 800; color: #6b7280; cursor: pointer; touch-action: manipulation;
    }
    .semaforo button:disabled { cursor: default; opacity: .7; }
    .semaforo button[data-livello="rosso"].sel  { background: #dc2626; border-color: #dc2626; color: #fff; }
    .semaforo button[data-livello="giallo"].sel { background: #facc15; border-color: #facc15; color: #111827; }
    .semaforo button[data-livello="verde"].sel  { background: #16a34a; border-color: #16a34a; color: #fff; }
    .legenda { font-size: 12px; color: #6b7280; margin-bottom: 12px; line-height: 1.5; }
    .andamento { background: #fff; border-radius: 12px; padding: 14px 16px; margin: 14px 0; font-size: 14px; }
    .andamento h3 { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
    .andamento div { padding: 4px 0; }
    .barra {
        position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb;
        margin: 16px -16px 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0));
        box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
    }
    .barra-riga { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; margin-bottom: 8px; }
    .barra-stato { font-size: 12px; font-weight: 600; color: #6b7280; }
    .barra-stato.errore { color: #b91c1c; }
    .totale { font-size: 13px; color: #374151; margin-bottom: 8px; }
    .btn-invia {
        width: 100%; min-height: 52px; border-radius: 10px; border: none; background: #16a34a; color: #fff;
        font-size: 16px; font-weight: 800; cursor: pointer;
    }
    .btn-invia:disabled { background: #d1d5db; color: #6b7280; cursor: not-allowed; }
    .barra-motivo { font-size: 12px; color: #6b7280; margin-top: 6px; }
</style>
