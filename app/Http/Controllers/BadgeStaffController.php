<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\BadgeConfigResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BadgeStaffController extends Controller
{
    public function __invoke(Evento $evento)
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();

        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin()      && $evento->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento->comitato_locale_id   === $user->comitato_locale_id)
            || ($user->isOperatore()  && $evento->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

        $evento->load('operatori');

        $iscrizioniStaff = $evento->iscrizioniStaff()
            ->with('partecipante')
            ->get()
            ->sortBy('partecipante.cognome');

        $badges = $iscrizioniStaff->map(function ($is) use ($evento) {
            $url = route('badge.portale', ['evento' => $evento->id, 'token' => $is->badge_token]);

            return [
                'nome'        => $is->partecipante->nome,
                'cognome'     => $is->partecipante->cognome,
                'corso_ruolo' => 'Staff',
                'qr_src'      => 'data:image/svg+xml;base64,' . base64_encode(
                    QrCode::format('svg')->size(300)->errorCorrection('M')->generate($url)
                ),
            ];
        });

        $sfondoBase64 = BadgeConfigResolver::sfondoBase64($evento);
        $config       = BadgeConfigResolver::resolveConfig($evento);

        $pdf = Pdf::loadView('pdf.badge', [
            'badgeChunks' => $badges->chunk(6),
            'sfondo'      => $sfondoBase64,
            'config'      => $config,
            'nomeEvento'  => $evento->nome,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('badge-staff-' . $this->normalizzaNome($evento->nome) . '.pdf');
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
