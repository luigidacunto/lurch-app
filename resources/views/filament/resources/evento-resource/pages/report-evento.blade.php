<x-filament-panels::page>
    <div style="display:flex; flex-direction:column; gap:16px;">

    {{-- Card statistiche effettive --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">

        <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:18px 22px;display:flex;flex-direction:column;gap:6px;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#3b82f6;">Discenti formati</div>
            <div style="font-size:2.4rem;font-weight:800;color:#1d4ed8;line-height:1;">{{ $this->statDiscentiFormati }}</div>
            <div style="font-size:.75rem;color:#60a5fa;line-height:1.4;">Iscrizioni ai corsi con almeno un check-in<br><span style="color:#93c5fd;">(stessa persona su più corsi = più figure formate)</span></div>
        </div>

        <div style="background:#f5f3ff;border:1.5px solid #ddd6fe;border-radius:12px;padding:18px 22px;display:flex;flex-direction:column;gap:6px;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#7c3aed;">Docenti formati</div>
            <div style="font-size:2.4rem;font-weight:800;color:#6d28d9;line-height:1;">{{ $this->statDocentiFormati }}</div>
            <div style="font-size:.75rem;color:#a78bfa;line-height:1.4;">Staff con ruolo docenza e almeno un check-in</div>
        </div>

        <div style="background:#f0fdfa;border:1.5px solid #99f6e4;border-radius:12px;padding:18px 22px;display:flex;flex-direction:column;gap:6px;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0e7490;">Logistica in campo</div>
            <div style="font-size:2.4rem;font-weight:800;color:#0e7490;line-height:1;">{{ $this->statLogisticaImpegnata }}</div>
            <div style="font-size:.75rem;color:#5eead4;line-height:1.4;">Staff con ruolo logistica e almeno un check-in</div>
        </div>

        <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:18px 22px;display:flex;flex-direction:column;gap:6px;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#22c55e;">Persone uniche formate</div>
            <div style="font-size:2.4rem;font-weight:800;color:#15803d;line-height:1;">{{ $this->statPersoneUniche }}</div>
            <div style="font-size:.75rem;color:#4ade80;line-height:1.4;">Corsisti distinti con almeno un check-in<br><span style="color:#86efac;">(stessa persona su più corsi conta una sola volta)</span></div>
        </div>

    </div>

    {{-- Sezione pernottanti: export + anteprima --}}
    <x-filament::section>
        <div slot="heading">
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <span style="font-weight:700;font-size:.95rem;color:#111827;">Pernottanti — check-in</span>
                @if($this->pernottatiCount > 0)
                    <span style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:3px 12px;border-radius:20px;font-size:.8rem;font-weight:700;">
                        {{ $this->pernottatiCount }} persone
                    </span>
                    <x-filament::button
                        icon="heroicon-o-arrow-down-tray"
                        color="info"
                        size="sm"
                        tag="a"
                        href="{{ route('eventi.pernottanti-checkin-export', $this->record) }}"
                        target="_blank"
                    >Scarica Excel</x-filament::button>
                @else
                    <span style="color:#9ca3af;font-size:.82rem;">Nessun pernottante con check-in effettuato.</span>
                @endif
            </div>
        </div>

        @if($this->pernottatiCount > 0)
            <details>
                <summary style="cursor:pointer;font-size:.82rem;font-weight:600;color:#6b7280;padding:4px 0;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;">
                    <span style="font-size:.6rem;">▶</span>
                    Anteprima lista ({{ $this->pernottatiCount }} righe)
                    <span style="font-size:.72rem;font-weight:400;color:#9ca3af;margin-left:4px;">· identica al file Excel</span>
                </summary>
                <div style="margin-top:10px;overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                        <thead>
                            <tr style="border-bottom:2px solid #f3f4f6;">
                                <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">#</th>
                                <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Cognome</th>
                                <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nome</th>
                                <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                                <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Data nascita</th>
                                <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Luogo nascita</th>
                                <th style="text-align:center;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">N. pernotti</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->pernottatiLista as $idx => $p)
                                <tr style="border-bottom:1px solid #f9fafb;background:{{ $idx % 2 === 1 ? '#f9fafb' : 'white' }};">
                                    <td style="padding:7px 12px;color:#9ca3af;font-size:.75rem;font-family:monospace;width:36px;">{{ $idx + 1 }}</td>
                                    <td style="padding:7px 12px;font-weight:600;color:#111827;">{{ $p['cognome'] }}</td>
                                    <td style="padding:7px 12px;color:#374151;">{{ $p['nome'] }}</td>
                                    <td style="padding:7px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $p['cf'] ?: '—' }}</td>
                                    <td style="padding:7px 12px;font-size:.78rem;color:#6b7280;">{{ $p['data_nascita'] ?: '—' }}</td>
                                    <td style="padding:7px 12px;font-size:.78rem;color:#374151;">{{ $p['luogo_nascita'] ?: '—' }}</td>
                                    <td style="padding:7px 12px;text-align:center;">
                                        <span style="background:#f0fdf4;color:#15803d;padding:2px 10px;border-radius:20px;font-size:.8rem;font-weight:700;">{{ $p['pernotti'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </x-filament::section>

    {{-- Navigazione data --}}
    <x-filament::section>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">

            @if($this->prevData)
                <x-filament::button
                    icon="heroicon-o-chevron-left"
                    color="gray"
                    size="sm"
                    tag="button"
                    type="button"
                    wire:click="cambiData('{{ $this->prevData }}')"
                >Precedente</x-filament::button>
            @else
                <x-filament::button
                    icon="heroicon-o-chevron-left"
                    color="gray"
                    size="sm"
                    tag="button"
                    type="button"
                    disabled
                >Precedente</x-filament::button>
            @endif

            <div style="text-align:center;">
                <div style="font-size:1rem;font-weight:700;color:#111827;">{{ ucfirst($this->dayLabel) }}</div>
                <div style="font-size:.75rem;color:#9ca3af;font-family:monospace;">{{ $this->data }}</div>
            </div>

            @if($this->nextData)
                <x-filament::button
                    icon="heroicon-o-chevron-right"
                    icon-position="after"
                    color="gray"
                    size="sm"
                    tag="button"
                    type="button"
                    wire:click="cambiData('{{ $this->nextData }}')"
                >Successivo</x-filament::button>
            @else
                <x-filament::button
                    icon="heroicon-o-chevron-right"
                    icon-position="after"
                    color="gray"
                    size="sm"
                    tag="button"
                    type="button"
                    disabled
                >Successivo</x-filament::button>
            @endif

        </div>
    </x-filament::section>

    {{-- Slot pasti / pernotti --}}
    @forelse($this->righe as $riga)

        <x-filament::section>
            <div slot="heading">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div style="background:{{ $riga['coloreBg'] }};border:1px solid {{ $riga['coloreBord'] }};border-radius:8px;padding:6px 14px;font-weight:700;font-size:.9rem;color:{{ $riga['coloreNum'] }};">
                        {{ $riga['label'] }}
                    </div>
                    <div style="display:flex;align-items:baseline;gap:4px;">
                        <span style="font-size:1.5rem;font-weight:800;color:{{ $riga['coloreNum'] }};">{{ $riga['snapshotCount'] }}</span>
                        <span style="font-size:.75rem;color:#6b7280;">confermati da check-in</span>
                    </div>
                </div>
            </div>

            @if($riga['snapshotCount'] > 0)
                <details>
                    <summary style="cursor:pointer;font-size:.82rem;font-weight:600;color:#6b7280;padding:4px 0;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;">
                        <span style="font-size:.6rem;">▶</span>
                        Confermati da check-in ({{ $riga['snapshotCount'] }})
                        <span style="font-size:.72rem;font-weight:400;color:#9ca3af;margin-left:4px;">· tutti gli iscritti all'evento</span>
                    </summary>
                    <div style="margin-top:8px;overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                            <thead>
                                <tr style="border-bottom:2px solid #f3f4f6;">
                                    <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">#</th>
                                    <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nominativo</th>
                                    <th style="text-align:left;padding:5px 12px;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riga['snapshot'] as $idx => $p)
                                    <tr style="border-bottom:1px solid #f9fafb;background:{{ $idx % 2 === 1 ? '#f9fafb' : 'white' }};">
                                        <td style="padding:7px 12px;color:#9ca3af;font-size:.75rem;font-family:monospace;width:36px;">{{ $idx + 1 }}</td>
                                        <td style="padding:7px 12px;font-weight:600;color:#111827;">{{ $p['nome'] }}</td>
                                        <td style="padding:7px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $p['cf'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @else
                <p style="color:#9ca3af;font-size:.82rem;margin:0;">Nessuna conferma da check-in per questo slot.</p>
            @endif

        </x-filament::section>

    @empty
        <x-filament::section>
            <p style="color:#9ca3af;text-align:center;padding:24px 0;font-size:.9rem;">
                Nessun dato check-in per questo giorno.<br>
                <span style="font-size:.8rem;">Controlla che siano stati eseguiti dei check-in per l'evento.</span>
            </p>
        </x-filament::section>
    @endforelse

    </div>
</x-filament-panels::page>
