<?php

namespace App\Filament\Resources\CatalogoCorsiResource\RelationManagers;

use App\Models\ComitatoRegionale;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ValiditaRegionaliRelationManager extends RelationManager
{
    protected static string $relationship = 'validitaRegionali';

    protected static ?string $title = 'Override regionali di scadenza';

    protected static ?string $modelLabel = 'Override regionale';

    protected static ?string $pluralModelLabel = 'Override regionali';

    /** Solo superadmin, admin e operatori con permesso vedono questa tab. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->can('qualifiche.validita_regionale');
    }

    /** Scope: admin e operatori vedono solo la propria regione. */
    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                if (! $user?->isSuperadmin()) {
                    $query->where('comitato_regionale_id', $user->comitato_regionale_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Regione')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => auth()->user()?->isSuperadmin()),

                Tables\Columns\TextColumn::make('scadenza_tipo')
                    ->label('Tipo scadenza')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'nessuna'     => 'Nessuna scadenza',
                        'mesi_esatti' => 'A mesi esatti',
                        'anno_solare' => 'Anno solare',
                        default       => '—',
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'nessuna'     => 'gray',
                        'mesi_esatti' => 'warning',
                        'anno_solare' => 'info',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('scadenza_durata')
                    ->label('Durata')
                    ->formatStateUsing(function ($state, Model $record) {
                        if (! $state) return '—';
                        return match ($record->scadenza_tipo) {
                            'mesi_esatti' => "{$state} mesi",
                            'anno_solare' => "{$state} " . ($state === 1 ? 'anno' : 'anni'),
                            default       => '—',
                        };
                    }),

                Tables\Columns\TextColumn::make('note')
                    ->label('Riferimento normativo')
                    ->limit(60)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->color('success')
                    ->visible(fn () => $this->canCreate()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Model $record) => $this->canEditRecord($record)),
                DeleteAction::make()
                    ->visible(fn (Model $record) => $this->canEditRecord($record)),
            ]);
    }

    public function form(Schema $form): Schema
    {
        $user = auth()->user();

        $regioniOptions = $user?->isSuperadmin()
            ? ComitatoRegionale::orderBy('nome')->pluck('nome', 'id')
            : ComitatoRegionale::where('id', $user->comitato_regionale_id)->pluck('nome', 'id');

        return $form->schema([
            Forms\Components\Select::make('comitato_regionale_id')
                ->label('Regione')
                ->options($regioniOptions)
                ->default($user?->isAdmin() || $user?->can('qualifiche.validita_regionale')
                    ? $user->comitato_regionale_id
                    : null)
                ->required()
                ->searchable()
                ->disabled(! $user?->isSuperadmin())
                ->dehydrated(true),

            Forms\Components\Select::make('scadenza_tipo')
                ->label('Tipo di scadenza')
                ->options([
                    'nessuna'     => 'Nessuna (annulla la scadenza nazionale per questa regione)',
                    'mesi_esatti' => 'A mesi esatti dalla data di acquisizione',
                    'anno_solare' => 'Anno solare (rinnovo entro fine anno)',
                ])
                ->searchable()
                ->required()
                ->live()
                ->helperText('Sovrascrive il tipo di scadenza nazionale per questa regione.'),

            Forms\Components\TextInput::make('scadenza_durata')
                ->label(fn (Get $get) => match ($get('scadenza_tipo')) {
                    'anno_solare' => 'Anni di validità',
                    default       => 'Mesi di validità',
                })
                ->numeric()
                ->minValue(1)
                ->required(fn (Get $get) => $get('scadenza_tipo') !== 'nessuna')
                ->visible(fn (Get $get) => $get('scadenza_tipo') && $get('scadenza_tipo') !== 'nessuna')
                ->helperText(fn (Get $get) => match ($get('scadenza_tipo')) {
                    'mesi_esatti' => 'Es. 12 = scade esattamente 12 mesi dopo la data di acquisizione.',
                    'anno_solare' => 'Es. 2 = scade entro il 31/12 del secondo anno solare successivo all\'acquisizione.',
                    default       => null,
                }),

            Forms\Components\Textarea::make('note')
                ->label('Riferimento normativo')
                ->rows(2)
                ->placeholder('Es. LR Toscana n.12/2019 art.5')
                ->helperText('Facoltativo. Indica la fonte normativa regionale che giustifica l\'override.'),
        ]);
    }

    public function canCreate(): bool
    {
        $user = auth()->user();
        if (! ($user?->isSuperadmin() || $user?->isAdmin() || $user?->can('qualifiche.validita_regionale'))) {
            return false;
        }
        if ($user->isSuperadmin()) {
            return true;
        }
        return ! $this->getOwnerRecord()
            ->validitaRegionali()
            ->where('comitato_regionale_id', $user->comitato_regionale_id)
            ->exists();
    }

    private function canEditRecord(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        return ($user?->isAdmin() || $user?->can('qualifiche.validita_regionale'))
            && $record->comitato_regionale_id === $user->comitato_regionale_id;
    }
}
