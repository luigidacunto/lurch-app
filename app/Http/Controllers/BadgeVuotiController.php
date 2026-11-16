<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\BadgeConfigResolver;
use Barryvdh\DomPDF\Facade\Pdf;

class BadgeVuotiController extends Controller
{
    public function __invoke(Evento $evento)
    {
        abort_unless(auth()->check(), 403);

        // Stessa regola della pagina Gestione evento da cui parte il link.
        abort_unless(\App\Filament\Resources\EventoResource::canEdit($evento), 403);

        $evento->load('operatori');

        $sfondoBase64 = BadgeConfigResolver::sfondoBase64($evento);
        $config       = BadgeConfigResolver::resolveConfig($evento);

        $pdf = Pdf::loadView('pdf.badge-vuoti', [
            'sfondo'     => $sfondoBase64,
            'config'     => $config,
            'nomeEvento' => $evento->nome,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('badge-vuoti-' . $this->normalizzaNome($evento->nome) . '.pdf');
    }

    private function normalizzaNome(string $testo): string
    {
        return (string) str($testo)
            ->ascii()
            ->lower()
            ->replace('/', '-')
            ->replace(' ', '_')
            ->replaceMatches('/[^a-z0-9_\-]+/', '');
    }
}
