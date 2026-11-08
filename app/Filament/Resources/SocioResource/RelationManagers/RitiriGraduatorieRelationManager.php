<?php

namespace App\Filament\Resources\SocioResource\RelationManagers;

use App\Models\Graduatoria;
use App\Models\GraduatoriaCandidato;
use App\Models\StoricoRitiro;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Griglia di sola consultazione: storico ritiri/ripristini di questo socio dalle
 * candidature graduatorie (relazione Socio::ritiriGraduatorie(), hasManyThrough su
 * GraduatoriaCandidato con vincolo esplicito sul tipo polimorfico — vedi il modello).
 */
class RitiriGraduatorieRelationManager extends RelationManager
{
    protected static string $relationship = 'ritiriGraduatorie';

    protected static ?string $title = 'Ritiri/Ripristini';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    /** Subquery scalare (titolo della graduatoria) riusata per ordinare la griglia. */
    private static function subqueryTitolo(): \Illuminate\Database\Query\Builder
    {
        return GraduatoriaCandidato::query()
            ->join('graduatorie', 'graduatorie.id', '=', 'graduatoria_candidati.graduatoria_id')
            ->whereColumn('graduatoria_candidati.id', 'storico_ritiri.ritirabile_id')
            ->select('graduatorie.titolo')
            ->limit(1)
            ->toBase();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['ritirabile.graduatoria', 'eseguitoDa']))
            ->defaultSort(fn ($query) => $query
                ->orderBy(static::subqueryTitolo())
                ->orderByDesc('storico_ritiri.created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'ritiro' ? 'Ritirato' : 'Ripristinato')
                    ->color(fn (string $state) => $state === 'ritiro' ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('corso')
                    ->label('Corso')
                    ->getStateUsing(function (StoricoRitiro $record) {
                        $graduatoria = $record->ritirabile instanceof GraduatoriaCandidato
                            ? $record->ritirabile->graduatoria
                            : null;

                        if (! $graduatoria instanceof Graduatoria) {
                            return '—';
                        }

                        return $graduatoria->titolo;
                    })
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderBy(static::subqueryTitolo(), $direction)),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->wrap(),

                Tables\Columns\TextColumn::make('eseguitoDa.name')
                    ->label('Operatore')
                    ->placeholder('—'),
            ]);
    }
}
