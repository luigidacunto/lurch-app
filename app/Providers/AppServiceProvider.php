<?php

namespace App\Providers;

use App\Models\CatalogoCorso;
use App\Models\CatalogoCorsoValiditaRegionale;
use App\Models\Corso;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Observers\CatalogoCorsoObserver;
use App\Observers\CatalogoCorsoValiditaRegionaleObserver;
use App\Observers\CorsoObserver;
use App\Observers\IscrizioneObserver;
use App\Observers\SocioObserver;
use App\Services\FormSectionsGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Support\View\Components\ModalComponent;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registra i Service come singleton — una sola istanza per request
        $this->app->singleton(FormSectionsGenerator::class);
    }

    public function boot(): void
    {
        // Disabilita il click sull'intera riga nelle tabelle Filament (permette la selezione del testo)
        Table::configureUsing(fn (Table $table) => $table->recordAction(null)->recordUrl(null));

        // Icona nell'input di ogni DatePicker/DateTimePicker/TimePicker del pannello — calendario
        // per le sole date, orologio per la sola ora, ENTRAMBE (calendario a sinistra, orologio a
        // destra) quando il campo combina data e ora — e niente selettore/formato secondi, solo
        // ore e minuti
        DateTimePicker::configureUsing(function (DateTimePicker $picker) {
            $picker->seconds(false);

            if ($picker->hasDate() && $picker->hasTime()) {
                return $picker
                    ->prefixIcon(Heroicon::CalendarDays, isInline: true)
                    ->suffixIcon(Heroicon::Clock, isInline: true);
            }

            return $picker->suffixIcon($picker->hasTime() ? Heroicon::Clock : Heroicon::CalendarDays, isInline: true);
        });

        // Nessuna modale del pannello si chiude con ESC o click fuori, di default — le usiamo sempre
        // come blocco pagina (form da compilare, conferma azione), mai come notifica passiva (per
        // quello ci sono i toast). Un'eventuale modale puramente informativa va riabilitata caso per
        // caso con ->closeModalByClickingAway(true)->closeModalByEscaping(true).
        ModalComponent::closedByClickingAway(false);
        ModalComponent::closedByEscaping(false);

        // Observer socio: gestisce cambio email e log
        Socio::observe(SocioObserver::class);

        // Observer per rilevamento sovrapposizioni date iscrizioni
        Iscrizione::observe(IscrizioneObserver::class);
        Corso::observe(CorsoObserver::class);

        // Observer catalogo corsi: ricalcolo scadenze in background quando cambiano le regole
        CatalogoCorso::observe(CatalogoCorsoObserver::class);
        CatalogoCorsoValiditaRegionale::observe(CatalogoCorsoValiditaRegionaleObserver::class);

        // Rate limiter per le code email
        RateLimiter::for('email_corso', function (Request $request) {
            return Limit::perDay(80)->by('resend');
        });

        RateLimiter::for('email_default', function (Request $request) {
            return Limit::perDay(15)->by('resend');
        });
    }
}
