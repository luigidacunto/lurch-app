<x-filament-panels::page>
    <x-filament-actions::modals />

    {{-- Contenitore unico: un solo figlio diretto per lo slot della pagina, così il gap-y-8
         nativo di .fi-page-content non entra in gioco — lo spazio tra i blocchi è SOLO
         questo gap, 20px, niente div vuoti aggiuntivi. --}}
    <div style="display:flex; flex-direction:column; gap:20px;">

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px 24px;">
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; margin-bottom:6px;">
                Vetrina componenti — elementi UI più usati nel pannello
            </div>
            <p style="font-size:0.9rem; color:#374151; margin:0;">
                Stessi componenti reali (tabella, azioni raggruppate, form, notifiche) usati altrove nel pannello, tutti in un'unica pagina per confronto rapido.
            </p>
        </div>

        <x-filament::section>
            <x-slot name="heading">Card statistiche</x-slot>
            <x-slot name="description">Stesso stile delle card di riepilogo import (es. "Importa soci da Excel")</x-slot>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px;">
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#15803d; line-height:1;">128</div>
                    <div style="font-size:0.78rem; color:#166534; margin-top:3px;">Creati</div>
                </div>
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#1d4ed8; line-height:1;">42</div>
                    <div style="font-size:0.78rem; color:#1e40af; margin-top:3px;">Aggiornati</div>
                </div>
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#b45309; line-height:1;">7</div>
                    <div style="font-size:0.78rem; color:#92400e; margin-top:3px;">Da verificare</div>
                </div>
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#dc2626; line-height:1;">3</div>
                    <div style="font-size:0.78rem; color:#b91c1c; margin-top:3px;">Errori</div>
                </div>
                <div style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px;">
                    <div style="font-size:1.6rem; font-weight:700; color:#475569; line-height:1;">180</div>
                    <div style="font-size:0.78rem; color:#64748b; margin-top:3px;">Totale (neutro)</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Pulsanti per funzione</x-slot>
            <x-slot name="description">Convenzione colore-per-funzione — non per stato</x-slot>

            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <x-filament::button color="success" icon="heroicon-m-check">Salva dati (verde)</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-x-mark">Annulla (grigio)</x-filament::button>
                <x-filament::button color="danger" icon="heroicon-m-trash">Elimina (rosso)</x-filament::button>
                <x-filament::button color="warning" icon="heroicon-m-exclamation-triangle">Attenzione (giallo)</x-filament::button>
                <x-filament::button color="info" icon="heroicon-m-arrow-top-right-on-square">Apri risorsa (blu)</x-filament::button>
                <x-filament::button disabled icon="heroicon-m-bolt">Operazione immediata (rosa — non ancora registrato in Filament)</x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Notifiche (toast)</x-slot>

            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <x-filament::button color="success" wire:click="notificaDemo('success')">Successo</x-filament::button>
                <x-filament::button color="danger" wire:click="notificaDemo('danger')">Errore</x-filament::button>
                <x-filament::button color="warning" wire:click="notificaDemo('warning')">Attenzione</x-filament::button>
                <x-filament::button color="info" wire:click="notificaDemo('info')">Info</x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Tabella</x-slot>
            <x-slot name="description">Azioni BeforeColumns, pericolose raggruppate a destra (stesso pattern di Soci)</x-slot>

            {{ $this->table }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Form</x-slot>

            {{ $this->form }}

            <div style="display:flex; gap:10px; margin-top:16px;">
                <x-filament::button color="success" wire:click="salvaDemo" icon="heroicon-m-check">Salva (demo)</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-x-mark">Annulla</x-filament::button>
            </div>
        </x-filament::section>

        <p>
            <a href="{{ \App\Filament\Resources\PrototipoSubNavResource::getUrl('index') }}" style="color:#2563eb; font-size:0.85rem;">&larr; Torna all'elenco eventi (prototipo)</a>
        </p>

    </div>
</x-filament-panels::page>
