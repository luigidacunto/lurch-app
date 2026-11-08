<?php

namespace App\Exports;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Stessa struttura/stile di QualificheSociExport (righe filtri + header + dati), per
 *  uniformità delle tecniche di export nel pannello. */
class SociExport implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    private const TOTALE_COLONNE = 14;
    private const FIRST_DATA_ROW = 5;

    public function __construct(
        private Collection $soci,
        private ?ComitatoRegionale $regione = null,
        private ?ComitatoLocale $locale = null,
        private ?string $ruolo = null,
        private bool $includiEstensioni = false,
        private bool $includiDipendenti = false,
        private ?string $statoAssociativo = null, // 'dimessi'|'tutti'|null(=solo attivi, default)
        private ?string $cestinati = null, // 'con'|'solo'|null(=senza eliminati, default)
        private ?string $filtroGiovani = null,
    ) {
    }

    public function title(): string
    {
        return 'Soci';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = $this->buildFiltriRigaArea();
        $rows[] = $this->buildFiltriRigaExtra();
        $rows[] = $this->buildFiltriRigaTotale();
        $rows[] = $this->buildHeaderRow();

        $oggi = Carbon::today();

        foreach ($this->soci->values() as $index => $socio) {
            $rows[] = [
                $index + 1,
                $socio->cognome,
                $socio->nome,
                $socio->codice_fiscale,
                $socio->email ?: '—',
                $this->formatTelefono($socio),
                $socio->comitato_display ?? '—',
                $socio->comitatoLocaleEstensione?->nome ?? '—',
                $socio->comitatoLocaleDipendente?->nome ?? $socio->comitatoRegionaleDipendente?->nome ?? '—',
                $this->formatRuolo($socio->ruolo_associativo),
                $socio->data_nascita?->format('d/m/Y') ?? '—',
                $socio->data_nascita ? (int) $socio->data_nascita->diffInYears($oggi) : '—',
                $socio->volontario_da?->format('d/m/Y') ?? '—',
                $socio->dimesso_il?->format('d/m/Y') ?? '—',
            ];
        }

        return $rows;
    }

    private function formatTelefono($socio): string
    {
        if (! $socio->telefono) {
            return '—';
        }
        $prefisso = $socio->prefisso_telefono ?? '+39';
        return $prefisso . ' ' . preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1 $2 $3', $socio->telefono);
    }

    private function formatRuolo(?string $ruolo): string
    {
        return match ($ruolo) {
            'volontario'            => 'Volontario',
            'dipendente'            => 'Dipendente',
            'infermiere_volontarie' => 'Infermiere Volontarie CRI',
            'corpo_militare'        => 'Corpo Militare Volontario CRI',
            default                 => '—',
        };
    }

    private function buildFiltriRigaArea(): array
    {
        $row    = array_fill(0, self::TOTALE_COLONNE, '');
        $row[0] = 'Regione:';
        $row[1] = $this->regione?->nome ?? 'Tutte';
        $row[2] = 'Comitato Locale:';
        $row[3] = $this->locale?->nome ?? 'Tutti';
        $row[4] = 'Ruolo:';
        $row[5] = $this->ruolo ? $this->formatRuolo($this->ruolo) : 'Tutti';
        return $row;
    }

    private function buildFiltriRigaExtra(): array
    {
        $row    = array_fill(0, self::TOTALE_COLONNE, '');
        $row[0] = 'Stato associativo:';
        $row[1] = match ($this->statoAssociativo) {
            'dimessi' => 'Solo dimessi',
            'tutti'   => 'Attivi e dimessi',
            default   => 'Solo attivi',
        };
        $row[2] = 'Cestinati:';
        $row[3] = match ($this->cestinati) {
            'con'   => 'Con eliminati',
            'solo'  => 'Solo eliminati',
            default => 'Senza eliminati',
        };

        $inclusi = [];
        if ($this->includiEstensioni) $inclusi[] = 'estensioni';
        if ($this->includiDipendenti) $inclusi[] = 'dipendenti';
        if (! empty($inclusi)) {
            $row[4] = 'Inclusi:';
            $row[5] = implode(', ', $inclusi);
        }

        if ($this->filtroGiovani) {
            $row[6] = 'Giovani:';
            $row[7] = $this->filtroGiovani === 'attuale'
                ? 'solo età attuale < 32 anni'
                : 'solo entrati come giovani (< 32 anni all\'ingresso)';
        }
        return $row;
    }

    private function buildFiltriRigaTotale(): array
    {
        $row    = array_fill(0, self::TOTALE_COLONNE, '');
        $row[0] = 'Totale soci: ' . $this->soci->count();
        return $row;
    }

    private function buildHeaderRow(): array
    {
        return [
            'NR', 'COGNOME', 'NOME', 'CODICE FISCALE', 'EMAIL', 'TELEFONO',
            'COMITATO / SOTTOSEDE', 'ESTENSIONE', 'COMITATO LAVORO', 'RUOLO',
            'NASCITA', 'ETÀ', 'VOLONTARIO DAL', 'DIMESSO IL',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $dataCount     = $this->soci->count();
        $lastRow       = self::FIRST_DATA_ROW + max($dataCount - 1, 0);
        $lastColLetter = $this->colLetter(self::TOTALE_COLONNE);
        $headerRow     = self::FIRST_DATA_ROW - 1;

        $sheet->getStyle("A1:{$lastColLetter}3")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('f1f5f9');

        foreach (['A1', 'C1', 'E1', 'A2', 'C2', 'E2', 'G2'] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('1e3a5f');
        }

        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        for ($row = self::FIRST_DATA_ROW; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('f8fafc');
            }
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        if ($dataCount > 0) {
            $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'e5e7eb']],
                ],
            ]);
        }

        return [];
    }

    private function colLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $remainder = ($col - 1) % 26;
            $letter    = chr(65 + $remainder) . $letter;
            $col       = (int) (($col - 1) / 26);
        }
        return $letter;
    }
}
