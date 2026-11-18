<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComitatoRegionaleResource\Pages;
use App\Models\ComitatoRegionale;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ComitatoRegionaleResource extends Resource
{
    use \App\Filament\Concerns\RispettaPermessiDellaRisorsa;

    protected static ?string $model = ComitatoRegionale::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static \UnitEnum|string|null $navigationGroup = 'Archivi';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'comitati-regionali';

    protected static ?string $modelLabel = 'Comitato Regionale';

    protected static ?string $pluralModelLabel = 'Comitati Regionali';

    // Solo il superadmin accede a questa risorsa.

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function form(Schema $form): Schema
    {
        return $form->columns(1)->schema([
            Section::make()->schema([
                Group::make()->columns(2)->schema([
                    Forms\Components\TextInput::make('nome')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('codice')
                        ->label('Codice (es. LOM)')
                        ->required()
                        ->maxLength(10)
                        ->unique(table: 'comitati_regionali', column: 'codice', ignoreRecord: true)
                        ->alphaDash()
                        ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                        ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                ]),

                Group::make()->columns(2)->schema([
                    Forms\Components\TextInput::make('rappresentante_legale')
                        ->label('Rappresentante legale')
                        ->maxLength(255)
                        ->placeholder('Es. Mario Rossi')
                        ->helperText('Usato nei testi privacy come {rappresentante_legale}.'),

                    Forms\Components\TextInput::make('email')
                        ->label('E-mail istituzionale')
                        ->email()
                        ->maxLength(255)
                        ->placeholder('Es. toscana@cri.it')
                        ->helperText('Usato nei testi privacy come {email}.'),
                ]),

                Forms\Components\TextInput::make('indirizzo')
                    ->label('Sede legale / Indirizzo')
                    ->maxLength(500)
                    ->placeholder('Es. Via Roma 1, 50100 Firenze')
                    ->helperText('Usato nei testi privacy come {indirizzo}.'),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true),
            ]),

            Section::make('Portale e branding')
                ->description('Dominio, logo e dati privacy della regione. Facoltativi: se vuoti valgono i valori generali del portale.')
                ->collapsed()
                ->schema([
                    Group::make()->columns(2)->schema([
                        Forms\Components\TextInput::make('url_portale')
                            ->label('Indirizzo del portale')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('Es. https://lurch.comitato.example')
                            ->unique(table: 'comitati_regionali', column: 'url_portale', ignoreRecord: true)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? rtrim(strtolower(trim($state)), '/') : null)
                            ->helperText('I link nelle email degli eventi della regione puntano qui.'),

                        Forms\Components\TextInput::make('privacy_responsabile')
                            ->label('Responsabile della protezione dei dati')
                            ->maxLength(255)
                            ->helperText('Usato nei testi privacy come {responsabile}.'),
                    ]),

                    Forms\Components\Placeholder::make('_anteprima_logo')
                        ->label('Logo attuale')
                        ->content(fn (?ComitatoRegionale $record) => new \Illuminate\Support\HtmlString(
                            '<img src="' . e($record->logoUrl()) . '" alt="' . e($record->nome) . '"'
                            . ' style="height:80px; width:auto; max-width:320px; object-fit:contain; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:6px;">'
                        ))
                        ->visible(fn (?ComitatoRegionale $record) => filled($record?->logo))
                        ->columnSpanFull(),

                    // Logo ufficiale: solo ridimensionamento proporzionale in visualizzazione, mai ritaglio.
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo del comitato')
                        ->image()
                        ->disk('public')
                        ->directory('comitati-regionali')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->maxSize(2048)
                        ->columnSpanFull(),
                ]),

            Section::make('Posta in uscita')
                ->description('Account SMTP della regione (es. Brevo): da qui partono le email degli eventi della regione e gli accessi dei suoi utenti. Senza account, gli invii degli eventi della regione sono bloccati.')
                ->collapsed()
                ->schema([
                    Group::make()->columns(3)->schema([
                        Forms\Components\TextInput::make('mail_host')
                            ->label('Server SMTP')
                            ->maxLength(255)
                            ->placeholder('Es. smtp-relay.brevo.com'),

                        Forms\Components\TextInput::make('mail_port')
                            ->label('Porta')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('587'),

                        Forms\Components\Select::make('mail_scheme')
                            ->label('Cifratura')
                            ->options([
                                'smtp'  => 'STARTTLS (porta 587)',
                                'smtps' => 'TLS diretto (porta 465)',
                            ])
                            ->placeholder('Automatica'),
                    ]),

                    Group::make()->columns(2)->schema([
                        Forms\Components\TextInput::make('mail_username')
                            ->label('Utente SMTP')
                            ->maxLength(255),

                        // La password salvata non viene mai rimandata al browser: campo vuoto = resta quella attuale.
                        Forms\Components\TextInput::make('mail_password')
                            ->label('Password SMTP')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->maxLength(500)
                            ->formatStateUsing(fn () => null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder(fn (?ComitatoRegionale $record) => $record?->haPasswordSmtp() ? '•••••••• (salvata, lascia vuoto per non cambiarla)' : null),
                    ]),

                    Group::make()->columns(2)->schema([
                        Forms\Components\TextInput::make('mail_from_address')
                            ->label('Indirizzo mittente')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('Es. lurch@comitato.example'),

                        Forms\Components\TextInput::make('mail_from_name')
                            ->label('Nome mittente')
                            ->maxLength(255)
                            ->placeholder('Es. CRI Comitato Regionale'),
                    ]),

                    Forms\Components\Placeholder::make('_stato_posta')
                        ->label('Stato')
                        ->content(fn (?ComitatoRegionale $record) => $record?->haPostaConfigurata()
                            ? 'Account completo.'
                            : 'Incompleto: servono server, porta, utente, password e indirizzo mittente.'),
                ]),
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
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codice')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitatiLocali_count')
                    ->counts('comitatiLocali')
                    ->label('Locali')
                    ->sortable(),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->label('Creato il')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('attivo')
                    ->label('Stato'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                ActionGroup::make([
                    RestoreAction::make(),
                    DeleteAction::make()
                        ->visible(fn (Model $record) => static::canDelete($record)),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->defaultSort('nome');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListComitatoRegionali::route('/'),
            'create' => Pages\CreateComitatoRegionale::route('/create'),
            'edit'   => Pages\EditComitatoRegionale::route('/{record}/edit'),
        ];
    }
}
