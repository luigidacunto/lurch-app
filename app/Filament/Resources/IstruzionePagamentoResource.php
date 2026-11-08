<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IstruzionePagamentoResource\Pages;
use App\Models\ComitatoRegionale;
use App\Models\IstruzionePagamento;
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

class IstruzionePagamentoResource extends Resource
{
    protected static ?string $model = IstruzionePagamento::class;

    protected static ?string $slug = 'istruzioni-pagamento';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Impostazioni';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Istruzioni pagamento';

    protected static ?string $pluralModelLabel = 'Istruzioni pagamento';

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

        return $form->schema([
            Section::make()->schema([
                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato regionale')
                    ->options($regioniOptions)
                    ->default($user?->isAdmin() ? $user->comitato_regionale_id : null)
                    ->placeholder('— Default globale —')
                    ->helperText('Lascia vuoto per il default globale, valorizza per un testo specifico della regione.')
                    ->nullable()
                    ->searchable()
                    ->disabled($user?->isAdmin()),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true)
                    ->inline(false),

                Forms\Components\RichEditor::make('testo')
                    ->label('Istruzioni di pagamento')
                    ->required()
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold', 'italic', 'underline',
                        'bulletList', 'orderedList',
                        'h3',
                        'link',
                        'undo', 'redo',
                    ])
                    ->helperText('Inserisci beneficiario, IBAN, causale e importo. Questo testo viene iniettato automaticamente nell\'email quando il corso prevede pagamento (tramite il segnaposto {sezione_pagamento} nel template email). Puoi usare segnaposto anche qui: {protocollo} → numero protocollo del corso · {cognome} {nome} → dati dell\'iscritto · {quota} → importo · {comitato_regionale} → nome comitato.'),

                Forms\Components\Placeholder::make('esempio')
                    ->label('Esempio causale')
                    ->content('Causale: Corso {protocollo} — {cognome} {nome}')
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
                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Scope')
                    ->default('Default globale')
                    ->badge()
                    ->color(fn ($state) => $state === 'Default globale' ? 'gray' : 'primary'),

                Tables\Columns\TextColumn::make('testo')
                    ->label('Anteprima')
                    ->limit(100)
                    ->html()
                    ->placeholder('—'),

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
            'index'  => Pages\ListIstruzioniPagamento::route('/'),
            'create' => Pages\CreateIstruzionePagamento::route('/create'),
            'edit'   => Pages\EditIstruzionePagamento::route('/{record}/edit'),
        ];
    }
}
