<x-filament-panels::page>
    <x-filament-actions::modals />

    @php
        $graduatoria = $this->getRecord();
        $codiciRilevanti = $graduatoria->codiciQualificheRilevanti();
        $livelloExport = $graduatoria->comitato_locale_id ? 'locale' : 'regionale';
    @endphp

    {{-- Riga dedicata sotto titolo/sottotitolo per il pulsante userscript, che deve restare
         fuori dall'ActionGroup "Azioni" (vedi GestisciCandidati::getHeaderActions()) — quello
         vero, in alto nell'header standard di Filament, raggruppa copiaLink/anteprimaEmail/
         anteprimaExcel/scaricaExcel per convenzione. Un solo bottone: lo userscript lo
         sostituisce con i due bottoni funzionanti (Cerca ID + Importa batch) quando attivo e
         aggiornato — vedi il commento su installaUserscriptGaiaAction(). --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:-16px;margin-bottom:16px;">
        {{ $this->installaUserscriptGaia }}
    </div>

    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#78350f;">
        <strong>⚠️ Prima della chiusura ufficiale:</strong> il calcolo si basa sulle qualifiche già importate da GAIA. Prima di chiudere la graduatoria il giorno del termine, si consiglia di fare un nuovo export a livello {{ $livelloExport }} (in base al comitato di apertura di questa graduatoria/corso) per ogni qualifica coinvolta nel calcolo, e reimportarlo nella pagina "Qualifiche". Per la Sicurezza non serve più importare i vecchi codici ormai dismessi (es. SIC2): basta esportare/importare solo le qualifiche attualmente in vigore e conseguibili.
        @if ($codiciRilevanti->isNotEmpty())
            <br><span style="color:#92400e;">Qualifiche coinvolte in questa graduatoria: {{ $codiciRilevanti->implode(', ') }}</span>
        @endif
    </div>

    {{ $this->content }}

    @livewire(\App\Filament\Resources\GraduatoriaResource\RelationManagers\CandidatiEsclusiRelationManager::class, [
        'ownerRecord' => $this->getRecord(),
        'pageClass' => \App\Filament\Resources\GraduatoriaResource\Pages\GestisciCandidati::class,
    ], key('candidati-esclusi-' . $this->getRecord()->getKey()))
</x-filament-panels::page>
