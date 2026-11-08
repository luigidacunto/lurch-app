<x-filament-panels::page>
    <x-filament-actions::modals />

    {{-- ── MODALE ANALISI IN CORSO ──────────────────────────────────────────
         wire:poll SOLO dentro questo blocco — stessa regola dell'import vero sotto:
         un poll costante disabilita ad ogni tick tutti i pulsanti Filament della pagina.
         Elaborazione pilotata dal
         browser, niente coda: se la scheda si chiude, l'analisi si ferma qui. --}}
    @if ($this->analisiInCorso)
    <div wire:poll.500ms="elaboraChunkAnalisi" style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:420px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px;">
                <svg style="width:24px;height:24px;color:#2563eb;flex-shrink:0;animation:spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <div style="font-weight:700; font-size:1rem; color:#111827;">Analisi del file in corso…</div>
            </div>

            @php $pctA = $this->analisiTotaleRighe > 0 ? min(100, (int) round($this->analisiOffset / $this->analisiTotaleRighe * 100)) : 0; @endphp
            <div style="background:#e5e7eb; border-radius:999px; height:10px; overflow:hidden; margin-bottom:8px;">
                <div style="background:#2563eb; height:100%; width:{{ $pctA }}%; transition:width .3s ease;"></div>
            </div>
            <div style="font-size:0.85rem; color:#4b5563; margin-bottom:18px;">
                {{ min($this->analisiOffset, $this->analisiTotaleRighe) }} di {{ $this->analisiTotaleRighe }} righe analizzate ({{ $pctA }}%)
            </div>

            <div style="text-align:right;">
                <x-filament::button wire:click="annullaImport" color="gray" size="xs" icon="heroicon-m-x-mark">
                    Annulla
                </x-filament::button>
            </div>
        </div>
        <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    </div>
    @endif

    {{-- ── MODALE ERRORE ANALISI ──────────────────────────────────────────── --}}
    @if ($this->analisiErrore)
    <div style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:460px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="display:flex; align-items:flex-start; gap:12px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:22px;height:22px;color:#dc2626;flex-shrink:0;margin-top:1px;">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <div style="flex:1;">
                    <div style="font-weight:700; color:#111827; font-size:1rem;">Errore durante l'analisi</div>
                    <div style="color:#dc2626; font-size:0.85rem; margin-top:4px;">{{ $this->analisiErrore }}</div>
                </div>
            </div>
            <div style="margin-top:18px; text-align:right;">
                <x-filament::button wire:click="annullaImport" color="gray" size="xs" icon="heroicon-m-x-mark">
                    Chiudi
                </x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── MODALE ANTEPRIMA PRONTA ────────────────────────────────────────── --}}
    @if ($this->analisiPronta)
    <div style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:620px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="font-weight:700; font-size:1rem; color:#111827; margin-bottom:4px;">Anteprima import soci</div>
            <div style="color:#6b7280; font-size:0.85rem; margin-bottom:18px;">
                Controlla i dati prima di confermare. L'import mostrerà l'avanzamento in tempo reale: non chiudere la scheda finché non è completato.
            </div>

            {{-- Contatori --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:20px;">
                @if ($this->analisiCreati > 0)
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#15803d; line-height:1;">{{ $this->analisiCreati }}</div>
                    <div style="font-size:0.78rem; color:#166534; margin-top:3px;">Nuovi soci</div>
                </div>
                @endif
                @if ($this->analisiAggiornati > 0)
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#1d4ed8; line-height:1;">{{ $this->analisiAggiornati }}</div>
                    <div style="font-size:0.78rem; color:#1e40af; margin-top:3px;">Aggiornamenti</div>
                </div>
                @endif
                @if ($this->analisiEstensioni > 0)
                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#7e22ce; line-height:1;">{{ $this->analisiEstensioni }}</div>
                    <div style="font-size:0.78rem; color:#6b21a8; margin-top:3px;">Estensioni</div>
                </div>
                @endif
                @if ($this->analisiDipendenti > 0)
                <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#c2410c; line-height:1;">{{ $this->analisiDipendenti }}</div>
                    <div style="font-size:0.78rem; color:#9a3412; margin-top:3px;">Dipendenti</div>
                </div>
                @endif
                @if ($this->analisiSaltati > 0)
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#dc2626; line-height:1;">{{ $this->analisiSaltati }}</div>
                    <div style="font-size:0.78rem; color:#b91c1c; margin-top:3px;">Saltati / Errori</div>
                </div>
                @endif
                <div style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#475569; line-height:1;">{{ $this->analisiRigheLette }}</div>
                    <div style="font-size:0.78rem; color:#64748b; margin-top:3px;">Righe totali</div>
                </div>
            </div>

            {{-- Avviso tipi sconosciuti --}}
            @if (!empty($this->analisiTipiSconosciutiRun))
            <div style="display:flex; gap:10px; align-items:flex-start; margin-bottom:16px; padding:12px 16px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; font-size:0.85rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;color:#d97706;">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span><strong>Tipi non riconosciuti ({{ count($this->analisiTipiSconosciutiRun) }} righe saltate):</strong> {{ implode(', ', array_map(fn($t) => '"'.$t.'"', $this->analisiTipiSconosciutiRun)) }}</span>
            </div>
            @endif

            {{-- Tabella errori --}}
            @if (!empty($this->analisiErroriRun))
            <div style="margin-bottom:16px;">
                <div style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">
                    Righe con errore ({{ count($this->analisiErroriRun) }})
                </div>
                <div style="border:1px solid #fecaca; border-radius:8px; overflow:hidden; max-height:220px; overflow-y:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                        <thead>
                            <tr style="position:sticky; top:0; z-index:2; background:#fef2f2; border-bottom:1px solid #fecaca;">
                                <th style="padding:8px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; color:#b91c1c; width:36px;">#</th>
                                <th style="padding:8px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; color:#b91c1c;">Errore</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->analisiErroriRun as $i => $errore)
                            <tr style="background:{{ $i % 2 === 0 ? '#fff' : '#fef9f9' }}; border-bottom:1px solid #fee2e2;">
                                <td style="padding:7px 12px; color:#94a3b8; font-size:0.75rem;">{{ $i + 1 }}</td>
                                <td style="padding:7px 12px; color:#dc2626; font-size:0.78rem;">{{ $errore }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @php $totImportabili = $this->analisiCreati + $this->analisiAggiornati + $this->analisiEstensioni; @endphp

            {{-- Azioni --}}
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                @if ($totImportabili > 0)
                <x-filament::button
                    wire:click="confermaImport"
                    wire:confirm="Confermi l'avvio dell'import di {{ $totImportabili }} soci? Si aprirà una finestra con l'avanzamento: non chiuderla finché non è completo."
                    icon="heroicon-m-check"
                    color="success"
                    size="md"
                >
                    Conferma import ({{ $totImportabili }} soci)
                </x-filament::button>
                @endif
                <x-filament::button
                    wire:click="annullaImport"
                    color="gray"
                    icon="heroicon-m-x-mark"
                    size="md"
                >
                    Annulla
                </x-filament::button>
                @if ($this->analisiSaltati > 0)
                <span style="font-size:0.8rem; color:#94a3b8;">{{ $this->analisiSaltati }} righe con errore verranno saltate</span>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ── MODALE IMPORT IN CORSO ─────────────────────────────────────────────
         wire:poll SOLO dentro questo blocco: esiste nel DOM solo mentre importInCorso
         è vero, mai prima — stessa regola già applicata sopra per l'analisi.
         Elaborazione pilotata dal
         browser: se questa scheda si chiude, non arrivano più chiamate e l'elaborazione
         si ferma esattamente dov'era — nessuna coda, nessuna ripresa, deliberato. --}}
    @if ($this->importInCorso)
    <div wire:poll.500ms="elaboraChunkImport" style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:480px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px;">
                <svg style="width:24px;height:24px;color:#16a34a;flex-shrink:0;animation:spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <div style="font-weight:700; font-size:1rem; color:#111827;">Import soci in corso…</div>
            </div>

            @php $pct = $this->importTotale > 0 ? min(100, (int) round($this->importOffset / $this->importTotale * 100)) : 0; @endphp
            <div style="background:#e5e7eb; border-radius:999px; height:10px; overflow:hidden; margin-bottom:8px;">
                <div style="background:#16a34a; height:100%; width:{{ $pct }}%; transition:width .3s ease;"></div>
            </div>
            <div style="font-size:0.85rem; color:#4b5563; margin-bottom:18px;">
                {{ min($this->importOffset, $this->importTotale) }} di {{ $this->importTotale }} soci elaborati ({{ $pct }}%)
            </div>

            <div style="display:flex; gap:10px; align-items:flex-start; padding:12px 14px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; font-size:0.82rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;flex-shrink:0;margin-top:1px;color:#d97706;">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span>Non chiudere questa scheda: se la chiudi o cambi pagina, l'import si interrompe qui — dovrai rifarlo da capo.</span>
            </div>
        </div>
        <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    </div>
    @endif

    {{-- ── MODALE RIEPILOGO FINALE ────────────────────────────────────────── --}}
    @if ($this->importCompletato && ! $this->allineaAnteprimaPronta && ! $this->allineaCompletato)
    @php
        $totImportati = $this->importCreati + $this->importAggiornati + $this->importEstensioni;
    @endphp
    <div style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:520px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="font-weight:700; font-size:1rem; color:#111827; margin-bottom:16px;">Import completato</div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:10px; margin-bottom:16px;">
                @if ($this->importCreati > 0)
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#15803d; line-height:1;">{{ $this->importCreati }}</div>
                    <div style="font-size:0.75rem; color:#166534; margin-top:3px;">Creati</div>
                </div>
                @endif
                @if ($this->importAggiornati > 0)
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#1d4ed8; line-height:1;">{{ $this->importAggiornati }}</div>
                    <div style="font-size:0.75rem; color:#1e40af; margin-top:3px;">Aggiornati</div>
                </div>
                @endif
                @if ($this->importEstensioni > 0)
                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#7e22ce; line-height:1;">{{ $this->importEstensioni }}</div>
                    <div style="font-size:0.75rem; color:#6b21a8; margin-top:3px;">Estensioni</div>
                </div>
                @endif
                @if ($this->importDipendenti > 0)
                <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#c2410c; line-height:1;">{{ $this->importDipendenti }}</div>
                    <div style="font-size:0.75rem; color:#9a3412; margin-top:3px;">Dipendenti</div>
                </div>
                @endif
                @if ($this->importSaltati > 0)
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#dc2626; line-height:1;">{{ $this->importSaltati }}</div>
                    <div style="font-size:0.75rem; color:#b91c1c; margin-top:3px;">Saltati</div>
                </div>
                @endif
                <div style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#475569; line-height:1;">{{ $this->importTotale }}</div>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:3px;">Righe totali</div>
                </div>
            </div>

            @if (count($this->importErroriRun) > 0)
            <div style="font-size:0.8rem; color:#b91c1c; margin-bottom:16px;">
                {{ count($this->importErroriRun) }} righe con errore — dettaglio nella notifica (campanella in alto).
            </div>
            @endif

            <x-filament::button wire:click="chiudiRiepilogoImport" color="gray" icon="heroicon-m-x-mark">
                Chiudi
            </x-filament::button>
        </div>
    </div>
    @endif

    {{-- ── MODALE ANTEPRIMA ALLINEAMENTO (dimessi / pulizia estensioni) ─────── --}}
    @if ($this->allineaAnteprimaPronta)
    <div style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:680px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="font-weight:700; font-size:1rem; color:#111827; margin-bottom:4px;">Allineamento soci — anteprima</div>
            <div style="color:#6b7280; font-size:0.85rem; margin-bottom:18px;">
                L'import è stato applicato. Questi sono i soci che verrebbero <strong>segnati come dimessi</strong> (spariscono da elenco Soci e ricerca Qualifiche, non vengono cancellati),
                le <strong>estensioni da rimuovere</strong> e i soci che <strong>rientrano</strong> (di nuovo presenti in lista). Nulla è ancora stato scritto: conferma per applicare.
            </div>

            @php
                $nDim = count($this->allineaPreviewDimessi);
                $nEst = count($this->allineaPreviewEstensioni);
                $nRie = count($this->allineaPreviewRientri);
                $nAttive = collect($this->allineaPreviewDimessi)->where('attive', true)->count();

                $parti = ["{$nDim} soci verranno segnati come dimessi"];
                if ($this->allineaPuliziaEstensioni) $parti[] = "{$nEst} estensioni rimosse";
                if ($nRie) $parti[] = "{$nRie} soci riattivati";
                $msgConfermaAllinea = 'Confermi? ' . implode(', ', $parti)
                    . ". L'operazione è reversibile a mano dalla scheda socio.";
            @endphp

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:18px;">
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#dc2626; line-height:1;">{{ $nDim }}</div>
                    <div style="font-size:0.78rem; color:#b91c1c; margin-top:3px;">Da segnare dimessi</div>
                </div>
                @if ($this->allineaPuliziaEstensioni)
                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#7e22ce; line-height:1;">{{ $nEst }}</div>
                    <div style="font-size:0.78rem; color:#6b21a8; margin-top:3px;">Estensioni da rimuovere</div>
                </div>
                @endif
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#15803d; line-height:1;">{{ $nRie }}</div>
                    <div style="font-size:0.78rem; color:#166534; margin-top:3px;">Rientri (riattivati)</div>
                </div>
            </div>

            @if ($nAttive > 0)
            <div style="display:flex; gap:10px; align-items:flex-start; margin-bottom:16px; padding:12px 16px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; font-size:0.85rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;color:#d97706;">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span><strong>{{ $nAttive }}</strong> di questi soci hanno un'iscrizione a un evento/corso non ancora concluso (evidenziati sotto). Potrebbe trattarsi di un trasferimento non ancora registrato: la dimissione non tocca comunque eventi e corsi.</span>
            </div>
            @endif

            @if ($nDim > 0)
            <div style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Soci da segnare dimessi</div>
            <div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; max-height:240px; overflow-y:auto; margin-bottom:16px;">
                <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                    <thead><tr style="position:sticky; top:0; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:8px 12px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Socio</th>
                        <th style="padding:8px 12px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:#64748b;">C.F.</th>
                        <th style="padding:8px 12px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:#64748b;">Comitato</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($this->allineaPreviewDimessi as $r)
                        <tr style="border-bottom:1px solid #f1f5f9; {{ $r['attive'] ? 'background:#fffbeb;' : '' }}">
                            <td style="padding:7px 12px; color:#0f172a;">{{ $r['cognome'] }} {{ $r['nome'] }} @if ($r['attive'])<span style="color:#d97706;" title="Iscrizione attiva">●</span>@endif</td>
                            <td style="padding:7px 12px; color:#64748b; font-family:monospace;">{{ $r['cf'] }}</td>
                            <td style="padding:7px 12px; color:#475569;">{{ $r['comitato'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if ($this->allineaPuliziaEstensioni && $nEst > 0)
            <div style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Estensioni da rimuovere</div>
            <div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; max-height:200px; overflow-y:auto; margin-bottom:16px;">
                <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                    <tbody>
                        @foreach ($this->allineaPreviewEstensioni as $r)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:7px 12px; color:#0f172a;">{{ $r['cognome'] }} {{ $r['nome'] }}</td>
                            <td style="padding:7px 12px; color:#64748b; font-family:monospace;">{{ $r['cf'] }}</td>
                            <td style="padding:7px 12px; color:#475569;">{{ $r['comitato'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:4px;">
                <x-filament::button
                    wire:click="confermaAllineamento"
                    wire:confirm="{{ $msgConfermaAllinea }}"
                    icon="heroicon-m-check"
                    color="warning"
                    size="md"
                >
                    Conferma allineamento
                </x-filament::button>
                <x-filament::button wire:click="annullaAllineamento" color="gray" icon="heroicon-m-x-mark" size="md">
                    Salta
                </x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── MODALE RIEPILOGO RICONCILIAZIONE ───────────────────────────────── --}}
    @if ($this->allineaCompletato)
    <div style="position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; padding:28px 32px; max-width:460px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
            <div style="font-weight:700; font-size:1rem; color:#111827; margin-bottom:16px;">Allineamento completato</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:10px; margin-bottom:18px;">
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#dc2626; line-height:1;">{{ $this->allineaRisultato['dimessi'] }}</div>
                    <div style="font-size:0.75rem; color:#b91c1c; margin-top:3px;">Segnati dimessi</div>
                </div>
                @if ($this->allineaRisultato['rientri'] > 0)
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#15803d; line-height:1;">{{ $this->allineaRisultato['rientri'] }}</div>
                    <div style="font-size:0.75rem; color:#166534; margin-top:3px;">Riattivati</div>
                </div>
                @endif
                @if ($this->allineaRisultato['estensioni'] > 0)
                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:1.4rem; font-weight:700; color:#7e22ce; line-height:1;">{{ $this->allineaRisultato['estensioni'] }}</div>
                    <div style="font-size:0.75rem; color:#6b21a8; margin-top:3px;">Estensioni rimosse</div>
                </div>
                @endif
            </div>
            <x-filament::button wire:click="chiudiRiepilogoImport" color="gray" icon="heroicon-m-x-mark">
                Chiudi
            </x-filament::button>
        </div>
    </div>
    @endif

    {{-- Pannello filtri (estratto dalla tabella — stesso schema di ListQualificheSoci) --}}
    <x-filament::section>
        <x-slot name="heading">Filtri di ricerca</x-slot>
        <x-slot name="description">Seleziona almeno un comitato regionale e avvia la ricerca per visualizzare i soci.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @if ($this->ricercaAvviata)
        {{ $this->table }}
    @else
        <x-filament::section>
            <div class="flex flex-col items-center justify-center py-12 text-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-funnel"
                    class="h-12 w-12 text-gray-300"
                />
                <p class="text-gray-500 text-sm">
                    Seleziona almeno un comitato regionale e clicca <strong>Avvia ricerca</strong> per visualizzare i soci.
                </p>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
