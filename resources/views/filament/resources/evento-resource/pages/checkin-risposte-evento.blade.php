<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:16px;">

    {{-- Filtri --}}
    <x-filament::section>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            {{-- Search --}}
            <div style="flex:1;min-width:200px;position:relative;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input wire:model.live="search" type="text" placeholder="Cerca per nome o CF…"
                    style="width:100%;padding:8px 10px 8px 32px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.88rem;box-sizing:border-box;">
            </div>
            {{-- Filtro tipo --}}
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                @foreach(['' => 'Tutti', 'corsista' => 'Corsisti', 'staff' => 'Staff', 'corsista+staff' => 'Cors+Staff', 'ospite' => 'Ospiti'] as $val => $label)
                    <button wire:click="$set('filtroTipo', '{{ $val }}')"
                        style="padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid {{ $this->filtroTipo === $val ? '#2563eb' : '#e5e7eb' }};background:{{ $this->filtroTipo === $val ? '#2563eb' : 'white' }};color:{{ $this->filtroTipo === $val ? 'white' : '#374151' }};">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-filament::section>

    {{-- Tabella --}}
    @php
        $q    = mb_strtolower(trim($this->search));
        $tipo = $this->filtroTipo;
        $filtrati = array_values(array_filter($this->lista, function ($p) use ($q, $tipo) {
            $matchSearch = !$q || str_contains(mb_strtolower($p['nome']), $q)
                              || str_contains(mb_strtolower($p['cf']), $q);
            $matchTipo   = !$tipo || $p['tipo'] === $tipo;
            return $matchSearch && $matchTipo;
        }));
        $totale = count($filtrati);
    @endphp

    <x-filament::section>
        <div slot="heading">
            <span style="font-weight:700;font-size:.95rem;">Partecipanti con check-in effettuato</span>
            <span style="margin-left:10px;font-size:.82rem;color:#6b7280;">{{ $totale }} {{ $totale === 1 ? 'persona' : 'persone' }}</span>
        </div>

        @if($totale > 0)
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #f3f4f6;">
                            <th style="padding:7px 12px;width:1%;"></th>
                            <th style="text-align:left;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">#</th>
                            <th style="text-align:left;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nominativo</th>
                            <th style="text-align:left;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                            <th style="text-align:center;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Tipo</th>
                            <th style="text-align:left;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Check-in</th>
                            <th style="text-align:center;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Pasti</th>
                            <th style="text-align:center;padding:7px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Pernotti</th>
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
                        @foreach($filtrati as $idx => $p)
                            @php
                                // corsista+staff mostra i due badge singoli affiancati invece di un 4° colore dedicato
                                $tipiRiga = $p['tipo'] === 'corsista+staff' ? ['corsista', 'staff'] : [$p['tipo']];
                            @endphp
                            <tr style="border-bottom:1px solid #f9fafb;background:{{ $idx % 2 === 1 ? '#f9fafb' : 'white' }};">
                                <td style="padding:8px 12px;white-space:nowrap;">
                                    <x-filament::icon-button
                                        icon="heroicon-o-pencil-square"
                                        color="info"
                                        size="sm"
                                        label="Modifica"
                                        tooltip="Modifica"
                                        wire:click="mountAction('modificaCheckin', {id: {{ $p['isc_id'] }}, tipoRecord: '{{ $p['tipo_record'] }}', nome: '{{ addslashes($p['nome']) }}'})"
                                    />
                                </td>
                                <td style="padding:8px 12px;color:#9ca3af;font-size:.75rem;font-family:monospace;width:36px;">{{ $idx + 1 }}</td>
                                <td style="padding:8px 12px;font-weight:600;color:#111827;">{{ $p['nome'] }}</td>
                                <td style="padding:8px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $p['cf'] ?: '—' }}</td>
                                <td style="padding:8px 12px;text-align:center;">
                                    @foreach($tipiRiga as $t)
                                        <span style="padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700;background:{{ $tipoBadge[$t][0] }};color:{{ $tipoBadge[$t][1] }};margin:0 2px;">{{ $tipoBadge[$t][2] }}</span>
                                    @endforeach
                                </td>
                                <td style="padding:8px 12px;font-size:.78rem;color:#6b7280;">
                                    {{ $p['checkin_at'] ? \Carbon\Carbon::parse($p['checkin_at'])->format('d/m H:i') : '—' }}
                                </td>
                                <td style="padding:8px 12px;text-align:center;">
                                    @if($p['n_pasti'] > 0)
                                        <span style="background:#eff6ff;color:#1d4ed8;padding:2px 9px;border-radius:20px;font-size:.78rem;font-weight:700;">{{ $p['n_pasti'] }}</span>
                                    @else
                                        <span style="color:#d1d5db;font-size:.78rem;">—</span>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;text-align:center;">
                                    @if($p['n_pernotti'] > 0)
                                        <span style="background:#f0fdf4;color:#15803d;padding:2px 9px;border-radius:20px;font-size:.78rem;font-weight:700;">{{ $p['n_pernotti'] }}</span>
                                    @else
                                        <span style="color:#d1d5db;font-size:.78rem;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="color:#9ca3af;text-align:center;padding:24px 0;font-size:.9rem;">
                Nessun partecipante trovato con i filtri selezionati.
            </p>
        @endif
    </x-filament::section>

    </div>

</x-filament-panels::page>
