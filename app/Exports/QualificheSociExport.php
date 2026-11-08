<?php

namespace App\Exports;

use App\Models\CatalogoCorso;
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

class QualificheSociExport implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    private int $headerRows;
    private int $firstDataRow;
    private array $qualificheCodici = [];
    private array $qualificheIds    = [];
    private int $totaleColonne;

    public function __construct(
        private Collection $soci,
        private array $qualificheRichieste = [],
        private array $qualificheEscluse = [],
        private string $operatoreRichieste = 'and',
        private ?int $filtroLocale = null,
        private ?ComitatoRegionale $regione = null,
        private ?ComitatoLocale $locale = null,
        private bool $includiEstensioni = false,
        private bool $includiDipendenti = false,
        private ?string $filtroGiovani = null,
    ) {
        if (! empty($this->qualificheRichieste)) {
            $corsi = CatalogoCorso::whereIn('id', $this->qualificheRichieste)->get()->keyBy('id');
            foreach ($this->qualificheRichieste as $id) {
                $this->qualificheCodici[$id] = $corsi[$id]?->codice ?? "#{$id}";
                $this->qualificheIds[$id]    = CatalogoCorso::idsConEquivalenti($id);
            }
        }

        // 3 righe filtri + 1 riga header colonne
        $this->headerRows   = 4;
        $this->firstDataRow = 5;

        // Colonne base: NR, COGNOME, NOME, CF, EMAIL, COMITATO/SOTTOSEDE, TIPO RELAZIONE, NASCITA, ETÀ, INGRESSO
        // + per ogni qualifica richiesta: CODICE, SCADENZA, STATO
        $this->totaleColonne = 10 + (count($this->qualificheRichieste) * 3);
    }

    public function title(): string
    {
        return 'Qualifiche';
    }

    public function array(): array
    {
        $rows = [];

        // --- Righe descrizione filtri ---
        $rows[] = $this->buildFiltriRigaQualifiche();
        $rows[] = $this->buildFiltriRigaArea();
        $rows[] = $this->buildFiltriRigaTotale();

        // --- Intestazione colonne ---
        $rows[] = $this->buildHeaderRow();

        // --- Righe dati ---
        $oggi = Carbon::today();

        foreach ($this->soci->values() as $index => $socio) {
            $row = [
                $index + 1,
                $socio->cognome,
                $socio->nome,
                $socio->codice_fiscale,
                $socio->email ?: '—',
                $socio->comitato_display,
                $this->tipoRelazione($socio),
                $socio->data_nascita?->format('d/m/Y') ?? '—',
                $socio->data_nascita ? (int) $socio->data_nascita->diffInYears($oggi) : '—',
                $socio->volontario_da?->format('d/m/Y') ?? '—',
            ];

            foreach ($this->qualificheRichieste as $qualId) {
                $ids = $this->qualificheIds[$qualId] ?? [];
                $sq  = $socio->qualificheSoci->first(fn ($sq) => in_array($sq->catalogo_corso_id, $ids));

                $codice   = $sq?->catalogoCorso?->codice ?? '—';
                $scadenza = $sq?->scadenza_calcolata;
                $stato    = $this->calcolaStatoLabel($sq, $socio->comitatoRegionaleEffettivoId(), $scadenza, $oggi);

                $row[] = $codice;
                $row[] = $scadenza ? $scadenza->format('d/m/Y') : '—';
                $row[] = $stato;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function buildFiltriRigaQualifiche(): array
    {
        $row = array_fill(0, $this->totaleColonne, '');

        if (! empty($this->qualificheRichieste)) {
            $codici   = implode(', ', array_values($this->qualificheCodici));
            $operText = $this->operatoreRichieste === 'or' ? 'almeno una tra' : 'tutte';
            $row[0]   = 'Qualifiche richieste:';
            $row[1]   = "{$operText}: {$codici}";
        } else {
            $row[0] = 'Qualifiche richieste:';
            $row[1] = 'nessuna';
        }

        if (! empty($this->qualificheEscluse)) {
            $escl   = CatalogoCorso::whereIn('id', $this->qualificheEscluse)->pluck('codice')->join(', ');
            $offset = (int) ($this->totaleColonne / 2);
            $row[$offset]     = 'Soci PRIVI DI:';
            $row[$offset + 1] = $escl;
        }

        return $row;
    }

    private function buildFiltriRigaArea(): array
    {
        $row    = array_fill(0, $this->totaleColonne, '');
        $row[0] = 'Regione:';
        $row[1] = $this->regione?->nome ?? 'Tutte';
        $row[2] = 'Comitato Locale:';
        $row[3] = $this->locale?->nome ?? 'Tutti';

        $flags = [];
        if ($this->includiEstensioni) $flags[] = 'estensioni';
        if ($this->includiDipendenti) $flags[] = 'dipendenti';
        if (! empty($flags)) {
            $row[4] = 'Inclusi:';
            $row[5] = implode(', ', $flags);
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
        $row    = array_fill(0, $this->totaleColonne, '');
        $row[0] = 'Totale soci: ' . $this->soci->count();
        return $row;
    }

    private function buildHeaderRow(): array
    {
        $headers = ['NR', 'COGNOME', 'NOME', 'CODICE FISCALE', 'EMAIL', 'COMITATO / SOTTOSEDE', 'TIPO RELAZIONE', 'NASCITA', 'ETÀ', 'INGRESSO'];

        foreach ($this->qualificheCodici as $codice) {
            $headers[] = "QUALIFICA ({$codice})";
            $headers[] = "SCADENZA ({$codice})";
            $headers[] = "STATO ({$codice})";
        }

        return $headers;
    }

    private function tipoRelazione($socio): string
    {
        if (! $this->filtroLocale) {
            return '—';
        }
        if ($socio->comitato_locale_dipendente_id === $this->filtroLocale) {
            return 'Dipendente';
        }
        if ($socio->comitato_locale_estensione_id === $this->filtroLocale) {
            return 'In estensione';
        }
        return 'Volontario';
    }

    private function calcolaStatoLabel($sq, ?int $regioneId, $scadenza, Carbon $oggi): string
    {
        if (! $sq || ! $scadenza) {
            return '—';
        }

        $regola = $sq->catalogoCorso->scadenzaPerRegione($regioneId);

        if ($regola['tipo'] === 'mesi_esatti') {
            if ($scadenza->lt($oggi)) return 'Scaduta';
            return $oggi->diffInMonths($scadenza) > 6 ? 'Valida' : 'In scadenza';
        }

        if ($regola['tipo'] === 'anno_solare') {
            $diff = $scadenza->year - $oggi->year;
            if ($diff > 0) return 'Valida';
            if ($diff === 0) return 'In scadenza';
            return 'Scaduta';
        }

        return '—';
    }

    public function styles(Worksheet $sheet): array
    {
        $dataCount = $this->soci->count();
        $lastRow   = $this->firstDataRow + max($dataCount - 1, 0);
        $lastCol   = $this->totaleColonne;
        $lastColLetter = $this->colLetter($lastCol);

        // Sfondo righe filtri
        $sheet->getStyle("A1:{$lastColLetter}3")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('f1f5f9');

        // Label filtri in grassetto
        foreach (['A1', 'A2', 'A3'] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('1e3a5f');
        }
        $sheet->getStyle('C2')->getFont()->setBold(true)->getColor()->setRGB('1e3a5f');

        $offset = (int) ($this->totaleColonne / 2);
        $offsetLetter = $this->colLetter($offset + 1);
        $sheet->getStyle("{$offsetLetter}1")->getFont()->setBold(true)->getColor()->setRGB('1e3a5f');

        $sheet->getStyle('A3')->getFont()->setSize(11);
        $sheet->getRowDimension(3)->setRowHeight(20);

        // Header colonne
        $headerRow = $this->firstDataRow - 1;
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        // Colonne qualifiche: raggruppa in sezioni da 3
        if (! empty($this->qualificheRichieste)) {
            $sectionColors = ['e0e7ff', 'dcfce7', 'fef9c3', 'fee2e2', 'f3e8ff', 'ffedd5'];
            $i = 0;
            foreach ($this->qualificheRichieste as $qualId) {
                $colStart = 11 + $i * 3;
                $colEnd   = $colStart + 2;
                $color    = $sectionColors[$i % count($sectionColors)];
                $startL   = $this->colLetter($colStart);
                $endL     = $this->colLetter($colEnd);
                $sheet->getStyle("{$startL}{$headerRow}:{$endL}{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($color);
                $sheet->getStyle("{$startL}{$headerRow}:{$endL}{$headerRow}")->getFont()
                    ->getColor()->setRGB('1e293b');
                $i++;
            }
        }

        // Righe dati
        for ($row = $this->firstDataRow; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('f8fafc');
            }

            // Colora stati scadenza per ogni blocco qualifica
            $i = 0;
            foreach ($this->qualificheRichieste as $qualId) {
                $statoCol = $this->colLetter(13 + $i * 3);
                $stato    = $sheet->getCell("{$statoCol}{$row}")->getValue();
                $color    = match ($stato) {
                    'Valida'       => '15803d',
                    'In scadenza'  => 'b45309',
                    'Scaduta'      => 'dc2626',
                    default        => '6b7280',
                };
                $sheet->getStyle("{$statoCol}{$row}")->getFont()->getColor()->setRGB($color);
                $sheet->getStyle("{$statoCol}{$row}")->getFont()->setBold($stato === 'Valida');
                $i++;
            }

            // Centra colonne NR, TIPO RELAZIONE, NASCITA, ETÀ, INGRESSO
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Bordi tabella dati
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
