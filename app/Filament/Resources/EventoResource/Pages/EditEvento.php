<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditEvento extends EditRecord
{
    protected static string $resource = EventoResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->nome;
    }

    /**
     * Inserisce "Gestione evento" come livello intermedio del breadcrumb (Eventi → Gestione
     * evento → Modifica) — sostituisce il pulsante header "Gestione evento", ora rimosso.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();
        $breadcrumbs[EventoResource::getUrl('gestione', ['record' => $this->record])] = $this->record->nome;
        $breadcrumbs[] = $this->getBreadcrumb();

        return $breadcrumbs;
    }

    /**
     * Assicura che badge_config abbia i valori di default se il campo è null nel DB,
     * così i TextInput nel form trovano sempre valori numerici da mostrare.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $stored = $data['badge_config'] ?? [];

        $data['badge_config'] = array_replace_recursive([
            'titolo_evento'  => ['attivo' => false, 'x' => 4, 'y' => 25, 'font_size' => 14],
            'nome_cognome'   => ['x' => 5,  'y' => 50, 'font_size' => 14],
            'corso_ruolo'    => ['x' => 5,  'y' => 40, 'font_size' => 9],
            'qr'             => ['x' => 16, 'y' => 66, 'size'      => 28],
            'qr_checkin_in_email' => false,
        ], is_array($stored) ? $stored : []);

        return $data;
    }

    /**
     * Dopo il salvataggio processa i file caricati nei FileUpload temporanei
     * e li aggiunge alle media collection tramite Spatie MediaLibrary.
     */
    protected function afterSave(): void
    {
        $nuoviDocumenti = $this->data['documenti_nuovi'] ?? [];

        foreach ($nuoviDocumenti as $relativePath) {
            $fullPath = Storage::disk('public')->path($relativePath);

            if (! file_exists($fullPath)) continue;

            $nomeSanitizzato = $this->sanitizzaNomeFile(basename($relativePath));

            $this->record
                ->addMedia($fullPath)
                ->usingFileName($nomeSanitizzato)
                ->toMediaCollection('documenti');
        }

        // FileUpload in Filament 5 restituisce array associativo {uuid => path}, non indicizzato
        $nuovoSfondoRaw = $this->data['badge_sfondo_nuovo'] ?? null;
        $nuovoSfondo    = is_array($nuovoSfondoRaw) ? (array_values($nuovoSfondoRaw)[0] ?? null) : $nuovoSfondoRaw;

        if ($nuovoSfondo) {
            $fullPath = Storage::disk('public')->path($nuovoSfondo);

            if (file_exists($fullPath)) {
                $this->record
                    ->addMedia($fullPath)
                    ->toMediaCollection('badge_sfondo');
            }
        }

        $nuovaIntestazionRaw = $this->data['intestazione_app_nuova'] ?? null;
        $nuovaIntestazione   = is_array($nuovaIntestazionRaw) ? (array_values($nuovaIntestazionRaw)[0] ?? null) : $nuovaIntestazionRaw;

        if ($nuovaIntestazione) {
            $fullPath = Storage::disk('public')->path($nuovaIntestazione);

            if (file_exists($fullPath)) {
                $this->record
                    ->addMedia($fullPath)
                    ->toMediaCollection('intestazione_app');
            }
        }
    }

    public function eliminaMedia(string $collection): void
    {
        $allowed = ['intestazione_app', 'badge_sfondo'];

        if (! in_array($collection, $allowed)) return;

        $media = $this->record->getFirstMedia($collection);
        if (! $media) return;

        $media->delete();
        $this->record->refresh();
        $this->fillForm();

        $labels = [
            'intestazione_app' => 'Immagine di intestazione rimossa.',
            'badge_sfondo'     => 'Sfondo badge rimosso.',
        ];

        Notification::make()
            ->title($labels[$collection])
            ->success()
            ->send();
    }

    public function eliminaDocumento(int $mediaId): void
    {
        $media = $this->record->getMedia('documenti')->firstWhere('id', $mediaId);

        if (! $media) return;

        $nome = $media->file_name;
        $media->delete();
        $this->record->refresh();
        $this->fillForm();

        Notification::make()
            ->title('Documento eliminato')
            ->body("«{$nome}» è stato rimosso dall'evento.")
            ->success()
            ->send();
    }

    private function sanitizzaNomeFile(string $nome): string
    {
        $ext  = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $base = \Illuminate\Support\Str::slug(pathinfo($nome, PATHINFO_FILENAME)) ?: 'documento';
        return $base . '-' . now()->timestamp . ($ext ? '.' . $ext : '');
    }
}
