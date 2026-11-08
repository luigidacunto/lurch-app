<x-filament-panels::page>
    @php
        $esame = $this->record;
        $righe = [
            'Esame'        => $esame->titolo,
            'Stato'        => \App\Filament\Resources\EsameResource::etichettaStato($esame->stato),
            'Ammessi'      => $esame->discentiAmmessi()->count() . ' su ' . $esame->discenti()->count() . ' discenti',
            'Prova finale' => $esame->provaFinaleAperta() ? 'Aperta dal ' . $esame->prova_finale_aperta_il->format('d/m/Y H:i') : 'Non aperta',
        ];
    @endphp

    <x-filament::section heading="Stato dell'esame">
        <dl style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px 24px; margin:0;">
            @foreach($righe as $etichetta => $valore)
                <div>
                    <dt style="font-size:12px; color:#6b7280;">{{ $etichetta }}</dt>
                    <dd style="margin:0; font-weight:600; overflow-wrap:anywhere;">{{ $valore }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section heading="Avanzamento delle valutazioni">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="border-bottom:2px solid #e5e7eb; text-align:left; color:#374151;">
                    <th style="padding:6px 10px;">Prova</th>
                    <th style="padding:6px 10px;">Avanzamento</th>
                    <th style="padding:6px 10px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->prove() as [$nome, $testo, $rotta])
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:6px 10px; font-weight:600;">{{ $nome }}</td>
                        <td style="padding:6px 10px;">{{ $testo }}</td>
                        <td style="padding:6px 10px; text-align:right;">
                            <x-filament::button tag="a" size="sm" color="info" :href="\App\Filament\Resources\EsameResource::getUrl($rotta, ['record' => $esame])">Apri</x-filament::button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
