<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#C8102E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lurch">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="{{ asset('images/logo_lurch.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_lurch.png') }}">
    <title>@yield('title', 'Lurch') — Portale Soci</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f4f4f5;
            color: #111827;
            min-height: 100dvh;
            -webkit-font-smoothing: antialiased;
        }
        .app-header {
            background: #C8102E;
            color: #fff;
            padding: env(safe-area-inset-top, 0) 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }
        .app-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            max-width: 640px;
            margin: 0 auto;
        }
        .app-header-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #fff;
        }
        .app-header-title svg { width: 22px; height: 22px; flex-shrink: 0; }
        .app-header-actions { display: flex; align-items: center; gap: 8px; }
        .btn-install {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.35);
            color: #fff;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: none;
        }
        .btn-install.visible { display: inline-block; }
        .btn-logout {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.75);
            font-size: 13px;
            cursor: pointer;
            padding: 6px;
        }
        .btn-logout:hover { color: #fff; }
        .app-main {
            max-width: 640px;
            margin: 0 auto;
            padding: 20px 16px calc(env(safe-area-inset-bottom, 0px) + 20px);
        }
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    </style>
    @stack('styles')
</head>
<body>

@if(session('app_socio_id'))
<header class="app-header">
    <div class="app-header-inner">
        <a href="{{ route('app.home') }}" class="app-header-title">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
            Lurch
        </a>
        <div class="app-header-actions">
            <button class="btn-install" id="btnInstall" onclick="installApp()">Installa app</button>
            <form method="POST" action="{{ route('app.logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-logout" title="Esci">Esci</button>
            </form>
        </div>
    </div>
</header>
@endif

<main class="app-main">
    @yield('content')

    <p style="text-align:center; margin-top:24px; font-size:12px; color:#9ca3af;">
        <a href="{{ route('privacy.show') }}" style="color:inherit; text-decoration:underline;">Informativa privacy</a>
    </p>
</main>

<script>
    // PWA: registra service worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
    }

    // PWA: intercetta evento install per mostrare il pulsante
    let deferredPrompt = null;
    const btnInstall = document.getElementById('btnInstall');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (btnInstall) btnInstall.classList.add('visible');
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        if (btnInstall) btnInstall.classList.remove('visible');
    });

    function installApp() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(() => { deferredPrompt = null; });
    }
</script>

@stack('scripts')
</body>
</html>
