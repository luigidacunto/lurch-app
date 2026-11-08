<?php

namespace App\Filament\Resources\PrototipoSubNavResource\Pages;

use App\Filament\Resources\PrototipoSubNavResource;
use App\Models\Evento;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Vetrina componenti — pagina di prova con gli elementi UI più usati nel pannello
 * (tabella con azioni raggruppate, card statistiche, pulsanti per funzione, form,
 * notifiche), per avere un unico posto dove vederli tutti insieme. Nascosta dal
 * menu, dati reali in sola lettura, non tocca nulla di esistente.
 */
class VetrinaTab extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = PrototipoSubNavResource::class;

    protected string $view = 'filament.resources.prototipo-sub-nav-resource.pages.vetrina';

    protected static ?string $title = 'Vetrina componenti';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->form->fill([
            'nome_demo'      => 'Corso di primo soccorso — base',
            'comitato_demo'  => 'toscana',
            'data_demo'      => now()->addWeeks(3)->toDateString(),
            'residenziale_demo' => true,
            'note_demo'      => '',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('esporta_demo')
                    ->label('Esporta Excel (demo)')
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->action(fn () => Notification::make()
                        ->title('Demo: qui si aprirebbe il file in una nuova scheda')
                        ->body('Pattern reale: controller HTTP dedicato + ->url(route(...))->openUrlInNewTab().')
                        ->info()
                        ->send()),

                Action::make('nuovo_demo')
                    ->label('Nuovo elemento (con modale)')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('titolo')
                            ->label('Titolo')
                            ->required(),
                    ])
                    ->action(fn (array $data) => Notification::make()
                        ->title("Demo: creato \"{$data['titolo']}\"")
                        ->success()
                        ->send()),
            ])
                ->label('Azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('info')
                ->button(),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Form (componenti comuni)')
                    ->description('Stessi componenti usati in Soci/Eventi/Corsi — TextInput, Select, DatePicker, Toggle, Textarea.')
                    ->schema([
                        Forms\Components\TextInput::make('nome_demo')
                            ->label('Nome')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('comitato_demo')
                            ->label('Comitato regionale')
                            ->options([
                                'toscana'  => 'Toscana',
                                'liguria'  => 'Liguria',
                                'lazio'    => 'Lazio',
                            ])
                            ->searchable(),

                        Forms\Components\DatePicker::make('data_demo')
                            ->label('Data')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\Toggle::make('residenziale_demo')
                            ->label('Residenziale'),

                        Forms\Components\Textarea::make('note_demo')
                            ->label('Note')
                            ->rows(2)
                            ->placeholder('Nessuna')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Data e ora — componenti nativi Filament')
                    ->description('Icona differenziata (calendario vs orologio) via AppServiceProvider::configureUsing() — vale per ogni DatePicker/DateTimePicker del pannello, non solo qui. Clicca l\'icona: apre il picker anche lei, non solo il campo.')
                    ->schema([
                        Forms\Components\DateTimePicker::make('data_ora_demo')
                            ->label('Data e ora (DateTimePicker)')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i'),

                        Forms\Components\DatePicker::make('solo_data_demo')
                            ->label('Solo data (DatePicker)')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TimePicker::make('solo_ora_demo')
                            ->label('Solo ora (TimePicker)')
                            ->native(false)
                            ->displayFormat('H:i'),
                    ])
                    ->columns(3),

                Section::make('Section vs Group — esempio')
                    ->description('Questo blocco stesso è un Section: bordo, titolo, descrizione — una categoria a sé stante, mai un\'altra Section annidata dentro (regola confermata: fa perdere spazio inutilmente). Il contrasto è qui sotto: un Group con gli stessi campi.')
                    ->schema([
                        Group::make([
                            Forms\Components\TextInput::make('group_demo_a')->label('Campo A')->placeholder('dentro un Group'),
                            Forms\Components\TextInput::make('group_demo_b')->label('Campo B')->placeholder('dentro un Group'),
                        ])
                            ->columns(2)
                            ->extraAttributes([
                                'style' => 'outline:2px dashed #94a3b8; outline-offset:6px; border-radius:8px; padding:4px;',
                            ]),
                        Forms\Components\Placeholder::make('group_demo_caption')
                            ->hiddenLabel()
                            ->content('↑ Questo è un Group dentro questo Section: nessun bordo/titolo proprio — il riquadro tratteggiato grigio è aggiunto SOLO per questa demo, per farti vedere dove finisce. Nella UI vera è invisibile: i due campi sembrano semplicemente vicini, senza nessun contenitore percepibile. Usalo per sotto-raggruppare campi dentro un Section, mai un\'altra Section.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Evento::query()->orderByDesc('data_inizio')->limit(5))
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('residenziale')
                    ->label('Residenziale')
                    ->boolean(),

                Tables\Columns\TextColumn::make('data_inizio')
                    ->label('Data inizio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stato_demo')
                    ->label('Stato')
                    ->badge()
                    ->getStateUsing(fn (Evento $record) => Carbon::parse($record->data_inizio)->isPast() ? 'concluso' : 'programmato')
                    ->formatStateUsing(fn (string $state) => $state === 'concluso' ? 'Concluso' : 'Programmato')
                    ->color(fn (string $state) => $state === 'concluso' ? 'gray' : 'success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('residenziale_demo')
                    ->label('Solo residenziali')
                    ->query(fn ($query) => $query->where('residenziale', true)),
            ])
            ->actions([
                Action::make('vedi')
                    ->iconButton()
                    ->tooltip('Vedi')
                    ->icon('heroicon-o-eye')
                    ->color('info'),

                Action::make('modifica')
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->icon('heroicon-o-pencil-square'),

                ActionGroup::make([
                    Action::make('duplica')
                        ->label('Duplica')
                        ->icon('heroicon-o-document-duplicate'),

                    Action::make('elimina')
                        ->label('Elimina')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation(),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns);
    }

    public function salvaDemo(): void
    {
        Notification::make()
            ->title('Demo: dati salvati')
            ->success()
            ->send();
    }

    public function notificaDemo(string $tipo): void
    {
        $notification = Notification::make();

        match ($tipo) {
            'success' => $notification->title('Operazione riuscita')->success(),
            'danger'  => $notification->title('Errore')->body('Qualcosa è andato storto.')->danger(),
            'warning' => $notification->title('Attenzione')->body('Verifica prima di continuare.')->warning(),
            default   => $notification->title('Informazione')->info(),
        };

        $notification->send();
    }
}
