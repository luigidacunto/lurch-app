<x-filament-panels::page>
    @php
        $esame     = $this->record;
        $corso     = $esame->corso;
        $laboratori = $this->laboratori();
        $commissione = $esame->docentiCommissione()->with('socio')->get();
        $nDiscenti = $esame->discenti()->count();
        $nAmmessi  = $esame->discentiAmmessi()->count();
        $nDomande  = $esame->testDomande()->count();
        $testoTest = $nDomande === 0
            ? 'Nessuna domanda'
            : $nDomande . ' domande · avviato per ' . $esame->testAvvii()->whereNotNull('avviato_il')->count() . ' · inviati ' . $esame->testConsegne()->whereNotNull('consegnato_il')->count() . ' su ' . $nAmmessi . ' ammessi';
        $righe = [
            'Esame'            => $esame->titolo,
            'Corso'            => $corso->nome,
            'Tipo'             => $esame->tipo_codice,
            'Versione modello' => $esame->modello_versione,
            'Stato'            => \App\Filament\Resources\EsameResource::etichettaStato($esame->stato),
            'LAP'              => rtrim(rtrim((string) $esame->lap, '0'), '.') . '%',
            'Direttore'        => $corso->direttore?->nome_completo ?? $corso->direttore_corso ?? '—',
            'Presidente o delegato' => $corso->presidente?->nome_completo ?? 'non indicato',
            'Discenti'         => $nDiscenti,
            'Prova finale'     => $esame->isBozza() ? '—' : ($esame->provaFinaleAperta() ? 'Aperta dal ' . $esame->prova_finale_aperta_il->format('d/m/Y H:i') : 'Non aperta'),
            'Test scritto'     => $testoTest,
        ];
    @endphp

    <x-filament::section heading="Riepilogo">
        <dl style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px 24px; margin:0;">
            @foreach($righe as $etichetta => $valore)
                <div>
                    <dt style="font-size:12px; color:#6b7280;">{{ $etichetta }}</dt>
                    <dd style="margin:0; font-weight:600; overflow-wrap:anywhere;">{{ $valore }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section heading="Commissione" description="Direttore e presidente o delegato si leggono dal corso; i docenti si scelgono dal pulsante Azioni → Commissione.">
        @if($commissione->isEmpty())
            <p style="margin:0; color:#b45309;">⚠ Nessun docente in commissione: con 0 docenti il direttore compila le schede come docente.</p>
        @else
            <p style="margin:0;">{{ $commissione->map(fn ($c) => $c->socio->cognome . ' ' . $c->socio->nome)->implode(' · ') }}</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Laboratori">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="border-bottom:2px solid #e5e7eb; text-align:left; color:#374151;">
                    <th style="padding:6px 10px;">Laboratorio</th>
                    <th style="padding:6px 10px;">Gruppi</th>
                    <th style="padding:6px 10px;">Docenti</th>
                </tr>
            </thead>
            <tbody>
                @foreach($laboratori as $lab)
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:6px 10px;">Laboratorio {{ $lab['numero'] }}</td>
                        <td style="padding:6px 10px;">{{ $lab['gruppi'] }}</td>
                        <td style="padding:6px 10px;">{{ $lab['docenti'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
            <x-filament::button tag="a" size="sm" color="info" :href="\App\Filament\Resources\EsameResource::getUrl('laboratori', ['record' => $esame])">Gestisci laboratori e gruppi</x-filament::button>
            <x-filament::button tag="a" size="sm" color="info" :href="\App\Filament\Resources\EsameResource::getUrl('discenti', ['record' => $esame])">Gestisci discenti ({{ $nDiscenti }})</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
