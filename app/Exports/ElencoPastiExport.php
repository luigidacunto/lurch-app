<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Elenco nominativo dei pasti dell'evento: un foglio per pasto con tutte le persone previste
 * (anche senza esigenze) e, per chi ne ha, allergeni / diete / note.
 * $pasti: righePresenze di GrigliaPastiBuilder::build() già filtrate (solo pasti con persone).
 */
class ElencoPastiExport implements WithMultipleSheets
{
    public function __construct(private readonly array $pasti) {}

    public function sheets(): array
    {
        return array_map(fn ($pasto) => new class($pasto) implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize {
            public function __construct(private readonly array $pasto) {}

            public function array(): array
            {
                return array_map(fn ($p) => [
                    $p['nome'],
                    $p['cf'],
                    implode(', ', $p['allergeni']),
                    implode(', ', $p['diete']),
                    $p['note'] ?? '',
                ], $this->pasto['persone']);
            }

            public function headings(): array
            {
                return ['Cognome e nome', 'Codice Fiscale', 'Allergeni', 'Diete', 'Note'];
            }

            // Titolo foglio: max 31 caratteri, es. "Pranzo 14-11-2026"
            public function title(): string
            {
                return ucfirst($this->pasto['tipo']) . ' ' . Carbon::parse($this->pasto['data'])->format('d-m-Y');
            }

            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F3F4F6']]]];
            }
        }, $this->pasti);
    }
}
