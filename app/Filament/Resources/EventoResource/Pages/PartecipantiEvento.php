<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Models\Evento;
use App\Models\OspiteEvento;
use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;

class PartecipantiEvento extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.partecipanti-evento';

    #[Locked]
    public Evento $record;

    public static function canAccess(array $parameters = []): bool
    {
        // Filament's own mountCanAuthorizeAccess() hook calls canAccess() with
        // zero arguments on every mount (Filament\Pages\Concerns\CanAuthorizeAccess) —
        // that call must stay a no-op (true), the real per-record check only runs
        // explicitly via abort_unless() in mount() below, which always passes 'record'.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }

        $record = $parameters['record'];

        return $record instanceof Evento && EventoResource::canEdit($record);
    }

    public string $search = '';

    public array $partecipanti = [];

    public function mount(Evento $record): void
    {
        abort_unless(static::canAccess(['record' => $record]), 403);

        $this->record = $record->load([
            'corsi.iscrizioni.partecipante.comitatoRegionale',
            'corsi.iscrizioni.partecipante.comitatoLocale',
            'corsi.iscrizioni.partecipante.sottosedeAppartenenza',
            'staff.partecipante.comitatoRegionale',
            'staff.partecipante.comitatoLocale',
            'staff.partecipante.sottosedeAppartenenza',
            'ospiti',
        ]);

        $this->costruisciPartecipanti();
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    private function costruisciPartecipanti(): void
    {
        $mappa = [];

        foreach ($this->record->corsi as $corso) {
            foreach ($corso->iscrizioni as $iscr) {
                $pid = $iscr->partecipante_id;
                $p   = $iscr->partecipante;
                if (! $p) continue;

                if (! isset($mappa[$pid])) {
                    $mappa[$pid] = $this->baseEntry($p);
                }
                $mappa[$pid]['tipo_iscritto'] = true;
                $codice = $corso->numero_protocollo ?: $corso->nome;
                if ($codice && ! in_array($codice, $mappa[$pid]['corsi_list'], true)) {
                    $mappa[$pid]['corsi_list'][] = $codice;
                }
            }
        }

        foreach ($this->record->staff as $iscr) {
            $pid = $iscr->partecipante_id;
            $p   = $iscr->partecipante;
            if (! $p) continue;

            if (! isset($mappa[$pid])) {
                $mappa[$pid] = $this->baseEntry($p);
            }
            $mappa[$pid]['tipo_staff'] = true;
        }

        foreach ($this->record->ospiti as $ospite) {
            $key = 'ospite_' . $ospite->id;
            $mappa[$key] = [
                'cognome'            => $ospite->cognome ?? '',
                'nome'               => $ospite->nome ?? '',
                'codice_fiscale'     => $ospite->cf ?? '',
                'comitato_regionale' => '—',
                'comitato_display'   => '—',
                'comitato_search'    => '',
                'tipo_iscritto'      => false,
                'tipo_staff'         => false,
                'tipo_ospite'        => true,
                'corsi_list'         => [],
            ];
        }

        foreach ($mappa as &$entry) {
            $entry['tipi'] = array_values(array_filter([
                ($entry['tipo_iscritto'] ?? false) ? 'iscritto' : null,
                ($entry['tipo_staff'] ?? false) ? 'staff' : null,
                ($entry['tipo_ospite'] ?? false) ? 'ospite' : null,
            ]));
            $entry['corsi'] = implode(' · ', $entry['corsi_list']);
            unset($entry['tipo_iscritto'], $entry['tipo_staff'], $entry['tipo_ospite'], $entry['corsi_list']);
        }
        unset($entry);

        uasort($mappa, fn ($a, $b) => strcmp($a['cognome'] . ' ' . $a['nome'], $b['cognome'] . ' ' . $b['nome']));

        $this->partecipanti = array_values($mappa);
    }

    private function baseEntry(Socio $p): array
    {
        return [
            'cognome'            => $p->cognome ?? '',
            'nome'               => $p->nome ?? '',
            'codice_fiscale'     => $p->codice_fiscale ?? '',
            'comitato_regionale' => $p->comitatoRegionale?->nome ?? '—',
            'comitato_display'   => $p->comitato_display,
            'comitato_search'    => mb_strtolower(
                ($p->comitatoRegionale?->nome ?? '') . ' ' .
                ($p->comitatoLocale?->nome ?? '') . ' ' .
                ($p->sottosedeAppartenenza?->nome ?? '')
            ),
            'tipo_iscritto'      => false,
            'tipo_staff'         => false,
            'corsi_list'         => [],
        ];
    }

    public function getTitle(): string
    {
        return 'Partecipanti — ' . $this->record->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                         => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->record])         => $this->record->nome,
            ''                                                                      => 'Partecipanti evento',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Esporta Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn () => route('eventi.partecipanti-export', ['evento' => $this->record])
                    . ($this->search !== '' ? '?search=' . urlencode($this->search) : ''))
                ->openUrlInNewTab(),
        ];
    }
}
