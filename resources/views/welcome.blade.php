<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L.U.R.C.H. — Croce Rossa Italiana</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #fff;
            font-family: system-ui, -apple-system, sans-serif;
            padding: 3rem 1.25rem 2rem;
        }
        .wrap { width: 100%; max-width: 760px; }

        /* Hero */
        .hero { text-align: center; margin-bottom: 2.5rem; }
        .logo { width: 100%; max-width: 520px; height: auto; margin: 0 auto 1rem; display: block; }
        .hero p { font-size: 0.95rem; color: #4b5563; line-height: 1.6; margin: 0 auto; }

        /* Scelta area */
        .options { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 560px) { .options { grid-template-columns: 1fr; } }
        .option {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.75rem;
            padding: 2rem 1.5rem;
            border-radius: 1.25rem;
            text-decoration: none;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid transparent;
            transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
        }
        .option:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.1); }
        .option-icon {
            width: 3.25rem; height: 3.25rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 1rem;
        }
        .option-admin .option-icon { background: #fdecec; color: #c8102e; }
        .option-app .option-icon { background: #e8f0fe; color: #1d4ed8; }
        .option-admin:hover { border-color: #f3b7bf; }
        .option-app:hover { border-color: #bfd4fb; }
        .option-title { font-size: 1.05rem; font-weight: 700; color: #1f2937; }
        .option-subtitle { font-size: 0.8rem; color: #6b7280; line-height: 1.5; }
        .option-cta {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .option-admin .option-cta { color: #c8102e; }
        .option-app .option-cta { color: #1d4ed8; }

        /* Funzionalità */
        .features {
            margin-top: 2.5rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        @media (max-width: 680px) { .features { grid-template-columns: repeat(2, 1fr); } }
        .feature {
            text-align: center;
            padding: 1rem 0.5rem;
        }
        .feature-icon {
            width: 2.25rem; height: 2.25rem;
            margin: 0 auto 0.5rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 0.7rem;
            background: #f3f4f6;
            color: #6b7280;
        }
        .feature-label { font-size: 0.75rem; color: #4b5563; line-height: 1.4; }

        /* Footer */
        .footer-note {
            margin-top: 3rem;
            text-align: center;
            font-size: 0.7rem;
            color: #b0b6c0;
            line-height: 1.6;
            width: 100%;
            max-width: 760px;
        }
        .footer-note a { color: #9aa2ae; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <img src="/images/lurch-hero.png" alt="L.U.R.C.H." class="logo">
            <p>
                Piattaforma di gestione corsi, preregistrazioni e hospitality
                per i comitati della <strong>Croce Rossa Italiana</strong>.
            </p>
        </div>

        <div class="options">
            <a href="/admin" class="option option-admin">
                <span class="option-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </span>
                <span class="option-title">Pannello admin</span>
                <span class="option-subtitle">Per operatori e segreteria dei comitati: gestione corsi, eventi, iscrizioni e graduatorie</span>
                <span class="option-cta">← Accedi</span>
            </a>
            <a href="/app" class="option option-app">
                <span class="option-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
                </span>
                <span class="option-title">App mobile</span>
                <span class="option-subtitle">Per i soci: eventi a cui sei iscritto, documenti e note personali</span>
                <span class="option-cta">Accedi →</span>
            </a>
        </div>

        <div class="features">
            <div class="feature">
                <span class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
                <span class="feature-label">Corsi, eventi<br>e iscrizioni</span>
            </div>
            <div class="feature">
                <span class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </span>
                <span class="feature-label">Qualifiche<br>e scadenze</span>
            </div>
            <div class="feature">
                <span class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21V9a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v12"/><path d="M4 21V13a2 2 0 0 1 2-2h1"/><path d="M17 21V13a2 2 0 0 1 2-2h1"/></svg>
                </span>
                <span class="feature-label">Graduatorie<br>e selezione</span>
            </div>
            <div class="feature">
                <span class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M2 21h20"/><path d="M6 11V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4"/></svg>
                </span>
                <span class="feature-label">Check-in<br>e hospitality</span>
            </div>
        </div>
    </div>

    <p class="footer-note">
        Software libero e gratuito, sviluppato e mantenuto da Luigi D'Acunto, volontario CRI, per l'attività
        della Croce Rossa Italiana — nessun fine commerciale.<br>
        @if (config('app.contatto_email'))
        Per assistenza sull'accesso, contatta <a href="#" id="contact-email" rel="nofollow">il gestore del portale</a>.
        @endif
        &nbsp;&middot;&nbsp;<a href="{{ route('privacy.show') }}">Informativa privacy</a>
    </p>

    @if (config('app.contatto_email'))
    @php([$contattoUtente, $contattoDominio] = array_pad(explode('@', config('app.contatto_email'), 2), 2, ''))
    <script>
        // Indirizzo composto a runtime (mai presente come stringa intera nell'HTML statico)
        // per non essere raccolto dai crawler che leggono solo il sorgente, senza eseguire JS.
        (function () {
            var user = @js($contattoUtente), domain = @js($contattoDominio);
            var address = user + '@' + domain;
            var link = document.getElementById('contact-email');
            link.href = 'mailto:' + address;
            link.textContent = address;
        })();
    </script>
    @endif
</body>
</html>
