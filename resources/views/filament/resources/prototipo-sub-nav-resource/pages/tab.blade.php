<x-filament-panels::page>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px 24px;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; margin-bottom:6px;">
            Prototipo — sub-navigation nativa Filament (getRecordSubNavigation)
        </div>
        <p style="font-size:0.95rem; color:#374151; margin:0 0 10px;">
            Record aperto: <strong>{{ $this->record->nome }}</strong>
        </p>
        <p style="font-size:0.9rem; color:#374151; margin:0;">{{ $this->descrizione }}</p>
    </div>

    <p style="margin-top:16px;">
        <a href="{{ \App\Filament\Resources\PrototipoSubNavResource::getUrl('index') }}" style="color:#2563eb; font-size:0.85rem;">&larr; Torna all'elenco eventi (prototipo)</a>
    </p>
</x-filament-panels::page>
