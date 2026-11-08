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

        $user = auth()->user();

        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin()       && $evento->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento->comitato_locale_id    === $user->comitato_locale_id)
            || ($user->isOperatore()   && $evento->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

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
