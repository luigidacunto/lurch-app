<?php

namespace App\Exports;

use App\Models\Graduatoria;
use App\Models\GraduatoriaCandidato;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GraduatoriaCandidatiExport implements WithMultipleSheets
{
    public function __construct(public Graduatoria $graduatoria) {}

    public function sheets(): array
    {
        return [
            new class($this->graduatoria) implements FromArray, WithTitle, WithStyles, ShouldAutoSize {
                /** Numeri di riga (1-based, foglio) dei candidati tecnicamente ammessi, da colorare in styles(). */
                private array $righeAmmessi = [];

                public function __construct(private Graduatoria $graduatoria) {}

                public function title(): string
                {
                    return 'Graduatoria';
                }

                public function array(): array
                {
                    $g = $this->graduatoria;

                    $rows = [
                        [$g->corso ? $g->titolo . ' · ' . $g->corso->nome : $g->titolo],
                        ['Generato il ' . now()->format('d/m/Y H:i')],
                        [''], // riga vuota di separazione — un array vuoto [] viene "saltato" da FromArray e sfasa tutte le righe successive
                        ['Pos.', 'Cognome', 'Nome', 'Codice fiscale', 'Comitato', 'Tipo', 'Email', 'Telefono', 'Punteggio'],
                    ];

                    foreach ($g->candidatiClassificati() as $i => $c) {
                        /** @var GraduatoriaCandidato $c */
                        $rows[] = [
                            $c->posizione_classifica ?? ($i + 1),
                            $c->cognome,
                            $c->nome,
                            $c->codice_fiscale,
                            $c->comitatoSocio() ?? ($c->comitatoLocale?->nome ?? '—'),
                            $c->tipoSocio() ?? 'Anagrafica non collegata a Socio',
                            $c->email,
                            $c->telefono ?: '—',
                            $c->punteggio_finale !== null ? (float) $c->punteggio_finale : '—',
                        ];

                        if ($c->ammesso) {
                            $this->righeAmmessi[] = count($rows);
                        }
                    }

                    return $rows;
                }

                public function styles(Worksheet $sheet)
                {
                    // Titolo/sottotitolo occupano solo la cella A senza merge: ShouldAutoSize
                    // calcola la larghezza colonna A sul contenuto più corto/frequente (i numeri
                    // di posizione), non sul testo lungo del titolo, quindi la colonna A risulta
                    // stretta e il titolo appare "sballato" invece di allinearsi alla tabella
                    // sotto. Merge su tutta la larghezza della tabella (9 colonne, A:I) risolve
                    // alla radice.
                    $sheet->mergeCells('A1:I1');
                    $sheet->mergeCells('A2:I2');

                    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                    $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('6b7280');

                    $sheet->getStyle('A4:I4')->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
                    ]);

                    // Colpo d'occhio sui posti coperti: righe dei candidati tecnicamente
                    // ammessi (calcolaClassifica(), primi N posti disponibili) colorate di
                    // verde chiaro, come nella griglia del pannello.
                    foreach ($this->righeAmmessi as $riga) {
                        $sheet->getStyle("A{$riga}:I{$riga}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'd1fae5']],
                        ]);
                    }

                    return [];
                }
            },

            new class($this->graduatoria) implements FromArray, WithTitle, WithStyles, ShouldAutoSize {
                public function __construct(private Graduatoria $graduatoria) {}

                public function title(): string
                {
                    return 'Esclusi';
                }

                public function array(): array
                {
                    $rows = [
                        ['Cognome', 'Nome', 'Codice fiscale', 'Comitato', 'Tipo', 'Email', 'Telefono', 'Motivo esclusione'],
                    ];

                    foreach ($this->graduatoria->candidatiEsclusiConMotivo() as $r) {
                        /** @var GraduatoriaCandidato $c */
                        $c = $r['candidato'];
                        $rows[] = [
                            $c->cognome,
                            $c->nome,
                            $c->codice_fiscale,
                            $c->comitatoSocio() ?? ($c->comitatoLocale?->nome ?? '—'),
                            $c->tipoSocio() ?? 'Anagrafica non collegata a Socio',
                            $c->email,
                            $c->telefono ?: '—',
                            $r['motivo'],
                        ];
                    }

                    return $rows;
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:H1')->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '991b1b']],
                    ]);

                    return [];
                }
            },
        ];
    }
}
