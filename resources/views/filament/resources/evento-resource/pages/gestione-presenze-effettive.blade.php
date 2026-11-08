<x-filament-panels::page>
    <div style="display:flex; flex-direction:column; gap:16px;">

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

    {{-- Contatori sommario --}}
    <x-filament::section>
        <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:center;">
            <div>
                <div style="font-size:1.6rem;font-weight:800;color:#111827;line-height:1;">{{ $this->totPresenti }}</div>
                <div style="font-size:.75rem;color:#6b7280;margin-top:3px;font-weight:500;">Presenti (check-in)</div>
                <div style="font-size:.7rem;color:#9ca3af;">almeno un'entrata registrata</div>
            </div>
            @if($this->totPrevisti > 0)
                <div style="width:1px;height:40px;background:#f3f4f6;"></div>
                <div>
                    <div style="font-size:1.6rem;font-weight:800;color:#6b7280;line-height:1;">{{ $this->totPrevisti }}</div>
                    <div style="font-size:.75rem;color:#6b7280;margin-top:3px;font-weight:500;">Previsti (da form)</div>
                    <div style="font-size:.7rem;color:#9ca3af;">con almeno una presenza dichiarata</div>
                </div>
            @endif
            <div style="width:1px;height:40px;background:#f3f4f6;"></div>
            <div>
                <div style="font-size:1.6rem;font-weight:800;line-height:1;color:{{ $this->totConCheckin < $this->totPresenti ? '#d97706' : '#15803d' }};">
                    {{ $this->totConCheckin }}
                </div>
                <div style="font-size:.75rem;color:#6b7280;margin-top:3px;font-weight:500;">Con dati check-in</div>
                @if($this->totConCheckin < $this->totPresenti)
                    <div style="font-size:.7rem;color:#9ca3af;">
                        {{ $this->totPresenti - $this->totConCheckin }} conteggiati da form
                    </div>
                @endif
            </div>
            @if(count($this->righe) > 0)
                <div style="width:1px;height:40px;background:#f3f4f6;"></div>
                <div>
                    <div style="font-size:1.6rem;font-weight:800;color:#111827;line-height:1;">
                        {{ array_sum(array_column($this->righe, 'count')) }}
                    </div>
                    <div style="font-size:.75rem;color:#6b7280;margin-top:3px;font-weight:500;">Totale pasti / pernotti</div>
                    <div style="font-size:.7rem;color:#9ca3af;">somma di tutti gli slot del giorno</div>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Corsi del giorno --}}
    @if(count($this->corsiLista) > 0)
        @foreach($this->corsiLista as $corso)
            @php
                $pct = $corso['totIscritti'] > 0
                    ? round($corso['totCheckin'] / $corso['totIscritti'] * 100)
                    : 0;
                $coloreCount = $corso['totCheckin'] === $corso['totIscritti'] ? '#15803d'
                    : ($corso['totCheckin'] > 0 ? '#d97706' : '#6b7280');
            @endphp
            <x-filament::section>
                <div slot="heading">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span style="font-weight:700;font-size:.95rem;color:#111827;">{{ $corso['nome'] }}</span>
                        <span style="font-size:.82rem;color:{{ $coloreCount }};font-weight:700;">
                            {{ $corso['totCheckin'] }} / {{ $corso['totIscritti'] }} check-in
                        </span>
                        @if($corso['totIscritti'] > 0)
                            <div style="flex:1;min-width:80px;max-width:140px;height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden;">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $coloreCount }};border-radius:3px;transition:width .3s;"></div>
                            </div>
                        @endif
                    </div>
                </div>

                <details>
                    <summary style="cursor:pointer;font-size:.82rem;font-weight:600;color:#6b7280;padding:4px 0;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;">
                        <span style="font-size:.6rem;">▶</span>
                        Elenco iscritti ({{ $corso['totIscritti'] }})
                    </summary>
                    <div style="margin-top:10px;overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                            <thead>
                                <tr style="border-bottom:2px solid #f3f4f6;">
                                    <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nominativo</th>
                                    <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                                    <th style="padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;text-align:center;">Check-in</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($corso['partecipanti'] as $p)
                                    <tr style="border-bottom:1px solid #f9fafb;">
                                        <td style="padding:8px 12px;font-weight:600;color:#111827;">{{ $p['nome'] }}</td>
                                        <td style="padding:8px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $p['cf'] ?: '—' }}</td>
                                        <td style="padding:8px 12px;text-align:center;">
                                            @if($p['checkin'])
                                                <span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;">✓ Effettuato</span>
                                            @else
                                                <span style="background:#f3f4f6;color:#9ca3af;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;">Non ancora</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </x-filament::section>
        @endforeach
    @endif

    {{-- Staff in campo quel giorno (deduplicato rispetto ai corsi) --}}
    @if(count($this->staffLista) > 0)
        <x-filament::section>
            <div slot="heading">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span style="font-weight:700;font-size:.95rem;color:#111827;">Staff</span>
                    <span style="font-size:.82rem;color:#92400e;font-weight:700;">
                        {{ count($this->staffLista) }} in campo
                    </span>
                </div>
            </div>

            <details>
                <summary style="cursor:pointer;font-size:.82rem;font-weight:600;color:#6b7280;padding:4px 0;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;">
                    <span style="font-size:.6rem;">▶</span>
                    Elenco staff ({{ count($this->staffLista) }})
                </summary>
                <div style="margin-top:10px;overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                        <thead>
                            <tr style="border-bottom:2px solid #f3f4f6;">
                                <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nominativo</th>
                                <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                                <th style="padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;text-align:center;">Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->staffLista as $s)
                                <tr style="border-bottom:1px solid #f9fafb;">
                                    <td style="padding:8px 12px;font-weight:600;color:#111827;">{{ $s['nome'] }}</td>
                                    <td style="padding:8px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $s['cf'] ?: '—' }}</td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;">In campo</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </x-filament::section>
    @endif

    {{-- Separatore sezione pasti --}}
    @if(count($this->righe) > 0)
        <div style="display:flex;align-items:center;gap:12px;margin:4px 0;">
            <div style="flex:1;height:1px;background:#e5e7eb;"></div>
            <span style="font-size:.72rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Pasti e pernotti</span>
            <div style="flex:1;height:1px;background:#e5e7eb;"></div>
        </div>
    @endif

    {{-- Griglia pasti --}}
    @forelse($this->righe as $riga)
        @php
            $coloreBg    = match($riga['tipo']) { 'colazione' => '#fffbeb', 'pranzo' => '#eff6ff', 'cena' => '#f5f3ff', 'pernotto' => '#f0fdf4', default => '#f9fafb' };
            $coloreNum   = match($riga['tipo']) { 'colazione' => '#92400e', 'pranzo' => '#1d4ed8', 'cena' => '#5b21b6', 'pernotto' => '#15803d', default => '#374151' };
            $coloreBord  = match($riga['tipo']) { 'colazione' => '#fde68a', 'pranzo' => '#bfdbfe', 'cena' => '#ddd6fe', 'pernotto' => '#bbf7d0', default => '#e5e7eb' };
            $nConf       = count($riga['personeConfermate']);
            $nNonConf    = count($riga['personeNonConfermate']);
            $nAttesi     = count($riga['attetiNonArrivati']);
            $nEsigenze   = count($riga['personeConEsigenze']);
        @endphp

        <x-filament::section>
            {{-- Intestazione slot --}}
            <div slot="heading">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div style="background:{{ $coloreBg }};border:1px solid {{ $coloreBord }};border-radius:8px;padding:6px 14px;font-weight:700;font-size:.9rem;color:{{ $coloreNum }};">
                        {{ $riga['label'] }}
                    </div>
                    <div style="display:flex;align-items:baseline;gap:4px;">
                        <span style="font-size:1.5rem;font-weight:800;color:{{ $coloreNum }};">{{ $nConf }}</span>
                        @if($nNonConf > 0)
                            <span style="font-size:.85rem;color:#9ca3af;font-weight:500;">/ {{ $this->totPresenti }} presenti</span>
                        @else
                            <span style="font-size:.75rem;color:#6b7280;">confermati</span>
                        @endif
                    </div>
                    @if($nNonConf > 0)
                        <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;">
                            {{ $nNonConf }} da verificare
                        </span>
                    @endif
                    @if($nAttesi > 0)
                        <span style="background:#eff6ff;color:#1d4ed8;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;">
                            {{ $nAttesi }} attesi non arrivati
                        </span>
                    @endif
                    @if($nEsigenze > 0 && $riga['tipo'] !== 'pernotto')
                        <span style="background:#fee2e2;color:#b91c1c;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;">
                            {{ $nEsigenze }} con esigenze alimentari
                        </span>
                    @endif
                </div>
            </div>

            @php $summaryStyle = "cursor:pointer;font-size:.82rem;font-weight:600;color:#6b7280;padding:4px 0;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;"; @endphp

            {{-- Esigenze alimentari PRIMA — solo pasti (non pernotto) --}}
            @if($riga['tipo'] !== 'pernotto')
                @if($nEsigenze > 0)
                    <details style="margin-bottom:6px;">
                        <summary style="{{ $summaryStyle }}">
                            <span style="font-size:.6rem;">▶</span>
                            Esigenze alimentari ({{ $nEsigenze }})
                        </summary>
                        <div style="overflow-x:auto;margin-top:8px;">
                            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                                <thead>
                                    <tr style="border-bottom:2px solid #f3f4f6;">
                                        <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;width:35%;">Nominativo</th>
                                        <th style="text-align:left;padding:6px 12px;color:#6b7280;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Allergeni / Diete / Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($riga['personeConEsigenze'] as $persona)
                                        <tr style="border-bottom:1px solid #f9fafb;">
                                            <td style="padding:9px 12px;vertical-align:top;">
                                                <div style="font-weight:600;color:#111827;">{{ $persona['nome'] }}</div>
                                                @if($persona['cf'])
                                                    <div style="font-size:.72rem;color:#9ca3af;font-family:monospace;">{{ $persona['cf'] }}</div>
                                                @endif
                                            </td>
                                            <td style="padding:9px 12px;vertical-align:top;">
                                                @foreach($persona['allergeni'] as $a)
                                                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;background:#fee2e2;color:#b91c1c;margin:1px 2px 1px 0;">{{ $a }}</span>
                                                @endforeach
                                                @foreach($persona['diete'] as $d)
                                                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;background:#fef9c3;color:#92400e;margin:1px 2px 1px 0;">{{ $d }}</span>
                                                @endforeach
                                                @if($persona['note'])
                                                    <div style="font-size:.78rem;color:#6b7280;font-style:italic;margin-top:3px;">{{ $persona['note'] }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @else
                    <p style="color:#9ca3af;font-size:.82rem;margin:0 0 6px;">Nessuna esigenza alimentare dichiarata.</p>
                @endif
            @endif

            {{-- Accordion confermati --}}
            @if($nConf > 0)
                <details style="margin-bottom:6px;">
                    <summary style="{{ $summaryStyle }}">
                        <span style="font-size:.6rem;">▶</span>
                        Confermati ({{ $nConf }})
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
                                @foreach($riga['personeConfermate'] as $idx => $p)
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
            @endif

            {{-- Accordion non confermati --}}
            @if($nNonConf > 0)
                <details style="margin-bottom:6px;">
                    <summary style="{{ $summaryStyle }}color:#92400e;">
                        <span style="font-size:.6rem;">▶</span>
                        Non confermati – da verificare ({{ $nNonConf }})
                    </summary>
                    <div style="margin-top:8px;overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                            <thead>
                                <tr style="border-bottom:2px solid #fef3c7;">
                                    <th style="text-align:left;padding:5px 12px;color:#92400e;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">#</th>
                                    <th style="text-align:left;padding:5px 12px;color:#92400e;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nominativo</th>
                                    <th style="text-align:left;padding:5px 12px;color:#92400e;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riga['personeNonConfermate'] as $idx => $p)
                                    <tr style="border-bottom:1px solid #fef9ec;background:{{ $idx % 2 === 1 ? '#fffbeb' : 'white' }};">
                                        <td style="padding:7px 12px;color:#d97706;font-size:.75rem;font-family:monospace;width:36px;">{{ $idx + 1 }}</td>
                                        <td style="padding:7px 12px;font-weight:600;color:#111827;">{{ $p['nome'] }}</td>
                                        <td style="padding:7px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $p['cf'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif

            {{-- Accordion attesi ma non arrivati --}}
            @if($nAttesi > 0)
                <details style="margin-bottom:6px;">
                    <summary style="{{ $summaryStyle }}color:#1d4ed8;">
                        <span style="font-size:.6rem;">▶</span>
                        Attesi ma non arrivati ({{ $nAttesi }})
                    </summary>
                    <div style="margin-top:8px;overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                            <thead>
                                <tr style="border-bottom:2px solid #dbeafe;">
                                    <th style="text-align:left;padding:5px 12px;color:#1d4ed8;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">#</th>
                                    <th style="text-align:left;padding:5px 12px;color:#1d4ed8;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Nominativo</th>
                                    <th style="text-align:left;padding:5px 12px;color:#1d4ed8;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Codice fiscale</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riga['attetiNonArrivati'] as $idx => $p)
                                    <tr style="border-bottom:1px solid #eff6ff;background:{{ $idx % 2 === 1 ? '#eff6ff' : 'white' }};">
                                        <td style="padding:7px 12px;color:#93c5fd;font-size:.75rem;font-family:monospace;width:36px;">{{ $idx + 1 }}</td>
                                        <td style="padding:7px 12px;font-weight:600;color:#111827;">{{ $p['nome'] }}</td>
                                        <td style="padding:7px 12px;font-family:monospace;font-size:.78rem;color:#6b7280;">{{ $p['cf'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif

        </x-filament::section>
    @empty
        <x-filament::section>
            <p style="color:#9ca3af;text-align:center;padding:24px 0;font-size:.9rem;">
                Nessuna presenza registrata per questo giorno.<br>
                <span style="font-size:.8rem;">Controlla che siano stati eseguiti dei check-in.</span>
            </p>
        </x-filament::section>
    @endforelse

    </div>
</x-filament-panels::page>
