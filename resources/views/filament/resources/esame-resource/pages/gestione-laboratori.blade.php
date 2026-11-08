<x-filament-panels::page>
    @php
        $puo = $this->puoConfigurare();
        $senzaDocenti = $this->gruppiSenzaDocenti();
        $bordo = 'rgba(128,128,128,.35)';
        $attivi = $this->idDiscentiAttivi();
    @endphp

    @if($senzaDocenti)
        <x-filament::section>
            <p style="margin:0; color:#b45309;">⚠ {{ $senzaDocenti }} gruppo/i senza docenti: i loro discenti non avranno schede da compilare.</p>
        </x-filament::section>
    @endif

    @foreach($this->laboratori() as $lab)
        @php $motivo = \App\Filament\Resources\EsameResource\Pages\GestioneLaboratori::motivoNonEliminabile($lab); @endphp
        <x-filament::section>
            <x-slot name="heading">Laboratorio {{ $lab->numero }}{{ $lab->data ? ' · ' . $lab->data->format('d/m/Y') : '' }}</x-slot>
            @if($puo)
                <x-slot name="afterHeader">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <x-filament::button size="sm" color="info" type="button" wire:click="mountAction('modificaLaboratorio', { laboratorio_id: {{ $lab->id }} })">Modifica data</x-filament::button>
                        <x-filament::button size="sm" color="info" type="button" wire:click="mountAction('nuovoGruppo', { laboratorio_id: {{ $lab->id }} })">Nuovo gruppo</x-filament::button>
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button color="danger" size="sm" icon="heroicon-o-ellipsis-vertical" tag="button" type="button" label="Altre azioni" />
                            </x-slot>
                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item icon="heroicon-o-document-duplicate" color="info" tag="button" type="button"
                                    wire:click="mountAction('copiaGruppi', { laboratorio_id: {{ $lab->id }} })">Copia i gruppi da un altro laboratorio</x-filament::dropdown.list.item>
                                <x-filament::dropdown.list.item icon="heroicon-o-trash" color="danger" tag="button" type="button"
                                    :disabled="$motivo !== null" :tooltip="$motivo"
                                    wire:click="mountAction('eliminaLaboratorio', { laboratorio_id: {{ $lab->id }} })">Elimina laboratorio</x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    </div>
                </x-slot>
            @endif

            @forelse($lab->gruppi as $g)
                <div x-data="{ aperto: false }" style="{{ ! $loop->first ? 'border-top:1px solid ' . $bordo . ';' : '' }}">
                    <div style="display:flex; flex-wrap:wrap; gap:6px 16px; align-items:center; justify-content:space-between; padding:8px 0;">
                        {{-- Riassunto cliccabile: apre l'elenco dei discenti --}}
                        <div role="button" tabindex="0" x-on:click="aperto = ! aperto" x-on:keydown.enter="aperto = ! aperto" x-bind:aria-expanded="aperto"
                             style="flex:1 1 520px; display:flex; flex-wrap:wrap; gap:6px 16px; align-items:center; cursor:pointer;">
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" x-bind:style="aperto ? 'transform:rotate(90deg)' : ''" style="transition:transform .15s;" />
                            <div style="flex:1 1 200px; font-weight:600; overflow-wrap:anywhere;">{{ $g->nome }}</div>
                            <div style="flex:0 0 90px; font-size:14px;">{{ $g->discenti->count() }} discenti</div>
                            <div style="flex:2 1 240px; font-size:14px; overflow-wrap:anywhere; {{ $g->docenti->isEmpty() ? 'color:#b45309;' : '' }}">
                                {{ $g->docenti->isEmpty() ? '⚠ nessun docente' : $g->docenti->map(fn ($s) => $s->cognome . ' ' . $s->nome)->implode(', ') }}
                            </div>
                        </div>
                        @if($puo)
                            <div style="display:flex; gap:6px; align-items:center;">
                                <x-filament::icon-button color="info" size="sm" icon="heroicon-o-pencil-square" tag="button" type="button" label="Modifica" tooltip="Modifica"
                                    wire:click="mountAction('modificaGruppo', { gruppo_id: {{ $g->id }} })" />
                                <x-filament::dropdown placement="bottom-end">
                                    <x-slot name="trigger">
                                        <x-filament::icon-button color="danger" size="sm" icon="heroicon-o-ellipsis-vertical" tag="button" type="button" label="Altre azioni" />
                                    </x-slot>
                                    <x-filament::dropdown.list>
                                        <x-filament::dropdown.list.item icon="heroicon-o-trash" color="danger" tag="button" type="button"
                                            wire:click="mountAction('eliminaGruppo', { gruppo_id: {{ $g->id }} })">Elimina gruppo</x-filament::dropdown.list.item>
                                    </x-filament::dropdown.list>
                                </x-filament::dropdown>
                            </div>
                        @endif
                    </div>
                    <div x-show="aperto" x-cloak style="padding:0 0 10px 28px; font-size:14px;">
                        @forelse($g->discenti->sortBy(fn ($s) => $s->cognome . ' ' . $s->nome) as $s)
                            <div style="padding:2px 0; overflow-wrap:anywhere;">
                                {{ $s->cognome }} {{ $s->nome }}
                                @unless(in_array($s->id, $attivi, true)) <span style="color:#6b7280;">(ritirato)</span> @endunless
                            </div>
                        @empty
                            <div style="color:#6b7280;">Nessun discente in questo gruppo.</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <p style="margin:0; font-size:14px;">Nessun gruppo ancora.</p>
            @endforelse
        </x-filament::section>
    @endforeach

    @if($puo)
        <div>
            <x-filament::button color="info" type="button" wire:click="mountAction('creaLaboratorio')">Aggiungi laboratorio</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
