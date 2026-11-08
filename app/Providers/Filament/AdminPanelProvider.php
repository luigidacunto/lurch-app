<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\AdminLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(AdminLogin::class)
            ->authGuard('web')
            ->brandLogo(view('filament.components.brand'))
            // Vuoto apposta: la sidebar mostra già "L.U.R.C.H." tramite brandLogo() sopra
            // (indipendente da brandName()); brandName() serve solo a comporre il suffisso
            // "- {brand}" nel <title> del tab browser — tolto per lasciare più spazio al
            // titolo specifico della pagina, la favicon identifica già l'app.
            ->brandName('')
            ->favicon(asset('images/logo_lurch.png'))
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->colors([
                'primary'    => Color::Red,
                // "Esegue un'operazione ora, non salva ancora" — convenzione colori-per-funzione.
                // Colmato il gap segnato nella tabella come "non ancora registrato".
                'brand_pink' => Color::hex('#a72863'),
            ])
            ->navigationGroups([
                NavigationGroup::make('Gestione operativa'),
                NavigationGroup::make('Archivi'),
                NavigationGroup::make('Impostazioni'),
                NavigationGroup::make('Guida'),
            ])
            ->navigationItems([
                // Guide pubbliche all'uso del portale (rotta /guide, blade non-Filament) —
                // voce a sé, visibile a ogni ruolo, si apre in una scheda nuova.
                NavigationItem::make('Guide all\'uso del portale')
                    ->url('/guide', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-question-mark-circle')
                    ->group('Guida')
                    ->sort(99),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Modifica password')
                    ->icon('heroicon-o-key')
                    ->url(fn () => url('/admin/modifica-password')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<HTML
                    <footer id="lurch-footer">
                        <span class="lurch-footer-left">
                            L.U.R.C.H. &mdash; v{$this->appVersion()}
                        </span>
                        <span class="lurch-footer-center">
                            &copy; {$this->currentYear()} Luigi D&apos;Acunto. Software sviluppato e mantenuto autonomamente. Uso interno per attività della Croce Rossa Italiana.
                        </span>
                        <span class="lurch-footer-right">
                            PHP {$this->phpVersion()} &nbsp;|&nbsp; Laravel {$this->laravelVersion()} &nbsp;|&nbsp; Ambiente: {$this->appEnv()}
                        </span>
                    </footer>
                    HTML)
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        // Cliccare l'icona calendario/orologio di un DatePicker/DateTimePicker apre il
                        // picker — di default Filament la rende solo decorativa (focus, non apertura).
                        // Delegation su document: copre anche i campi renderizzati dopo (modali, Livewire).
                        document.addEventListener('click', function (event) {
                            var suffix = event.target.closest('.fi-fo-date-time-picker .fi-input-wrp-suffix');
                            if (!suffix) return;
                            var picker = suffix.closest('.fi-fo-date-time-picker');
                            var trigger = picker && picker.querySelector('.fi-fo-date-time-picker-trigger');
                            if (trigger) trigger.click();
                        });
                    </script>
                    HTML)
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        /* Bug flexbox min-width:auto: una sub-navigation Top con molte tab (più
                           larga del disponibile) impedisce ai contenitori della pagina di
                           restringersi, lasciando il contenuto sotto tagliato fuori dal bordo
                           senza scroll proprio. */
                        .fi-main, .fi-main-ctn, .fi-page-main, .fi-page-header-main-ctn { min-width: 0; }
                        /* Sezioni comprimibili (accordion): intestazione evidenziata e etichetta «Nascondi»/«Mostra»
                           prima della freccia, così non sembrano riquadri qualsiasi su cui si clicca per sbaglio. */
                        .fi-section.fi-collapsible > .fi-section-header {
                            background: rgba(128, 128, 128, .08);
                            border-radius: .5rem;
                            transition: background .15s;
                        }
                        .fi-section.fi-collapsible > .fi-section-header:hover { background: rgba(128, 128, 128, .18); }
                        .fi-section.fi-collapsible > .fi-section-header::after {
                            content: "Nascondi";
                            order: 1;
                            font-size: .75rem;
                            font-weight: 500;
                            opacity: .75;
                        }
                        .fi-section.fi-collapsible.fi-collapsed > .fi-section-header::after { content: "Mostra"; }
                        .fi-section.fi-collapsible > .fi-section-header > .fi-section-collapse-btn { order: 2; }
                        /* Freccia dopo l'etichetta dei tab a tendina della sub-navigation (es. "Staff",
                           "Presenze"), per far capire che aprono un sottomenu e non sono un link diretto. */
                        .fi-page-sub-navigation-tabs .fi-dropdown-trigger .fi-tabs-item::after {
                            content: "";
                            display: inline-block;
                            width: 12px;
                            height: 12px;
                            margin-inline-start: 2px;
                            background-color: currentColor;
                            -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
                            mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
                            -webkit-mask-size: contain;
                            mask-size: contain;
                            -webkit-mask-repeat: no-repeat;
                            mask-repeat: no-repeat;
                        }
                        /* Testo nelle celle selezionabile con il mouse */
                        .fi-ta-cell { user-select: text !important; }
                        /* Righe alternate */
                        .fi-ta-row:nth-child(even) td.fi-ta-cell { background-color: #f9fafb !important; }
                        /* Footer fisso */
                        #lurch-footer {
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
                            padding: 0 1.5rem;
                            z-index: 40;
                            font-size: 0.7rem;
                            color: #9ca3af;
                            gap: 1rem;
                        }
                        .dark #lurch-footer {
                            background: #1f2937;
                            border-top-color: #374151;
                            color: #6b7280;
                        }
                        .lurch-footer-center {
                            flex: 1;
                            text-align: center;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        }
                        .lurch-footer-left, .lurch-footer-right {
                            white-space: nowrap;
                            flex-shrink: 0;
                        }
                        /* Padding per non nascondere contenuti sotto il footer */
                        .fi-body { padding-bottom: 36px !important; }
                        /* Rich editor toolbar: va a capo su schermi stretti */
                        .fi-fo-rich-editor-toolbar { flex-wrap: wrap !important; }
                        .fi-fo-rich-editor-toolbar-group { flex-wrap: wrap !important; }
                        /* Rich editor content: altezza massima con scroll interno */
                        .fi-fo-rich-editor-content { max-height: 420px; overflow-y: auto; }
                    </style>
                    <script>
                        function applyRowTooltips() {
                            document.querySelectorAll('[data-row-tooltip]').forEach(cell => {
                                const row = cell.closest('tr');
                                if (row && !row.title) row.title = cell.dataset.rowTooltip;
                            });
                        }
                        document.addEventListener('DOMContentLoaded', () => {
                            applyRowTooltips();
                            new MutationObserver(applyRowTooltips).observe(document.body, { childList: true, subtree: true });
                        });
                        document.addEventListener('livewire:navigated', applyRowTooltips);

                        document.addEventListener('livewire:init', () => {
                            Livewire.hook('request', ({ fail }) => {
                                fail(({ status, preventDefault }) => {
                                    if (status !== 419) return;
                                    preventDefault();
                                    Livewire.pausePolling();
                                    if (document.getElementById('lurch-session-banner')) return;
                                    const banner = document.createElement('div');
                                    banner.id = 'lurch-session-banner';
                                    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#fef2f2;border-bottom:2px solid #f87171;padding:12px 20px;display:flex;align-items:center;gap:12px;font-size:14px;color:#991b1b;';
                                    banner.innerHTML = '<span style="font-size:18px;">⚠</span><span>La sessione è scaduta. Il polling è stato messo in pausa.</span><button onclick="window.location.reload()" style="margin-left:auto;background:#dc2626;color:#fff;border:none;border-radius:6px;padding:6px 14px;cursor:pointer;font-size:13px;font-weight:600;">Ricarica la pagina</button>';
                                    document.body.prepend(banner);
                                });
                            });
                        });
                    </script>
                    HTML)
            );
    }

    private function appVersion(): string { return config('app.version', '1.0.0'); }
    private function currentYear(): string { return date('Y'); }
    private function phpVersion(): string { return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; }
    private function laravelVersion(): string { return app()->version(); }
    private function appEnv(): string { return config('app.env', 'production'); }
}
