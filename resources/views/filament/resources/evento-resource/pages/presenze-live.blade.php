<x-filament-panels::page>

    <div {{ $this->pollAttivo() ? 'wire:poll.30s=carica' : '' }} style="display:flex; flex-direction:column; gap:20px;">

    {{-- Contatori --}}
    <x-filament::section>
        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center;">
            <div style="font-size:1.5rem; font-weight:800; color:#111827;">
                {{ count($this->dentro) }}
                <span style="font-size:1rem; font-weight:600; color:#6b7280; margin-left:4px;">in campo</span>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if($this->totCorsisti)
                    <span style="background:#eef2ff;color:#4f46e5;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:600;">
                        {{ $this->totCorsisti }} corsist{{ $this->totCorsisti === 1 ? 'a' : 'i' }}
                    </span>
                @endif
                @if($this->totStaff)
                    <span style="background:#ecfeff;color:#0e7490;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:600;">
                        {{ $this->totStaff }} staff
                    </span>
                @endif
                @if($this->totOspiti)
                    <span style="background:#fdf4ff;color:#a21caf;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:600;">
                        {{ $this->totOspiti }} ospit{{ $this->totOspiti === 1 ? 'e' : 'i' }}
                    </span>
                @endif
                @if(count($this->dentro) === 0)
                    <span style="color:#9ca3af;font-size:.88rem;">Nessuno attualmente in campo</span>
                @endif
            </div>
            <span style="margin-left:auto;font-size:.75rem;color:#9ca3af;">
                {{ $this->pollAttivo() ? 'aggiorn. ogni 30s' : 'aggiornamento automatico fermo — non è uno dei giorni dell\'evento' }}
            </span>
        </div>
    </x-filament::section>

    {{-- Chi è dentro --}}
    @if(count($this->dentro) > 0)
        @php
            $q          = mb_strtolower($this->searchDentro);
            $filtroTipo = $this->filtroDentroTipo;

            $dentroFiltrati = array_values(array_filter($this->dentro, function ($p) use ($q, $filtroTipo) {
                $matchSearch = !$q || str_contains(mb_strtolower($p['nome']), $q) || str_contains(mb_strtolower($p['cf'] ?? ''), $q);
                $matchTipo   = !$filtroTipo || str_contains($p['tipo'], $filtroTipo);
                return $matchSearch && $matchTipo;
            }));

            $dentroTotFiltrati = count($dentroFiltrati);
            $dentroTotPagine   = max(1, (int) ceil($dentroTotFiltrati / $this->dentroPerPage));
            $dentroPage        = min($this->dentroPage, $dentroTotPagine);
            $dentroSlice       = array_slice($dentroFiltrati, ($dentroPage - 1) * $this->dentroPerPage, $this->dentroPerPage);
        @endphp
        <x-filament::section>
            <div slot="heading" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;width:100%;">
                <span style="font-weight:700;font-size:1rem;">Attualmente in campo</span>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    {{-- Filtri tipo --}}
                    <div style="display:flex;gap:4px;">
                        @foreach(['' => 'Tutti', 'corsista' => 'Corsisti', 'staff' => 'Staff', 'ospite' => 'Ospiti'] as $val => $label)
                            <button wire:click="$set('filtroDentroTipo', '{{ $val }}')"
                                style="padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;cursor:pointer;
                                       background:{{ $this->filtroDentroTipo === $val ? '#2563eb' : '#f1f5f9' }};
                                       color:{{ $this->filtroDentroTipo === $val ? '#fff' : '#6b7280' }};
                                       border:1px solid {{ $this->filtroDentroTipo === $val ? '#2563eb' : '#e2e8f0' }};">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    {{-- Per pagina --}}
                    <select wire:model.live="dentroPerPage"
                        style="padding:4px 8px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.82rem;color:#374151;cursor:pointer;">
                        <option value="10">10 per pagina</option>
                        <option value="25">25 per pagina</option>
                        <option value="50">50 per pagina</option>
                    </select>
                    {{-- Contatore filtrato --}}
                    @if($q || $filtroTipo)
                        <span style="font-size:.78rem;color:#6b7280;">{{ $dentroTotFiltrati }} di {{ count($this->dentro) }}</span>
                    @endif
                    {{-- Ricerca --}}
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchDentro"
                        placeholder="Cerca nome o CF…"
                        style="padding:6px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;width:180px;outline:none;">
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #f3f4f6;">
                            <th style="padding:6px 12px;width:1%;"></th>
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nome</th>
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Tipo</th>
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Entrato alle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $tipoBadge = [
                                'corsista' => ['#eef2ff', '#4f46e5', 'Corsista'],
                                'staff'    => ['#ecfeff', '#0e7490', 'Staff'],
                                'ospite'   => ['#fdf4ff', '#a21caf', 'Ospite'],
                            ];
                        @endphp
                        @forelse($dentroSlice as $p)
                            @php
                                // corsista+staff mostra i due badge singoli affiancati invece di un 4° colore dedicato
                                $tipiRiga = $p['tipo'] === 'corsista+staff' ? ['corsista', 'staff'] : [$p['tipo']];
                            @endphp
                            <tr style="border-bottom:1px solid #f9fafb;">
                                <td style="padding:10px 12px;white-space:nowrap;">
                                    <div style="display:flex;gap:4px;align-items:center;">
                                        @if(str_contains($p['tipo_isc_class'], 'SocioEvento'))
                                            <x-filament::icon-button
                                                icon="heroicon-o-pencil-square"
                                                color="info"
                                                size="sm"
                                                label="Modifica dati"
                                                tooltip="Modifica dati"
                                                wire:click="mountAction('modificaCheckin', {seId: {{ $p['isc_id'] }}, nome: '{{ addslashes($p['nome']) }}'})"
                                            />
                                        @endif
                                        <x-filament::icon-button
                                            icon="heroicon-o-arrow-right-on-rectangle"
                                            color="success"
                                            size="sm"
                                            label="Registra uscita"
                                            tooltip="Registra uscita"
                                            wire:click="mountAction('registraUscita', {tipoClass: '{{ addslashes($p['tipo_isc_class']) }}', iscId: {{ $p['isc_id'] }}, nome: '{{ addslashes($p['nome']) }}'})"
                                        />
                                    </div>
                                </td>
                                <td style="padding:10px 12px;font-weight:600;color:#111827;">
                                    {{ $p['nome'] }}
                                    @if($p['cf'])
                                        <div style="font-size:.75rem;color:#9ca3af;font-family:monospace;">{{ $p['cf'] }}</div>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;">
                                    @foreach($tipiRiga as $t)
                                        <span style="background:{{ $tipoBadge[$t][0] }};color:{{ $tipoBadge[$t][1] }};padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600;margin-right:4px;">
                                            {{ $tipoBadge[$t][2] }}
                                        </span>
                                    @endforeach
                                </td>
                                <td style="padding:10px 12px;color:#374151;">
                                    {{ $p['entrato_alle']->format('H:i') }}
                                    <span style="color:#9ca3af;font-size:.78rem;margin-left:4px;">
                                        {{ $p['entrato_alle']->isToday() ? '' : $p['entrato_alle']->format('d/m') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="padding:20px 12px;text-align:center;color:#9ca3af;font-size:.88rem;">
                                    Nessun risultato per i filtri applicati
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginatore dentro --}}
            @if($dentroTotPagine > 1)
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:8px;">
                    <div style="font-size:.82rem;color:#6b7280;">
                        Pagina {{ $dentroPage }} di {{ $dentroTotPagine }}
                        &nbsp;·&nbsp;
                        righe {{ ($dentroPage - 1) * $this->dentroPerPage + 1 }}–{{ min($dentroPage * $this->dentroPerPage, $dentroTotFiltrati) }}
                    </div>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        @if($dentroPage > 1)
                            <button wire:click="cambiaPaginaDentro(1)"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">«</button>
                            <button wire:click="cambiaPaginaDentro({{ $dentroPage - 1 }})"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">‹ Prec</button>
                        @endif
                        @foreach(range(max(1, $dentroPage - 2), min($dentroTotPagine, $dentroPage + 2)) as $pg)
                            <button wire:click="cambiaPaginaDentro({{ $pg }})"
                                style="padding:4px 10px;border-radius:6px;font-size:.82rem;cursor:pointer;
                                       font-weight:{{ $pg === $dentroPage ? '700' : '400' }};
                                       background:{{ $pg === $dentroPage ? '#2563eb' : '#fff' }};
                                       color:{{ $pg === $dentroPage ? '#fff' : '#374151' }};
                                       border:1px solid {{ $pg === $dentroPage ? '#2563eb' : '#d1d5db' }};">
                                {{ $pg }}
                            </button>
                        @endforeach
                        @if($dentroPage < $dentroTotPagine)
                            <button wire:click="cambiaPaginaDentro({{ $dentroPage + 1 }})"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">Succ ›</button>
                            <button wire:click="cambiaPaginaDentro({{ $dentroTotPagine }})"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">»</button>
                        @endif
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- Storico di oggi --}}
    @if($this->storicoTotale > 0)
        @php
            $totPagine   = (int) ceil($this->storicoTotale / $this->storicoPerPage);
            $pagCorrente = $this->storicoPage;
        @endphp
        <x-filament::section>
            <div slot="heading" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;width:100%;">
                <span style="font-weight:700;font-size:1rem;">
                    Storico accessi
                    <span style="font-size:.8rem;font-weight:400;color:#6b7280;margin-left:6px;">{{ $this->storicoTotale }} totali</span>
                </span>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="date" wire:model.live="storicoDataFiltro"
                        style="padding:4px 8px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.82rem;color:#374151;cursor:pointer;">
                    @if($this->storicoDataFiltro)
                        <button wire:click="$set('storicoDataFiltro', '')"
                            style="padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;font-size:.78rem;color:#6b7280;cursor:pointer;">
                            × Tutte le date
                        </button>
                    @endif
                    <input type="text" wire:model.live.debounce.300ms="storicoSearchNome"
                        placeholder="Cerca nome o CF…"
                        style="padding:6px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;width:170px;outline:none;">
                    <select wire:model.live="storicoPerPage"
                        style="padding:4px 8px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.82rem;color:#374151;cursor:pointer;">
                        <option value="25">25 per pagina</option>
                        <option value="50">50 per pagina</option>
                        <option value="100">100 per pagina</option>
                    </select>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #f3f4f6;">
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">{{ $this->storicoDataFiltro ? 'Orario' : 'Data / Ora' }}</th>
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nome</th>
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Tipo accesso</th>
                            <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Operatore</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->storicoOggi as $acc)
                            <tr style="border-bottom:1px solid #f9fafb;">
                                <td style="padding:8px 12px;font-family:monospace;color:#374151;font-size:.88rem;">{{ $acc['orario'] }}</td>
                                <td style="padding:8px 12px;color:#111827;font-weight:500;">
                                    {{ $acc['nome'] }}
                                    @if($acc['cf'])
                                        <div style="font-size:.72rem;color:#9ca3af;font-family:monospace;">{{ $acc['cf'] }}</div>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;">
                                    @if($acc['tipo'] === 'entrata')
                                        <span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600;">ENTRATA</span>
                                    @else
                                        <span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600;">USCITA</span>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;color:#6b7280;font-size:.82rem;">{{ $acc['operatore'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Paginatore --}}
            @if($totPagine > 1)
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:8px;">
                    <div style="font-size:.82rem;color:#6b7280;">
                        Pagina {{ $pagCorrente }} di {{ $totPagine }}
                        &nbsp;·&nbsp;
                        righe {{ ($pagCorrente - 1) * $this->storicoPerPage + 1 }}–{{ min($pagCorrente * $this->storicoPerPage, $this->storicoTotale) }}
                    </div>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        {{-- Prima --}}
                        @if($pagCorrente > 1)
                            <button wire:click="cambiaPaginaStorico(1)"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">
                                «
                            </button>
                            <button wire:click="cambiaPaginaStorico({{ $pagCorrente - 1 }})"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">
                                ‹ Prec
                            </button>
                        @endif

                        {{-- Pagine vicine --}}
                        @foreach(range(max(1, $pagCorrente - 2), min($totPagine, $pagCorrente + 2)) as $pg)
                            <button wire:click="cambiaPaginaStorico({{ $pg }})"
                                style="padding:4px 10px;border-radius:6px;font-size:.82rem;cursor:pointer;font-weight:{{ $pg === $pagCorrente ? '700' : '400' }};
                                       background:{{ $pg === $pagCorrente ? '#2563eb' : '#fff' }};
                                       color:{{ $pg === $pagCorrente ? '#fff' : '#374151' }};
                                       border:1px solid {{ $pg === $pagCorrente ? '#2563eb' : '#d1d5db' }};">
                                {{ $pg }}
                            </button>
                        @endforeach

                        {{-- Ultima --}}
                        @if($pagCorrente < $totPagine)
                            <button wire:click="cambiaPaginaStorico({{ $pagCorrente + 1 }})"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">
                                Succ ›
                            </button>
                            <button wire:click="cambiaPaginaStorico({{ $totPagine }})"
                                style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:.82rem;cursor:pointer;color:#374151;">
                                »
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    @if(count($this->dentro) === 0 && $this->storicoTotale === 0)
        <x-filament::section>
            <p style="color:#9ca3af;text-align:center;padding:24px 0;font-size:.9rem;">
                Nessun accesso registrato per oggi.
            </p>
        </x-filament::section>
    @endif

    </div>

</x-filament-panels::page>
