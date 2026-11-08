<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Guide pubbliche all'uso del portale (operatori / referenti locali).
 *
 * Sorgente: file Markdown in docs/guide-operatori/. Resi a runtime — si aggiornano
 * da soli ad ogni modifica del .md, nessun contenuto duplicato.
 */
class GuidaController extends Controller
{
    private function dir(): string
    {
        return base_path('docs/guide-operatori');
    }

    public function index()
    {
        return view('guide.index', ['guide' => $this->elenco()]);
    }

    public function show(string $slug)
    {
        return view('guide.show', $this->carica($slug) + ['pdf' => false]);
    }

    public function pdf(string $slug)
    {
        $html = view('guide.show', $this->carica($slug) + ['pdf' => true])->render();

        // dompdf non scarica immagini via URL: gli screenshot (/guide-assets/...) vanno
        // riscritti a path assoluto su disco. ponytail: str_replace secco, basta finché
        // stanno tutti sotto public/guide-assets.
        $html = str_replace(
            'src="/guide-assets/',
            'src="' . public_path('guide-assets') . DIRECTORY_SEPARATOR,
            $html,
        );

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="guida-' . $slug . '.pdf"',
        ]);
    }

    /** @return array{slug:string, titolo:string, html:string} */
    private function carica(string $slug): array
    {
        abort_unless((bool) preg_match('/^[a-z0-9-]+$/', $slug), 404);

        $file = $this->dir() . '/' . $slug . '.md';
        abort_unless(File::exists($file), 404);

        $md = File::get($file);

        return [
            'slug'   => $slug,
            'titolo' => $this->titolo($md) ?? Str::headline($slug),
            'html'   => Str::markdown($md, ['html_input' => 'allow', 'allow_unsafe_links' => false]),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array{slug:string, titolo:string, descrizione:?string}> */
    private function elenco()
    {
        if (! File::isDirectory($this->dir())) {
            return collect();
        }

        return collect(File::files($this->dir()))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->map(function ($f) {
                $md = File::get($f->getPathname());

                return [
                    'slug'        => $f->getFilenameWithoutExtension(),
                    'titolo'      => $this->titolo($md) ?? Str::headline($f->getFilenameWithoutExtension()),
                    'descrizione' => $this->descrizione($md),
                ];
            })
            ->sortBy('titolo')
            ->values();
    }

    private function titolo(string $md): ?string
    {
        return preg_match('/^#\s+(.+)$/m', $md, $m) ? trim($m[1]) : null;
    }

    /** Primo blockquote ("> ...") = descrizione breve mostrata nell'indice. */
    private function descrizione(string $md): ?string
    {
        return preg_match('/^>\s+(.+)$/m', $md, $m) ? trim($m[1]) : null;
    }
}
