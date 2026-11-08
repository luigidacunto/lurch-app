<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiscentiCorsoExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Cognome', 'Nome', 'Codice Fiscale', 'Data di nascita', 'Età', 'Comitato', 'Tipo', 'Telefono', 'Email'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F3F4F6']]]];
    }
}
