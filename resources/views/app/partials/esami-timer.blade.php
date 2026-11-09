{{-- Timer a conto alla rovescia (minuti e secondi), fisso sotto l'intestazione. Stato nel browser (localStorage) per pagina: sopravvive a ricarica e cambio pagina. --}}
<style>
    .timer {
        position: sticky; top: calc(56px + env(safe-area-inset-top, 0px)); z-index: 50;
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        background: #fff; border-bottom: 1px solid #e5e7eb; box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        margin: 0 -16px 14px; padding: 8px 16px;
    }
    .timer-tempo { display: flex; align-items: center; gap: 2px; font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .timer-tempo input {
        width: 2.2ch; border: 0; border-bottom: 2px solid #e5e7eb; background: transparent; padding: 0; text-align: center;
        font: inherit; color: inherit; border-radius: 0;
    }
    .timer-tempo input:disabled { border-bottom-color: transparent; opacity: 1; -webkit-text-fill-color: currentColor; }
    .timer-azioni { display: flex; gap: 6px; }
    .timer-azioni button {
        min-height: 40px; padding: 0 12px; border-radius: 10px; border: 2px solid #e5e7eb; background: #fff;
        font-size: 13px; font-weight: 800; color: #374151; cursor: pointer; touch-action: manipulation;
    }
    .timer-azioni button[data-avvia] { background: #16a34a; border-color: #16a34a; color: #fff; }
    .timer-azioni button:disabled { opacity: .4; cursor: default; }
    .timer.finito { background: #dc2626; color: #fff; animation: timer-lampeggio .8s ease-in-out infinite alternate; }
    @keyframes timer-lampeggio { to { background: #fca5a5; color: #7f1d1d; } }
</style>
<div class="timer" id="timer-prova" role="timer" aria-label="Timer della prova">
    <div class="timer-tempo">
        <input type="text" inputmode="numeric" maxlength="2" data-m aria-label="Minuti" value="00">:<input type="text" inputmode="numeric" maxlength="2" data-s aria-label="Secondi" value="00">
    </div>
    <div class="timer-azioni">
        <button type="button" data-avvia>Avvia</button>
        <button type="button" data-stop>Stop</button>
        <button type="button" data-reset>Reset</button>
    </div>
</div>
<script>
(() => {
    const el = document.getElementById('timer-prova');
    const m = el.querySelector('[data-m]'), s = el.querySelector('[data-s]');
    const bAvvia = el.querySelector('[data-avvia]'), bStop = el.querySelector('[data-stop]'), bReset = el.querySelector('[data-reset]');
    const CHIAVE = 'lurch-timer:' + location.pathname;
    // durata = ultimo valore impostato (per Reset), resto = secondi quando fermo, fine = istante di scadenza (ms) quando in corsa
    let st = { durata: 0, resto: 0, fine: null };
    try { Object.assign(st, JSON.parse(localStorage.getItem(CHIAVE)) || {}); } catch (e) {}
    let ctx, tick;

    const salva = () => { try { localStorage.setItem(CHIAVE, JSON.stringify(st)); } catch (e) {} };
    const due = (n) => String(n).padStart(2, '0');
    const leggi = () => Math.min(99, parseInt(m.value, 10) || 0) * 60 + Math.min(59, parseInt(s.value, 10) || 0);
    const resto = () => st.fine ? Math.max(0, Math.ceil((st.fine - Date.now()) / 1000)) : st.resto;

    function render() {
        const r = resto(), inCorsa = !!st.fine;
        m.value = due(Math.floor(r / 60)); s.value = due(r % 60);
        m.disabled = s.disabled = inCorsa;
        bAvvia.disabled = inCorsa || r === 0;
        bStop.disabled = !inCorsa;
        bReset.disabled = inCorsa || st.durata === 0;
        el.classList.toggle('finito', !inCorsa && st.durata > 0 && r === 0);
    }

    function allarme() {
        if (navigator.vibrate) navigator.vibrate([300, 150, 300, 150, 300]);
        // ponytail: il beep funziona solo se l'audio è stato sbloccato da un Avvia in questa pagina (iOS); la vibrazione non c'è su iOS
        try { const o = ctx.createOscillator(); o.frequency.value = 880; o.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + 0.7); } catch (e) {}
    }

    function passo() {
        if (resto() > 0) { render(); return; }
        clearInterval(tick); st.fine = null; st.resto = 0; salva(); render(); allarme();
    }

    function avvia() {
        ctx = ctx || (window.AudioContext || window.webkitAudioContext) && new (window.AudioContext || window.webkitAudioContext)();
        if (ctx && ctx.resume) ctx.resume();
        const sec = leggi(); // gli input mostrano sempre il tempo rimasto (anche dopo uno Stop)
        if (sec <= 0) return;
        if (!st.durata) st.durata = sec;
        st.fine = Date.now() + sec * 1000;
        salva(); render(); tick = setInterval(passo, 250);
    }

    m.addEventListener('focus', (e) => e.target.select()); s.addEventListener('focus', (e) => e.target.select());
    [m, s].forEach((i) => i.addEventListener('change', () => { st.durata = st.resto = leggi(); salva(); render(); }));
    bAvvia.addEventListener('click', avvia);
    bStop.addEventListener('click', () => { st.resto = resto(); st.fine = null; clearInterval(tick); salva(); render(); });
    bReset.addEventListener('click', () => { st.resto = st.durata; st.fine = null; clearInterval(tick); salva(); render(); });

    // Ripresa dopo ricarica: se era in corsa riparte il ciclo, se è scaduto nel frattempo resta a 00:00 (rosso, senza allarme)
    if (st.fine && st.fine <= Date.now()) { st.fine = null; st.resto = 0; salva(); }
    if (st.fine) tick = setInterval(passo, 250);
    render();
})();
</script>
