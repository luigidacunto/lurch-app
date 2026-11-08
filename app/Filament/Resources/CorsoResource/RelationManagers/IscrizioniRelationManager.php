<?php

namespace App\Filament\Resources\CorsoResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class IscrizioniRelationManager extends RelationManager
{
    protected static string $relationship = 'iscrizioni';

    protected static ?string $title = 'Iscritti al corso';

    protected static ?string $modelLabel = 'Iscrizione';

    protected static ?string $pluralModelLabel = 'Iscrizioni';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('corsi.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('partecipante.cognome')
                    ->label('Cognome')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('partecipante.nome')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('partecipante.codice_fiscale')
                    ->label('Codice fiscale')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('partecipante.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('partecipante.telefono')
                    ->label('Telefono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stato')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'          => 'gray',
                        'form_completato'  => 'warning',
                        'checkin_fatto'    => 'success',
                        'ritirato'         => 'danger',
                        default            => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending'          => 'In attesa',
                        'form_completato'  => 'Form compilato',
                        'checkin_fatto'    => 'Check-in fatto',
                        'ritirato'         => 'Ritirato',
                        default            => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Iscritto il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('partecipante.cognome')
            ->actions([
                Action::make('apri_form')
                    ->label('Apri form')
                    ->tooltip('Apri form')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->iconButton()
                    ->url(fn (Model $record) => route('form.show', $record->token))
                    ->openUrlInNewTab(),

                // Copia link: nessuna azione server-side reale, il click scrive negli
                // appunti via onclick nativo (eseguito insieme al wire:click di Filament);
                // ->action() serve solo per il toast di conferma.
                Action::make('copia_link')
                    ->label('Copia link')
                    ->tooltip('Copia link al form')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->iconButton()
                    ->extraAttributes(fn (Model $record) => [
                        'onclick' => "navigator.clipboard.writeText('" . route('form.show', $record->token) . "')",
                    ])
                    ->action(function () {
                        Notification::make()->title('Link copiato')->success()->send();
                    }),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([]);
    }
}
