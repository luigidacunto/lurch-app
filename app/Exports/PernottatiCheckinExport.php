<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PernottatiCheckinExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        return array_map(
            fn ($idx, $r) => [
                $idx + 1,
                $r['cognome'],
                $r['nome'],
                $r['cf'],
                $r['data_nascita'],
                $r['luogo_nascita'],
                $r['pernotti'],
            ],
            array_keys($this->rows),
            $this->rows
        );
    }

    public function headings(): array
    {
        return ['#', 'Cognome', 'Nome', 'Codice fiscale', 'Data di nascita', 'Luogo di nascita', 'N. pernotti (check-in)'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F3F4F6']]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 20,
            'D' => 18,
            'E' => 16,
            'F' => 22,
            'G' => 22,
        ];
    }
}
