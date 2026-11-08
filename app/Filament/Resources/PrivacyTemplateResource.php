<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrivacyTemplateResource\Pages;
use App\Models\ComitatoRegionale;
use App\Models\PrivacyTemplate;
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

class PrivacyTemplateResource extends Resource
{
    protected static ?string $model = PrivacyTemplate::class;

    protected static ?string $slug = 'template-privacy';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static \UnitEnum|string|null $navigationGroup = 'Impostazioni';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Template privacy';

    protected static ?string $pluralModelLabel = 'Template privacy';

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
        return static::canEdit($record);
    }

    public static function form(Schema $form): Schema
    {
        $user = auth()->user();

        $regioniOptions = $user?->isSuperadmin()
            ? ComitatoRegionale::orderBy('nome')->pluck('nome', 'id')
            : ComitatoRegionale::where('id', $user->comitato_regionale_id)->pluck('nome', 'id');

        return $form->schema([
            Section::make()->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'informativa_dati' => 'Informativa dati (art. 13 GDPR)',
                        'foto_video'       => 'Consenso foto e video',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato regionale')
                    ->options($regioniOptions)
                    ->placeholder('— Default globale —')
                    ->helperText('Lascia vuoto per impostare il default globale, valorizza per un override regionale.')
                    ->nullable()
                    ->searchable(),

                Forms\Components\Textarea::make('testo')
                    ->label('Testo informativa')
                    ->required()
                    ->rows(12)
                    ->columnSpanFull()
                    ->helperText('Usa {titolare}, {rappresentante_legale}, {indirizzo}, {email} come segnaposto — verranno sostituiti automaticamente con i dati del comitato organizzatore; {responsabile} con il responsabile del trattamento configurato per il portale.'),

                Forms\Components\FileUpload::make('pdf_path')
                    ->label('PDF allegato (opzionale)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->disk('public')
                    ->directory('privacy-pdf')
                    ->downloadable()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true)
                    ->inline(false),
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
                    ->color(fn (string $state) => match ($state) {
                        'informativa_dati' => 'info',
                        'foto_video'       => 'warning',
                        default            => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'informativa_dati' => 'Informativa dati',
                        'foto_video'       => 'Foto e video',
                        default            => $state,
                    }),

                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Scope')
                    ->default('Default globale')
                    ->badge()
                    ->color(fn ($state) => $state === 'Default globale' ? 'gray' : 'primary'),

                Tables\Columns\TextColumn::make('testo')
                    ->label('Anteprima testo')
                    ->limit(80)
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('pdf_path')
                    ->label('PDF')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-arrow-down')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->label('Attivo'),
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
            ->defaultSort('tipo');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrivacyTemplates::route('/'),
            'create' => Pages\CreatePrivacyTemplate::route('/create'),
            'edit'   => Pages\EditPrivacyTemplate::route('/{record}/edit'),
        ];
    }
}
