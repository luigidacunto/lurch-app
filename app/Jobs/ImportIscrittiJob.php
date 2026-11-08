<?php

namespace App\Jobs;

use App\Imports\IscrittiImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportIscrittiJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 300; // 5 minuti — file grandi

    /**
     * @param string $diskPath  Percorso relativo del file su disco 'local'
     * @param int    $corsoId   ID del corso destinazione
     * @param int    $userId    ID dell'operatore che ha avviato l'import
     */
    public function __construct(
        private readonly string $diskPath,
        private readonly int    $corsoId,
        private readonly int    $userId,
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::disk('local')->path($this->diskPath);

        $import = new IscrittiImport($this->corsoId);
        Excel::import($import, $fullPath);

        // Rimuove il file temporaneo dopo l'import
        Storage::disk('local')->delete($this->diskPath);

        Log::info("Import iscritti completato.", [
            'corso_id'   => $this->corsoId,
            'user_id'    => $this->userId,
            'creati'     => $import->creati,
            'aggiornati' => $import->aggiornati,
            'errori'     => count($import->errori),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Import iscritti fallito.", [
            'corso_id' => $this->corsoId,
            'user_id'  => $this->userId,
            'errore'   => $exception->getMessage(),
        ]);

        // Il file temp viene rimosso anche in caso di fallimento
        Storage::disk('local')->delete($this->diskPath);
    }
}
