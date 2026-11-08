<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricerca partecipante — {{ $evento->nome }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .header { background: #b91c1c; color: #fff; padding: 14px 16px; }
        .header h1 { margin: 0; font-size: 1rem; font-weight: 700; }
        .header p  { margin: 4px 0 0; font-size: .8rem; opacity: .8; }
        .card { background: #fff; border-radius: 12px; margin: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        label { font-size: .82rem; font-weight: 600; color: #374151; display: block; margin-bottom: 4px; }
        input[type=text], input[type=datetime-local] {
            width: 100%; padding: 12px 14px; border: 1.5px solid #d1d5db; border-radius: 8px;
            font-size: 1rem; font-family: monospace; letter-spacing: .06em; text-transform: uppercase; background: #fff;
        }
        input:focus { outline: none; border-color: #2563eb; }
        .btn-primary { display: block; width: 100%; padding: 14px; background: #2563eb; color: #fff;
            border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .btn-secondary { display: block; width: 100%; padding: 12px; background: #f9fafb; color: #374151;
            border: 1.5px solid #d1d5db; border-radius: 10px; font-size: .95rem; font-weight: 600;
            cursor: pointer; margin-top: 8px; text-align: center; text-decoration: none; }
        .alert-success { background: #dcfce7; color: #15803d; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .alert-error   { background: #fee2e2; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .alert-info    { background: #eff6ff; color: #1d4ed8; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .form-row { margin-bottom: 12px; }
        .section-title { font-size: .75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .08em; margin: 16px 0 8px; }

        /* Autocomplete */
        .cf-wrap { position: relative; }
        #suggest-list {
            display: none;
            position: absolute; top: 100%; left: 0; right: 0; z-index: 100;
            background: #fff; border: 1.5px solid #d1d5db; border-top: none;
            border-radius: 0 0 10px 10px; box-shadow: 0 4px 12px rgba(0,0,0,.1);
            max-height: 260px; overflow-y: auto;
        }
        #suggest-list.visible { display: block; }
        .suggest-item {
            padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center; gap: 10px;
        }
        .suggest-item:last-child { border-bottom: none; }
        .suggest-item:hover, .suggest-item.active { background: #eff6ff; }
        .suggest-nome { font-weight: 600; font-size: .9rem; color: #111827; }
        .suggest-cf   { font-size: .75rem; font-family: monospace; color: #6b7280; letter-spacing: .04em; }
        .suggest-com  { font-size: .75rem; color: #9ca3af; }
        .suggest-tipo {
            font-size: .7rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;
            white-space: nowrap; flex-shrink: 0;
        }
        .tipo-corsista { background: #dbeafe; color: #1d4ed8; }
        .tipo-staff    { background: #fef3c7; color: #92400e; }
        .tipo-ospite   { background: #ede9fe; color: #5b21b6; }
        .suggest-spinner { padding: 12px 14px; font-size: .85rem; color: #9ca3af; text-align: center; }
        #cf-input.searching { border-color: #f59e0b; }

        /* Form ospite inline (nascosto di default) */
        #ospite-form { display: none; }
        #ospite-form.visible { display: block; }
    </style>
</head>
<body>

<div class="header">
    <h1>Ricerca partecipante</h1>
    <p>{{ $evento->nome }}</p>
</div>

<div style="padding: 12px;">

    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    {{-- Form ricerca CF --}}
    <div class="card">
        <p style="font-size:.9rem;color:#6b7280;margin:0 0 14px;">
            Inserisci il codice fiscale del partecipante per aprire la scheda check-in.
            La ricerca parte automaticamente dopo 6 caratteri.
        </p>
        <form id="cerca-form">
            @csrf
            <div class="form-row">
                <label for="cf-input">Codice fiscale</label>
                <div class="cf-wrap">
                    <input type="text" id="cf-input" name="cf" maxlength="16" autofocus
                        placeholder="RSSMRA80A01H501Z"
                        autocomplete="off"
                        style="text-transform:uppercase;">
                    <div id="suggest-list" role="listbox"></div>
                </div>
            </div>
            <button type="submit" class="btn-primary">Cerca</button>
        </form>
    </div>

    {{-- Form aggiungi ospite (inline, mostrato via JS o session) --}}
    <div class="card" id="ospite-form" style="border: 2px solid #fbbf24;"
        @if(session('cf_non_trovato')) class="card visible" style="border: 2px solid #fbbf24;" @endif>
        <div class="alert-info" style="margin-bottom:14px;">
            Nessun iscritto o staff trovato con CF <strong id="ospite-cf-label">{{ session('cf_non_trovato') }}</strong>.<br>
            Puoi registrarlo come ospite.
        </div>

        <form method="POST" action="{{ route('checkin.salva-ospite', $evento) }}">
            @csrf

            <div class="section-title">Dati ospite</div>

            <div class="form-row">
                <label>Codice Fiscale *</label>
                <input type="text" name="cf" id="ospite-cf-input"
                    value="{{ session('cf_non_trovato') }}"
                    required maxlength="16" placeholder="RSSMRA80A01H501Z"
                    style="text-transform:uppercase;">
            </div>
            <div class="form-row">
                <label>Cognome *</label>
                <input type="text" name="cognome" required placeholder="Rossi"
                    style="text-transform:capitalize;font-family:inherit;letter-spacing:0;">
            </div>
            <div class="form-row">
                <label>Nome *</label>
                <input type="text" name="nome" required placeholder="Mario"
                    style="text-transform:capitalize;font-family:inherit;letter-spacing:0;">
            </div>
            <button type="submit" class="btn-primary" style="background:#16a34a;">
                Registra ospite
            </button>
        </form>
    </div>

    <a href="{{ route('filament.admin.resources.eventi.presenze-live', $evento) }}"
        class="btn-secondary" style="margin: 0 12px 12px;">
        ← Torna a Presenze live
    </a>

</div>

<script>
const suggestUrl  = '{{ route('checkin.suggerisci', $evento) }}';
const cercaUrl    = '{{ route('checkin.cerca', $evento) }}';
const csrfToken   = document.querySelector('input[name=_token]')?.value ?? '';

const cfInput     = document.getElementById('cf-input');
const suggestList = document.getElementById('suggest-list');
const ospiteForm  = document.getElementById('ospite-form');
const ospiteCfLabel = document.getElementById('ospite-cf-label');
const ospiteCfInput = document.getElementById('ospite-cf-input');

// Mostra il form ospite se la session aveva cf_non_trovato
@if(session('cf_non_trovato'))
    ospiteForm.classList.add('visible');
    cfInput.value = '{{ session('cf_non_trovato') }}';
@endif

let debounceTimer = null;
let activeIndex   = -1;

// ── Autocomplete ──────────────────────────────────────────────
cfInput.addEventListener('input', () => {
    const val = cfInput.value.toUpperCase().trim();
    clearTimeout(debounceTimer);
    activeIndex = -1;

    if (val.length < 6) { hideSuggest(); return; }

    debounceTimer = setTimeout(() => fetchSuggest(val), 280);
});

async function fetchSuggest(q) {
    suggestList.innerHTML = '<div class="suggest-spinner">Ricerca…</div>';
    suggestList.classList.add('visible');

    try {
        const res  = await fetch(suggestUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        renderSuggest(data);
    } catch {
        hideSuggest();
    }
}

function renderSuggest(items) {
    if (!items.length) { hideSuggest(); return; }

    suggestList.innerHTML = items.map((it, i) => {
        const tipoCss = it.tipo === 'Staff' ? 'tipo-staff' : it.tipo === 'Ospite' ? 'tipo-ospite' : 'tipo-corsista';
        const com = it.comitato ? `<div class="suggest-com">${it.comitato}</div>` : '';
        return `
        <div class="suggest-item" role="option" data-url="${it.url}" data-cf="${it.cf}" data-index="${i}">
            <div style="flex:1;min-width:0;">
                <div class="suggest-nome">${it.nome}</div>
                <div class="suggest-cf">${it.cf}</div>
                ${com}
            </div>
            <span class="suggest-tipo ${tipoCss}">${it.tipo}</span>
        </div>`;
    }).join('');

    suggestList.classList.add('visible');

    suggestList.querySelectorAll('.suggest-item').forEach(el => {
        el.addEventListener('mousedown', e => {
            e.preventDefault();
            openCheckin(el.dataset.url);
        });
    });
}

function hideSuggest() {
    suggestList.classList.remove('visible');
    suggestList.innerHTML = '';
    activeIndex = -1;
}

// Navigazione tastiera
cfInput.addEventListener('keydown', e => {
    const items = suggestList.querySelectorAll('.suggest-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, items.length - 1);
        updateActive(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        updateActive(items);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault();
        openCheckin(items[activeIndex].dataset.url);
    } else if (e.key === 'Escape') {
        hideSuggest();
    }
});

function updateActive(items) {
    items.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
    if (activeIndex >= 0) items[activeIndex].scrollIntoView({ block: 'nearest' });
}

// Chiudi dropdown cliccando fuori
document.addEventListener('click', e => {
    if (!cfInput.contains(e.target) && !suggestList.contains(e.target)) hideSuggest();
});

// ── Submit form (cerca manuale) ────────────────────────────────
document.getElementById('cerca-form').addEventListener('submit', async e => {
    e.preventDefault();
    hideSuggest();

    const cf = cfInput.value.toUpperCase().trim();
    if (!cf) return;

    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Ricerca…';

    try {
        const res  = await fetch(cercaUrl, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ cf }),
        });
        const data = await res.json();

        if (data.found) {
            openCheckin(data.url);
        } else {
            // Mostra form ospite inline con CF precompilato
            ospiteCfLabel.textContent = data.cf;
            ospiteCfInput.value       = data.cf;
            ospiteForm.classList.add('visible');
            ospiteForm.scrollIntoView({ behavior: 'smooth' });
            const cognomeInput = ospiteForm.querySelector('input[name=cognome]');
            if (cognomeInput) setTimeout(() => cognomeInput.focus(), 300);
        }
    } catch {
        alert('Errore di rete. Riprova.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Cerca';
    }
});

function openCheckin(url) {
    hideSuggest();
    window.location.href = url;
}
</script>
</body>
</html>
