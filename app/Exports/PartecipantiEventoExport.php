<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PartecipantiEventoExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Cognome',
            'Nome',
            'Codice Fiscale',
            'Email',
            'Telefono',
            'Comitato Regionale',
            'Comitato Locale / Sottosede',
            'Corsi (protocollo)',
            'Tipo partecipazione',
        ];
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
            'A' => 22,
            'B' => 22,
            'C' => 18,
            'D' => 30,
            'E' => 16,
            'F' => 28,
            'G' => 32,
            'H' => 22,
            'I' => 18,
        ];
    }
}
