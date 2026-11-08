<?php

namespace App\Filament\Resources\EsameResource\Concerns;

use App\Esami\RiepilogoProvaFinale;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;

/**
 * Parte comune alle pagine Colloquio e Prova pratica (schede personali dei commissari + schede finali del direttore):
 * accesso in lettura (Esame::puoVedereProvaFinale), dati da RiepilogoProvaFinale, motivo per cui i pulsanti finali sono spenti.
 * Le due pagine usano la stessa vista (`prova-finale`), che mostra la sezione indicata da sezione().
 */
trait LeggeProvaFinale
{
    public static function canAccess(array $parameters = []): bool
    {
        // Filament sonda canAccess() senza argomenti all'avvio: no-op, il controllo vero ha il record.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }
        $record = $parameters['record'];

        return $record instanceof Esame && $record->puoVedereProvaFinale(auth()->user());
    }

    public function getSubNavigation(): array
    {
        return EsameResource::getRecordSubNavigation($this);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(['record' => $this->record]), 403);
    }

    public function riepilogo(): array
    {
        return RiepilogoProvaFinale::per($this->record);
    }

    public function puoInserire(): bool
    {
        return $this->record->puoInserireFinali(auth()->user());
    }

    /** Motivo per cui i pulsanti delle finali sono spenti, null se accesi (tooltip). */
    public function motivoNonInseribile(): ?string
    {
        if (! $this->record->isDirettore(auth()->user())) {
            return 'Solo il direttore del corso inserisce le schede finali.';
        }
        if ($this->record->isChiuso()) {
            return 'L\'esame è chiuso: sola lettura.';
        }
        if ($this->record->stato !== Esame::STATO_IN_CORSO) {
            return 'L\'esame non è in corso.';
        }

        return null;
    }
}
