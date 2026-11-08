<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\ComitatoRegionale;
use App\Models\EmailTemplate;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $slug = 'template-email';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static \UnitEnum|string|null $navigationGroup = 'Impostazioni';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Template email';

    protected static ?string $pluralModelLabel = 'Template email';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            // Solo il proprio override regionale — il default globale (null) è riservato al superadmin
            return $record->comitato_regionale_id !== null
                && $record->comitato_regionale_id === $user->comitato_regionale_id;
        }
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function form(Schema $form): Schema
    {
        $user = auth()->user();

        $regioniOptions = $user?->isSuperadmin()
            ? ComitatoRegionale::orderBy('nome')->pluck('nome', 'id')
            : ComitatoRegionale::where('id', $user->comitato_regionale_id)->pluck('nome', 'id');

        $segnapostoIscritti = <<<'TXT'
{nome} {cognome} — nome e cognome dell'iscritto
{nome_corso} — nome del corso
{protocollo} — numero di protocollo (vuoto se assente)
{comitato_regionale} — comitato regionale organizzatore
{comitato_locale} — comitato locale (vuoto se assente)
{quota} — quota formattata "€ X,XX" (vuoto se assente)
{link_form} — pulsante + URL del form (HTML)
{scadenza} — data scadenza compilazione (data invio + 7 giorni)
{istruzioni_pagamento} — testo istruzioni pagamento della regione
{sezione_pagamento} — blocco completo pagamento (visibile solo se il corso prevede pagamento)
{referenti} — lista HTML dei referenti (nome + email; vuoto se nessuno assegnato)
{sezione_referenti} — blocco completo "Contatti per informazioni" con tabella nome/email
TXT;

        $segnapostoSollecitoIscritti = <<<'TXT'
{nome} {cognome} — nome e cognome dell'iscritto
{nome_corso} — nome del corso
{protocollo} — numero di protocollo (vuoto se assente)
{comitato_regionale} — comitato regionale organizzatore
{comitato_locale} — comitato locale (vuoto se assente)
{quota} — quota formattata "€ X,XX" (vuoto se assente)
{link_form} — pulsante + URL del form (HTML)
{scadenza} — data scadenza compilazione (data di invio di QUESTO sollecito + 1 giorno)
{istruzioni_pagamento} — testo istruzioni pagamento della regione
{sezione_pagamento} — blocco completo pagamento (visibile solo se il corso prevede pagamento)
{referenti} — lista HTML dei referenti (nome + email; vuoto se nessuno assegnato)
{sezione_referenti} — blocco completo "Contatti per informazioni" con tabella nome/email
TXT;

        $segnapostoStaff = <<<'TXT'
{nome} {cognome} — nome e cognome del membro staff
{nome_evento} — nome dell'evento
{comitato_regionale} — comitato regionale organizzatore
{comitato_locale} — comitato locale (vuoto se assente)
{link_form} — pulsante + URL del form staff (HTML)
{scadenza} — data scadenza compilazione (data invio + 7 giorni)
{referenti} — lista HTML dei referenti (nome + email; vuoto se nessuno assegnato)
{sezione_referenti} — blocco completo "Contatti per informazioni" con tabella nome/email
TXT;

        $segnapostoSollecitoStaff = <<<'TXT'
{nome} {cognome} — nome e cognome del membro staff
{nome_evento} — nome dell'evento
{comitato_regionale} — comitato regionale organizzatore
{comitato_locale} — comitato locale (vuoto se assente)
{link_form} — pulsante + URL del form staff (HTML)
{scadenza} — data scadenza compilazione (data di invio di QUESTO sollecito + 1 giorno)
{referenti} — lista HTML dei referenti (nome + email; vuoto se nessuno assegnato)
{sezione_referenti} — blocco completo "Contatti per informazioni" con tabella nome/email
TXT;

        $segnapostoGraduatoria = <<<'TXT'
{nome} {cognome} — nome e cognome del candidato
{titolo_graduatoria} — titolo della graduatoria
{codice_protocollo} — codice di protocollo della graduatoria (opzionale, vuoto se non impostato)
{comitato_regionale} — comitato regionale organizzatore
{comitato_locale} — comitato locale (vuoto se assente)
{testo_graduatoria} — testo libero opzionale impostato sulla graduatoria (campo "Testo email di conferma")
TXT;

        return $form->schema([
            Section::make()->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'iscritti'          => 'Iscritti ai corsi',
                        'sollecito_iscritti' => 'Sollecito iscritti (reminder)',
                        'staff'             => 'Staff evento',
                        'sollecito_staff'   => 'Sollecito staff (reminder)',
                        'graduatoria'       => 'Candidatura graduatoria',
                    ])
                    ->default('iscritti')
                    ->required()
                    ->searchable()
                    ->live()
                    ->rules(function ($get, ?Model $record) {
                        return [
                            function ($attribute, $value, $fail) use ($get, $record) {
                                $regioneId = $get('comitato_regionale_id');
                                $query = EmailTemplate::where('tipo', $value);
                                if ($regioneId) {
                                    $query->where('comitato_regionale_id', $regioneId);
                                } else {
                                    $query->whereNull('comitato_regionale_id');
                                }
                                if ($record?->id) {
                                    $query->where('id', '!=', $record->id);
                                }
                                if ($query->exists()) {
                                    $scope = $regioneId ? 'regionale' : 'default globale';
                                    $fail("Esiste già un template {$scope} per il tipo \"{$value}\". Modifica quello esistente.");
                                }
                            }
                        ];
                    }),

                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato regionale')
                    ->options($regioniOptions)
                    ->default($user?->isAdmin() ? $user->comitato_regionale_id : null)
                    ->placeholder('— Default globale —')
                    ->helperText('Lascia vuoto per il default globale, valorizza per un override regionale.')
                    ->nullable()
                    ->searchable()
                    ->disabled($user?->isAdmin()),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true)
                    ->inline(false),

                Forms\Components\TextInput::make('soggetto')
                    ->label('Oggetto email')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Puoi usare i segnaposto anche nell\'oggetto: es. "Iscrizione confermata — {nome_corso}"'),

                Forms\Components\RichEditor::make('corpo')
                    ->label('Corpo email')
                    ->required()
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold', 'italic', 'underline',
                        'h2', 'h3',
                        'bulletList', 'orderedList',
                        'link',
                        'undo', 'redo',
                    ]),

                Forms\Components\Placeholder::make('segnaposto_iscritti')
                    ->label('Segnaposto disponibili')
                    ->content($segnapostoIscritti)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('tipo') === 'iscritti'),

                Forms\Components\Placeholder::make('segnaposto_sollecito_iscritti')
                    ->label('Segnaposto disponibili')
                    ->content($segnapostoSollecitoIscritti)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('tipo') === 'sollecito_iscritti'),

                Forms\Components\Placeholder::make('segnaposto_staff')
                    ->label('Segnaposto disponibili')
                    ->content($segnapostoStaff)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('tipo') === 'staff'),

                Forms\Components\Placeholder::make('segnaposto_sollecito_staff')
                    ->label('Segnaposto disponibili')
                    ->content($segnapostoSollecitoStaff)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('tipo') === 'sollecito_staff'),

                Forms\Components\Placeholder::make('segnaposto_graduatoria')
                    ->label('Segnaposto disponibili')
                    ->content($segnapostoGraduatoria)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('tipo') === 'graduatoria'),
            ])->columns(2),

            Section::make('Mittente e risposta')
                ->description('Lascia vuoti per usare le impostazioni globali del server (MAIL_FROM_* nel .env).')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('from_name')
                        ->label('Nome mittente')
                        ->placeholder(config('mail.from.name'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('from_address')
                        ->label('Indirizzo mittente (From)')
                        ->email()
                        ->placeholder(config('mail.from.address'))
                        ->maxLength(255),

                    Forms\Components\TextInput::make('reply_to')
                        ->label('Indirizzo risposta (Reply-To)')
                        ->email()
                        ->maxLength(255)
                        ->helperText('Le risposte del destinatario arriveranno a questo indirizzo invece che al mittente.')
                        ->columnSpanFull(),
                ])->columns(2),
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
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'iscritti'          => 'Iscritti corsi',
                        'sollecito_iscritti' => 'Sollecito iscritti',
                        'staff'             => 'Staff evento',
                        'sollecito_staff'   => 'Sollecito staff',
                        'graduatoria'       => 'Candidatura graduatoria',
                        default             => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'iscritti'          => 'primary',
                        'sollecito_iscritti' => 'warning',
                        'staff'             => 'info',
                        'sollecito_staff'   => 'warning',
                        'graduatoria'       => 'success',
                        default             => 'gray',
                    }),

                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Scope')
                    ->default('Default globale')
                    ->badge()
                    ->color(fn ($state) => $state === 'Default globale' ? 'gray' : 'warning'),

                Tables\Columns\TextColumn::make('soggetto')
                    ->label('Oggetto')
                    ->limit(60)
                    ->searchable(),

                Tables\Columns\TextColumn::make('from_address')
                    ->label('From')
                    ->state(fn ($record) => $record->from_address
                        ? ($record->from_name ? "{$record->from_name} <{$record->from_address}>" : $record->from_address)
                        : null)
                    ->formatStateUsing(fn ($state) => filled($state)
                        ? '<span title="' . e($state) . '" style="color:#16a34a;font-size:1rem;cursor:help;">✓</span>'
                        : '<span style="color:#d1d5db;">—</span>')
                    ->html(),

                Tables\Columns\TextColumn::make('reply_to')
                    ->label('Reply-To')
                    ->formatStateUsing(fn ($state) => filled($state)
                        ? '<span title="' . e($state) . '" style="color:#16a34a;font-size:1rem;cursor:help;">✓</span>'
                        : '<span style="color:#d1d5db;">—</span>')
                    ->html(),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->label('Attivo'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                ActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (Model $record) => static::canDelete($record)),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->defaultSort('comitato_regionale_id');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit'   => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
