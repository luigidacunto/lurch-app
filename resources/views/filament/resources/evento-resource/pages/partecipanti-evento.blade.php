<x-filament-panels::page>
    @php
        $q    = mb_strtolower(trim($this->search));
        $rows = collect($this->partecipanti)->when($q !== '', fn ($c) => $c->filter(function ($p) use ($q) {
            return str_contains(mb_strtolower($p['cognome'] . ' ' . $p['nome']), $q)
                || str_contains($p['comitato_search'], $q)
                || str_contains(mb_strtolower($p['codice_fiscale']), $q);
        }))->values();

        $tipoLabel  = ['iscritto' => 'Corsista', 'staff' => 'Staff', 'ospite' => 'Ospite'];
        $tipoColor  = ['iscritto' => '#4f46e5', 'staff' => '#0e7490', 'ospite' => '#a21caf'];
        $tipoBg     = ['iscritto' => '#eef2ff', 'staff' => '#ecfeff', 'ospite' => '#fdf4ff'];
        $tipoBorder = ['iscritto' => '#c7d2fe', 'staff' => '#a5f3fc', 'ospite' => '#f5d0fe'];
    @endphp

    {{-- Search --}}
    <x-filament::section>
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="position:relative; flex:1; max-width:480px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"
                     style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none; flex-shrink:0;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    wire:model.live="search"
                    type="text"
                    placeholder="Cerca per nome, cognome, CF, comitato regionale o locale&hellip;"
                    style="width:100%; padding:8px 12px 8px 38px; font-size:13px; border:1px solid #d1d5db; border-radius:8px; outline:none; color:#111827; background:white;"
                />
            </div>
            <span style="font-size:13px; color:#6b7280; white-space:nowrap;">
                {{ $rows->count() }}{{ $q !== '' ? ' / ' . count($this->partecipanti) : '' }} partecipanti
                @php $nOspiti = collect($this->partecipanti)->filter(fn ($p) => in_array('ospite', $p['tipi'], true))->count(); @endphp
                @if($nOspiti > 0)
                    <span style="color:#6b21a8;">(di cui {{ $nOspiti }} {{ $nOspiti === 1 ? 'ospite' : 'ospiti' }})</span>
                @endif
            </span>
        </div>
    </x-filament::section>

    {{-- Tabella --}}
    <x-filament::section>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid #e5e7eb; background:#f9fafb;">
                        <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151; white-space:nowrap;">Cognome e nome</th>
                        <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151; font-family:monospace; white-space:nowrap;">Codice fiscale</th>
                        <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151; white-space:nowrap;">Com. Regionale</th>
                        <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151;">Com. Locale / Sottosede</th>
                        <th style="text-align:center; padding:10px 14px; font-weight:600; color:#374151; white-space:nowrap;">Tipo</th>
                        <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151;">Corsi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $idx => $p)
                        <tr style="border-bottom:1px solid #f3f4f6; background:{{ $idx % 2 === 1 ? '#f9fafb' : 'white' }};">
                            <td style="padding:10px 14px; white-space:nowrap;">
                                <span style="font-weight:600; color:#111827;">{{ $p['cognome'] }}</span>
                                <span style="color:#374151;"> {{ $p['nome'] }}</span>
                            </td>
                            <td style="padding:10px 14px; font-family:monospace; font-size:12px; color:#6b7280; white-space:nowrap;">
                                {{ $p['codice_fiscale'] ?: '&mdash;' }}
                            </td>
                            <td style="padding:10px 14px; color:#374151; white-space:nowrap;">
                                {{ $p['comitato_regionale'] }}
                            </td>
                            <td style="padding:10px 14px; color:#374151;">
                                {{ $p['comitato_display'] }}
                            </td>
                            <td style="padding:10px 14px; text-align:center;">
                                <div style="display:flex; flex-wrap:wrap; gap:4px; justify-content:center;">
                                    @foreach($p['tipi'] as $t)
                                        <span style="display:inline-block; font-size:11px; font-weight:600; white-space:nowrap;
                                                     background:{{ $tipoBg[$t] }}; color:{{ $tipoColor[$t] }};
                                                     border:1px solid {{ $tipoBorder[$t] }};
                                                     border-radius:20px; padding:2px 10px;">
                                            {{ $tipoLabel[$t] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td style="padding:10px 14px; color:#374151;">
                                @if($p['corsi'])
                                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                        @foreach(explode(' · ', $p['corsi']) as $codice)
                                            <span style="font-size:11px; font-family:monospace; background:#f3f4f6; color:#374151; border-radius:4px; padding:1px 6px; white-space:nowrap;">{{ $codice }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span style="color:#d1d5db;">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:32px; text-align:center; font-size:13px; color:#9ca3af;">
                                Nessun partecipante trovato{{ $q !== '' ? ' per "' . e($this->search) . '"' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

</x-filament-panels::page>
