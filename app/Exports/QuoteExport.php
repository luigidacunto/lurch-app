<?php

namespace App\Exports;

use App\Models\Corso;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuoteExport implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    private const HEADER_ROW   = 4;
    private const FIRST_DATA   = 5;

    public function __construct(public Corso $corso) {}

    public function title(): string
    {
        return 'Quote';
    }

    /** Righe extra prepende in testa (banner "corso annullato"): sposta header/dati in basso. */
    private function offset(): int
    {
        return $this->corso->isAnnullato() ? 1 : 0;
    }

    public function array(): array
    {
        $corso      = $this->corso;
        $evento     = $corso->evento;
        $iscrizioni = $corso->iscrizioni->sortBy('partecipante.cognome');
        $quota      = $corso->quota_partecipazione;

        $attive         = $iscrizioni->where('stato', '!=', 'ritirato');
        $nRitirati      = $iscrizioni->where('stato', 'ritirato')->count();
        $totale         = $iscrizioni->count();
        $conRicevuta    = $iscrizioni->filter(fn($i) => $i->rispostaPreregistrazione?->ricevuta_path)->count();
        $verificati     = $iscrizioni->filter(fn($i) => $i->rispostaPreregistrazione?->pagamento_verificato)->count();
        $mancanti       = $attive->filter(fn($i) => ! $i->rispostaPreregistrazione?->ricevuta_path)->count();
        $totaleRicevuto = $quota ? $quota * $conRicevuta : null;

        $rows = [];

        if ($corso->isAnnullato()) {
            $rows[] = ['⚠ CORSO ANNULLATO', '', '', '', '', ''];
        }

        $rows[] = ['Corso:',  $corso->nome,          '', '', 'Quota partecipazione:', $quota ? '€ ' . number_format($quota, 2, ',', '.') : '—'];
        $rows[] = ['Evento:', $evento?->nome ?? '—', '', '', 'N. protocollo:',        $corso->numero_protocollo ?? '—'];
        $rows[] = [
            'Totale: ' . $totale,
            'Pagamenti: ' . $conRicevuta,
            'Verificati: ' . $verificati,
            'Ritirati: ' . $nRitirati,
            'Mancanti: ' . $mancanti,
            $totaleRicevuto ? 'Tot. ricevuto: € ' . number_format($totaleRicevuto, 2, ',', '.') : '',
        ];
        $rows[] = ['NR', 'COGNOME NOME DISCENTE', 'TOTALE QUOTA PAGATA', 'DATA PAGAMENTO', 'COMITATO', 'STATO'];

        $nr = 0;
        foreach ($iscrizioni->values() as $iscrizione) {
            $p             = $iscrizione->partecipante;
            $risposta      = $iscrizione->rispostaPreregistrazione;
            $ricevuta      = $risposta?->ricevuta_path;
            $verificato    = $risposta?->pagamento_verificato ?? false;
            $dataPagamento = $risposta?->data_pagamento;
            $ritirato      = $iscrizione->isRitirato();

            if (! $ritirato) $nr++;

            $stato = match (true) {
                $ritirato   => 'Ritirato',
                $verificato => 'Verificato',
                (bool)$ricevuta => 'Non verificata',
                default     => 'Mancante',
            };

            $rows[] = [
                $ritirato ? '—' : $nr,
                strtoupper($p?->cognome) . ' ' . $p?->nome,
                ($ritirato || ! $quota) ? '—' : '€ ' . number_format($quota, 2, ',', '.'),
                $dataPagamento ? $dataPagamento->format('d/m/Y') : '',
                $p?->comitatoLocale?->nome ?? $p?->comitatoRegionale?->nome ?? '—',
                $stato,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $dataCount = $this->corso->iscrizioni->count();
        $offset    = $this->offset();
        $headerRow = self::HEADER_ROW + $offset;
        $firstData = self::FIRST_DATA + $offset;
        $lastRow   = $firstData + max($dataCount - 1, 0);

        if ($this->corso->isAnnullato()) {
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->applyFromArray([
                'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '991b1b']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fee2e2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(24);
        }

        // Info block background
        $sheet->getStyle('A' . (1 + $offset) . ':F' . (3 + $offset))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('f1f5f9');

        // Label bold + dark blue
        foreach (['A' . (1 + $offset), 'A' . (2 + $offset), 'E' . (1 + $offset), 'E' . (2 + $offset)] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('1e3a5f');
        }

        // Stats row — bold, larger, colored per significato (allineato alla web view)
        $statsRow = 3 + $offset;
        $sheet->getStyle("A{$statsRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('374151'); // Totale — grigio scuro
        $sheet->getStyle("B{$statsRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('7c3aed'); // Pagamenti — viola
        $sheet->getStyle("C{$statsRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('15803d'); // Verificati — verde
        $nRitiratiVal = (int) preg_replace('/\D/', '', (string) $sheet->getCell("D{$statsRow}")->getValue());
        $sheet->getStyle("D{$statsRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB($nRitiratiVal > 0 ? 'ea580c' : '9ca3af'); // Ritirati
        $sheet->getStyle("E{$statsRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('b45309'); // Mancanti — ambra
        $sheet->getStyle("F{$statsRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1e3a5f'); // Totale ricevuto
        $sheet->getRowDimension($statsRow)->setRowHeight(20);

        // Column header row
        $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // Data rows
        for ($row = $firstData; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:F{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('f8fafc');
            }

            $stato = $sheet->getCell("F{$row}")->getValue();
            $ritirato = ($stato === 'Ritirato');

            if ($ritirato) {
                $sheet->getStyle("A{$row}:F{$row}")->getFont()->getColor()->setRGB('9ca3af');
                $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB('991b1b');
            } else {
                $color = match ($stato) {
                    'Verificato'     => '15803d',
                    'Non verificata' => '7c3aed',
                    default          => 'b45309',
                };
                $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB($color);
                $sheet->getStyle("F{$row}")->getFont()->setBold($stato === 'Verificato');
            }

            foreach (['A', 'C', 'D', 'F'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        // Border on table (headers + data)
        if ($dataCount > 0) {
            $sheet->getStyle('A' . $headerRow . ":F{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'e5e7eb']],
                ],
            ]);
        }

        return [];
    }
}
