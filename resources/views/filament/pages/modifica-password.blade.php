<x-filament-panels::page>
    <div style="max-width: 480px;">
        <x-filament::section>
            <form wire:submit="salva">
                {{ $this->form }}

                <div style="margin-top: 2rem;">
                    <x-filament::button type="submit">
                        Aggiorna password
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
