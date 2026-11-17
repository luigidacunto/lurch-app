<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Socio;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'utenti';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static \UnitEnum|string|null $navigationGroup = 'Impostazioni';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Utente';

    protected static ?string $pluralModelLabel = 'Utenti';

    /**
     * Permessi granulari assegnabili agli operatori: solo quelli che il codice controlla davvero,
     * con nome e spiegazione in italiano. Gli altri permessi presenti nel database (senza effetto)
     * non compaiono nella scheda ma, se già assegnati, restano: vedi EditUser::afterSave().
     */
    public const PERMESSI_OPERATORE = [
        'eventi.view'                   => ['Vede gli eventi', 'La sezione Eventi, con i soli eventi regionali a cui è assegnato.'],
        'eventi.create'                 => ['Crea eventi', 'Pulsante «Nuovo» nella sezione Eventi.'],
        'eventi.edit'                   => ['Gestisce gli eventi', 'Apre e modifica gli eventi a cui è assegnato: staff, partecipanti, presenze, report.'],
        'corsi.view'                    => ['Vede e gestisce i corsi', 'I corsi degli eventi a cui è assegnato: iscritti, moduli, comunicazioni.'],
        'corsi.create'                  => ['Crea corsi', 'Pulsante «Nuovo» nella sezione Corsi.'],
        'corsi.edit'                    => ['Modifica i corsi', 'I dati dei corsi degli eventi a cui è assegnato.'],
        'iscritti.import'               => ['Importa iscritti', 'Importazione degli iscritti di un corso da file.'],
        'checkin.manage'                => ['Fa il check-in', 'Entrate e uscite dal badge, negli eventi a cui è assegnato.'],
        'graduatorie.manage'            => ['Gestisce le graduatorie', 'Le graduatorie in cui è tra gli operatori abilitati.'],
        'qualifiche.view'               => ['Cerca le qualifiche', 'Ricerca dei soci per qualifica ed export, nel suo territorio.'],
        'qualifiche.manage'             => ['Gestisce le qualifiche', 'Come sopra, e aggiunge o modifica le qualifiche dei soci.'],
        'qualifiche.validita_regionale' => ['Validità regionali', 'Scadenze regionali dei corsi nel Catalogo corsi.'],
        'soci.view'                     => ['Vede i soci', 'La sezione Soci del suo territorio.'],
        'soci.create'                   => ['Crea soci', 'Inserimento a mano di un nuovo socio.'],
        'soci.edit'                     => ['Modifica i soci', 'Anagrafica dei soci della sua regione.'],
        'soci.import'                   => ['Importa i soci', 'Importazione dei soci da GAIA.'],
        'soci.view_sensitive'           => ['Vede i dati sanitari', 'Allergie, diete e note alimentari dei soci.'],
    ];

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminLocale();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminLocale();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        // Admin regionale autonomo sulla propria regione: gestisce admin regionali, admin_locale
        // e operatori della regione (non sé stesso, per non disattivarsi o declassarsi da solo).
        if ($user?->isAdmin()) {
            return in_array($record->ruolo, ['admin', 'admin_locale', 'operatore'])
                && $record->comitato_regionale_id === $user->comitato_regionale_id
                && $record->getKey() !== $user->getKey();
        }
        // Admin locale può modificare solo gli operatori del proprio comitato locale
        if ($user?->isAdminLocale()) {
            return $record->ruolo === 'operatore'
                && $record->comitato_locale_id === $user->comitato_locale_id;
        }
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return ! $record->isSuperadmin();
        // Admin regionale: stessa competenza della modifica. Admin locale: nessuna eliminazione.
        if ($user?->isAdmin()) return static::canEdit($record);
        return false;
    }

    /** Reset sessioni: stessa competenza della modifica, mai su sé stessi. */
    public static function canResetSessioni(Model $record): bool
    {
        return $record->getKey() !== auth()->id() && static::canEdit($record);
    }

    /**
     * Scope: superadmin vede tutto, admin vede admin regionali, admin_locale e operatori della
     * propria regione, admin_locale vede solo gli operatori del proprio comitato locale.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user?->isAdmin()) {
            $query->where('comitato_regionale_id', $user->comitato_regionale_id)
                  ->whereIn('ruolo', ['admin', 'admin_locale', 'operatore']);
        } elseif ($user?->isAdminLocale()) {
            $query->where('comitato_locale_id', $user->comitato_locale_id)
                  ->where('ruolo', 'operatore');
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $authUser = auth()->user();

        // Ruoli selezionabili in base a chi sta creando
        $ruoliDisponibili = $authUser?->isSuperadmin()
            ? ['admin' => 'Admin Regionale', 'admin_locale' => 'Admin Locale', 'operatore' => 'Operatore']
            : ($authUser?->isAdminLocale()
                ? ['operatore' => 'Operatore']
                : ['admin' => 'Admin Regionale', 'admin_locale' => 'Admin Locale', 'operatore' => 'Operatore']);

        // Regioni selezionabili
        $regioniOptions = $authUser?->isSuperadmin()
            ? ComitatoRegionale::orderBy('nome')->pluck('nome', 'id')
            : ComitatoRegionale::where('id', $authUser?->comitato_regionale_id)->pluck('nome', 'id');

        // Comitati locali selezionabili: admin solo la propria regione, admin_locale solo il proprio comitato
        $localiQuery = ComitatoLocale::orderBy('nome');
        if ($authUser?->isAdmin()) {
            $localiQuery->where('comitato_regionale_id', $authUser->comitato_regionale_id);
        } elseif ($authUser?->isAdminLocale()) {
            $localiQuery->where('id', $authUser->comitato_locale_id);
        }
        $localiOptions = $localiQuery->pluck('nome', 'id');

        // Lista permessi granulari
        // Solo i permessi che esistono nel database (altrimenti syncPermissions() fallirebbe).
        $permessi = array_intersect_key(
            static::PERMESSI_OPERATORE,
            array_flip(Permission::where('guard_name', 'web')->pluck('name')->all())
        );

        return $form->schema([
            Section::make('Anagrafica')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome completo')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(table: 'users', column: 'email', ignoreRecord: true)
                    ->autocomplete('off')
                    ->maxLength(255),

                // new-password: il browser non deve compilare qui le credenziali salvate di chi sta operando.
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->autocomplete('new-password')
                    ->minLength(8)
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn (string $context) => $context === 'edit'
                        ? 'Lascia vuoto per mantenere la password attuale (o quella già disabilitata).'
                        : 'Lascia vuoto per creare l\'utente senza password: potrà accedere solo tramite link/codice via email.'),

                Forms\Components\Select::make('socio_id')
                    ->label('Socio collegato (opzionale)')
                    ->helperText('Cerca per codice fiscale, nome o cognome. Facilita l\'operatività ma non è richiesto.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Socio::query()
                        ->where('codice_fiscale', 'ilike', "%{$search}%")
                        ->orWhere('nome', 'ilike', "%{$search}%")
                        ->orWhere('cognome', 'ilike', "%{$search}%")
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Socio $s) => [$s->id => "{$s->cognome} {$s->nome} — {$s->codice_fiscale} — {$s->email}"]))
                    ->getOptionLabelUsing(fn ($value) => optional(Socio::find($value))->nome_completo)
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (! $state) return;
                        $socio = Socio::find($state);
                        if (! $socio) return;
                        $set('name', $socio->nome_completo);
                        $set('email', $socio->email);
                    })
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Ruolo e territorio')
                ->visible(fn (?Model $record) => ! ($record?->isSuperadmin() ?? false))
                ->schema([
                Forms\Components\Select::make('ruolo')
                    ->label('Ruolo')
                    ->options($ruoliDisponibili)
                    ->default('operatore')
                    ->required(fn (?Model $record) => ! ($record?->isSuperadmin() ?? false))
                    ->searchable()
                    ->live(),

                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato Regionale')
                    ->options($regioniOptions)
                    ->default($authUser?->isAdmin() || $authUser?->isAdminLocale() ? $authUser->comitato_regionale_id : null)
                    ->required(fn (Get $get, ?Model $record) => ! ($record?->isSuperadmin() ?? false) && $get('ruolo') !== 'admin_locale')
                    ->searchable()
                    ->disabled($authUser?->isAdmin() || $authUser?->isAdminLocale())
                    ->dehydrated(true),

                Forms\Components\Select::make('comitato_locale_id')
                    ->label('Comitato Locale')
                    ->options($localiOptions)
                    ->default($authUser?->isAdminLocale() ? $authUser->comitato_locale_id : null)
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->visible(fn (Get $get) => $get('ruolo') === 'admin_locale' || ($get('ruolo') === 'operatore' && $authUser?->isAdminLocale()))
                    ->required(fn (Get $get) => $get('ruolo') === 'admin_locale' || ($get('ruolo') === 'operatore' && $authUser?->isAdminLocale()))
                    ->disabled($authUser?->isAdminLocale())
                    ->dehydrated(true)
                    ->afterStateUpdated(function (?int $state, Set $set) {
                        if ($state) {
                            $locale = ComitatoLocale::find($state);
                            $set('comitato_regionale_id', $locale?->comitato_regionale_id);
                        }
                    }),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true),
            ])->columns(2),

            Section::make('Permessi granulari')
                ->description('Visibili e assegnabili solo agli operatori.')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('Permessi abilitati')
                        ->options(array_map(fn (array $p) => $p[0], $permessi))
                        ->descriptions(array_map(fn (array $p) => $p[1], $permessi))
                        ->columns(3)
                        ->bulkToggleable(),
                ])
                ->visible(fn (Get $get) => $get('ruolo') === 'operatore')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->color('success'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ruolo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'superadmin'  => 'Superadmin',
                        'admin'       => 'Admin Regionale',
                        'admin_locale' => 'Admin Locale',
                        'operatore'   => 'Operatore',
                        default       => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'superadmin'   => 'danger',
                        'admin'        => 'warning',
                        'admin_locale' => 'info',
                        'operatore'    => 'success',
                        default        => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Regione')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitatoLocale.nome')
                    ->label('Comitato Locale')
                    ->searchable()
                    ->sortable()
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('socio.nome_completo')
                    ->label('Socio collegato')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Metodo di accesso al pannello. password=null → solo email (link/OTP), è lo
                // stato normale: User::completaLoginEmail() azzera la password al primo accesso
                // email riuscito. password valorizzata → password temporanea ancora attiva.
                Tables\Columns\TextColumn::make('password')
                    ->label('Login')
                    ->badge()
                    ->icon(fn (?string $state) => $state === null ? 'heroicon-m-envelope' : 'heroicon-m-key')
                    ->formatStateUsing(fn (?string $state) => $state === null ? 'Email' : 'Password temp.')
                    ->color(fn (?string $state) => $state === null ? 'gray' : 'warning')
                    ->tooltip(fn (?string $state) => $state === null
                        ? 'Accede solo via email (link o codice OTP) — è lo stato normale.'
                        : 'Ha una password temporanea attiva: verrà azzerata al primo accesso via email.'),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->sortable(),

                // Aggiornata al massimo ogni 5 minuti (RegistraAttivitaPannello).
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Ultima attività')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('Mai')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->label('Creato il')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ruolo')
                    ->options([
                        'superadmin'   => 'Superadmin',
                        'admin'        => 'Admin Regionale',
                        'admin_locale' => 'Admin Locale',
                        'operatore'    => 'Operatore',
                    ])
                    ->visible(fn () => auth()->user()?->isSuperadmin()),

                Tables\Filters\SelectFilter::make('comitato_regionale_id')
                    ->label('Regione')
                    ->relationship('comitatoRegionale', 'nome')
                    ->visible(fn () => auth()->user()?->isSuperadmin()),

                Tables\Filters\TernaryFilter::make('attivo')
                    ->label('Stato'),
            ])
            ->actions([
                \Filament\Actions\Action::make('passwordTemporanea')
                    ->label('Password temporanea')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Password temporanea')
                    ->visible(fn () => auth()->user()?->isSuperadmin())
                    ->requiresConfirmation()
                    ->modalDescription('Genera una nuova password temporanea per questo utente, riattiva il login con password e disconnette tutte le sue sessioni attive. Comunicala manualmente insieme al link di accesso.')
                    ->action(function (User $record) {
                        $password = \Illuminate\Support\Str::password(16);
                        $record->update(['password' => Hash::make($password)]);
                        $record->invalidaSessioni();

                        \Filament\Notifications\Notification::make()
                            ->title('Password temporanea impostata per ' . $record->name)
                            ->body("Password: {$password}\nLink di accesso: " . url('/admin/login?password=1'))
                            ->warning()
                            ->persistent()
                            ->send();
                    }),

                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                ActionGroup::make([
                    \Filament\Actions\Action::make('resetSessioni')
                        ->label('Reset sessioni')
                        ->icon('heroicon-o-arrow-right-start-on-rectangle')
                        ->color('danger')
                        ->visible(fn (User $record) => static::canResetSessioni($record))
                        ->requiresConfirmation()
                        ->modalHeading('Reset sessioni')
                        ->modalDescription('Disconnette l\'utente da tutti i dispositivi. Il metodo di accesso (email o password) resta valido: in caso di account compromesso, cambia anche la password o disattiva l\'utente.')
                        ->modalSubmitActionLabel('Sì, disconnetti')
                        ->action(function (User $record) {
                            // Nessun "ricordami" nel pannello (AdminLogin): basta chiudere le sessioni.
                            $record->invalidaSessioni();
                            \Illuminate\Support\Facades\Log::info('Reset sessioni utente pannello', [
                                'utente_id' => $record->id,
                                'eseguito_da' => auth()->id(),
                            ]);
                            \Filament\Notifications\Notification::make()
                                ->title('Sessioni chiuse per ' . $record->name)
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->visible(fn (Model $record) => static::canDelete($record)),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
