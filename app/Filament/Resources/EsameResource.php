<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EsameResource\Pages;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\User;
use Filament\Actions;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Esami (TCRI per ora). Si creano solo dalla pagina Gestione corso (nessuna pagina "create").
 * Visibilità: vedi Esame::scopeVisibiliA().
 */
class EsameResource extends Resource
{
    protected static ?string $model = Esame::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestione operativa';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'esami';

    protected static ?string $modelLabel = 'Esame';

    protected static ?string $pluralModelLabel = 'Esami';

    protected static ?string $recordTitleAttribute = 'titolo';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || CorsoResource::eDirettoreDiQualcheCorso($user)
            // Presidente/delegato e docenti di commissione leggono (non gestiscono) l'esame.
            || ($user && Esame::query()->leggibiliA($user)->exists());
    }

    /** Gli esami si creano solo da Gestione corso: mai dalla Resource. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Chi vede l'esame lo gestisce (visibilità = gestione); l'operatore non direttore non lo vede affatto. */
    public static function canEdit(Model $record): bool
    {
        return Esame::query()->visibiliA(auth()->user())->whereKey($record->getKey())->exists();
    }

    /** Si cancella solo in bozza e senza dati: la configurazione sparisce a cascata. */
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && $record->isBozza() && ! $record->hasDatiCorrelati();
    }

    public static function getEloquentQuery(): Builder
    {
        // Lettura: superset di «gestione». Le pagine di gestione si proteggono con canEdit (visibiliA).
        return parent::getEloquentQuery()->with('corso.evento')->leggibiliA(auth()->user());
    }

    /** Chi può creare l'esame dal corso: gli stessi soggetti che poi lo vedranno. */
    public static function puoCreareDaCorso(Corso $corso, ?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperadmin() || CorsoResource::eDirettoreDelCorso($user, $corso)) {
            return true;
        }
        if ($user->isAdmin()) {
            return $corso->evento?->comitato_regionale_id === $user->comitato_regionale_id;
        }

        return $user->isAdminLocale() && $corso->evento?->comitato_locale_id === $user->comitato_locale_id;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titolo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('corso.nome')->label('Corso')->searchable(),
                Tables\Columns\TextColumn::make('corso.direttore_corso')->label('Direttore'),
                Tables\Columns\TextColumn::make('tipo_codice')->label('Tipo'),
                Tables\Columns\TextColumn::make('stato')
                    ->badge()
                    ->formatStateUsing(fn ($state) => static::etichettaStato($state))
                    ->color(fn ($state) => $state === Esame::STATO_BOZZA ? 'gray' : 'info'),
            ])
            // Chi gestisce apre Gestione; chi solo legge (commissione) apre le Valutazioni in sola lettura.
            ->recordUrl(fn (Esame $record) => static::canEdit($record)
                ? static::getUrl('gestione', ['record' => $record])
                : static::getUrl('valutazioni', ['record' => $record]))
            ->recordActions([
                Actions\Action::make('gestione')
                    ->label('Gestione')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Gestione')
                    ->visible(fn (Esame $record) => static::canEdit($record))
                    ->url(fn (Esame $record) => static::getUrl('gestione', ['record' => $record])),
                Actions\Action::make('valutazioni')
                    ->label('Valutazioni')
                    ->icon('heroicon-o-presentation-chart-bar')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Valutazioni')
                    ->visible(fn (Esame $record) => $record->puoVedereRiepilogoItinere(auth()->user()))
                    ->url(fn (Esame $record) => static::getUrl('valutazioni', ['record' => $record])),
                Actions\EditAction::make()->iconButton()->tooltip('Modifica'),
                Actions\ActionGroup::make([
                    Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Elimina esame')
                        ->modalDescription('L\'esame è in bozza e senza dati: verranno eliminati anche commissione, laboratori, gruppi e tutoraggio configurati.')
                        ->visible(fn (Esame $record) => static::canDelete($record)),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Breadcrumb unica per tutte le pagine di un esame:
     * Esami › titolo (etichetta, senza link) › Sezione (gestione|valutazioni) › [intermedi] › Pagina.
     * Il titolo non ha link: la sezione sarebbe la stessa destinazione (nessun livello duplicato). Senza `$pagina` si è
     * sulla radice della sezione (etichetta); senza `$sezione` (es. Modifica) la pagina segue direttamente il titolo.
     * «Dashboard» lo antepone il layout.
     */
    public static function breadcrumbs(Esame $record, ?string $sezione = null, ?string $pagina = null, array $intermedi = []): array
    {
        $crumbs = [static::getUrl('index') => static::getBreadcrumb()];
        $crumbs[] = $record->titolo;
        if ($sezione !== null) {
            $etichetta = ucfirst($sezione);
            if ($pagina === null) {
                $crumbs[] = $etichetta;

                return $crumbs;
            }
            $crumbs[static::getUrl($sezione, ['record' => $record])] = $etichetta;
        }
        foreach ($intermedi as $url => $etichetta) {
            $crumbs[$url] = $etichetta;
        }
        if ($pagina !== null) {
            $crumbs[] = $pagina;
        }

        return $crumbs;
    }

    public static function etichettaStato(?string $stato): string
    {
        return match ($stato) {
            Esame::STATO_BOZZA    => 'Bozza',
            Esame::STATO_IN_CORSO => 'In corso',
            default               => 'Chiuso',
        };
    }

    /** Tab bar in alto per le pagine dello stesso esame (stesso pattern di CorsoResource). */
    public static function getRecordSubNavigation(Page $page): array
    {
        $record = $page->getRecord();

        $tab = fn (string $route, string $label, string $icon) => NavigationItem::make($label)
            ->icon($icon)
            ->url(static::getUrl($route, ['record' => $record]))
            ->isActiveWhen(fn () => $page instanceof (static::getPages()[$route]->getPage()));

        // Due barre in base alla pagina: configurazione (sempre, finché si gestisce) e svolgimento
        // (dopo l'attivazione). Un link in coda passa dall'una all'altra.
        // ⚠️ Mai request()->routeIs(): nelle azioni Livewire la richiesta è livewire/update e la barra tornerebbe a Gestione.
        $inSvolgimento = collect(['valutazioni', 'itinere', 'test-scritto', 'prova-pratica', 'prova-pratica-finale', 'colloquio', 'test-cartaceo', 'esito', 'riepilogo-discenti'])
            ->contains(fn (string $r) => $page instanceof (static::getPages()[$r]->getPage()));
        $puoGestire = static::canEdit($record);
        $puoSvolgimento = $record->puoVedereRiepilogoItinere(auth()->user());
        $vaiA = fn (string $route, string $label, string $icon) => NavigationItem::make($label)
            ->icon($icon)
            ->url(static::getUrl($route, ['record' => $record]))
            ->isActiveWhen(fn () => false);

        if (! $inSvolgimento && $puoGestire) {
            $tabs = [
                $tab('gestione', 'Panoramica', 'heroicon-o-chart-bar'),
                $tab('discenti', 'Discenti', 'heroicon-o-users'),
                $tab('laboratori', 'Laboratori e gruppi', 'heroicon-o-beaker'),
                $tab('tutoraggio', 'Tutoraggio', 'heroicon-o-academic-cap'),
                $tab('domande-test', 'Domande test', 'heroicon-o-clipboard-document-check'),
            ];
            if ($puoSvolgimento) {
                $tabs[] = $vaiA('valutazioni', 'Vai alle valutazioni', 'heroicon-o-arrow-right');
            }

            return $tabs;
        }

        // Svolgimento. Chi solo legge (commissione) vede soltanto questa barra, senza link di ritorno.
        $tabs = [];
        if ($puoSvolgimento) {
            $tabs[] = $tab('valutazioni', 'Valutazioni', 'heroicon-o-presentation-chart-bar');
            $tabs[] = $tab('itinere', 'Itinere', 'heroicon-o-clipboard-document-list');
            $tabs[] = $tab('test-scritto', 'Test scritto', 'heroicon-o-pencil-square');
            $tabs[] = $tab('prova-pratica', 'Prova pratica', 'heroicon-o-wrench-screwdriver');
            $tabs[] = $tab('colloquio', 'Autovalutazione', 'heroicon-o-chat-bubble-left-right');
            $tabs[] = $tab('esito', 'Esito', 'heroicon-o-trophy');
            // Riepilogo dei punteggi per discente (con la proiezione): solo chi gestisce, in fondo a tutte le prove.
            if ($puoGestire) {
                $tabs[] = $tab('riepilogo-discenti', 'Discenti', 'heroicon-o-users');
            }
        }
        if ($puoGestire) {
            $tabs[] = $vaiA('gestione', 'Gestione', 'heroicon-o-arrow-left');
        }

        return $tabs;
    }

    public static function getPages(): array
    {
        return [
            'index'       => Pages\ListEsami::route('/'),
            'gestione'    => Pages\GestioneEsame::route('/{record}/gestione'),
            'edit'        => Pages\EditEsame::route('/{record}/edit'),
            'discenti'    => Pages\DiscentiEsame::route('/{record}/gestione/discenti'),
            'laboratori'  => Pages\GestioneLaboratori::route('/{record}/gestione/laboratori'),
            'tutoraggio'  => Pages\GestioneTutoraggio::route('/{record}/gestione/tutoraggio'),
            'valutazioni' => Pages\ValutazioniEsame::route('/{record}/valutazioni'),
            'itinere'     => Pages\RiepilogoItinereEsame::route('/{record}/valutazioni/itinere'),
            'prova-pratica' => Pages\ProvaPraticaEsame::route('/{record}/valutazioni/prova-pratica'),
            'colloquio' => Pages\ColloquioEsame::route('/{record}/valutazioni/autovalutazione'),
            'prova-pratica-finale' => Pages\InserisciProvaPraticaFinale::route('/{record}/valutazioni/prova-pratica/{discente}/finale'),
            'test-scritto' => Pages\TestScrittoEsame::route('/{record}/valutazioni/test-scritto'),
            'domande-test' => Pages\DomandeTestEsame::route('/{record}/gestione/domande-test'),
            'esito' => Pages\EsitoEsame::route('/{record}/valutazioni/esito'),
            'riepilogo-discenti' => Pages\RiepilogoDiscentiEsame::route('/{record}/valutazioni/discenti'),
            'test-cartaceo' => Pages\InserisciTestCartaceo::route('/{record}/valutazioni/test-scritto/{discente}/cartaceo'),
        ];
    }
}
