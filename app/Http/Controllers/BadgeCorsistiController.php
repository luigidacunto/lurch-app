<?php

namespace App\Http\Controllers;

use App\Models\Corso;
use App\Services\BadgeConfigResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BadgeCorsistiController extends Controller
{
    public function __invoke(Corso $corso)
    {
        abort_unless(auth()->check(), 403);

        $user  = auth()->user();
        $evento = $corso->evento;

        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin()       && $evento?->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento?->comitato_locale_id    === $user->comitato_locale_id)
            || ($user->isOperatore()   && $evento?->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

        $corso->load('iscrizioni.partecipante', 'evento.operatori');

        $badges = collect();
        foreach ($corso->iscrizioni->where('stato', '!=', 'ritirato')->sortBy('partecipante.cognome') as $iscrizione) {
            $url = route('badge.portale', ['evento' => $corso->evento_id, 'token' => $iscrizione->badge_token]);

            $badges->push([
                'nome'        => $iscrizione->partecipante->nome,
                'cognome'     => $iscrizione->partecipante->cognome,
                'corso_ruolo' => $corso->numero_protocollo ?? $corso->nome,
                'qr_src'      => 'data:image/svg+xml;base64,' . base64_encode(
                    QrCode::format('svg')->size(300)->errorCorrection('M')->generate($url)
                ),
            ]);
        }

        $sfondoBase64 = BadgeConfigResolver::sfondoBase64($evento);
        $config       = BadgeConfigResolver::resolveConfig($evento);

        $pdf = Pdf::loadView('pdf.badge', [
            'badgeChunks' => $badges->chunk(6),
            'sfondo'      => $sfondoBase64,
            'config'      => $config,
            'nomeEvento'  => $evento?->nome,
        ])->setPaper('a4', 'portrait');

        $nomeEventoFile = $this->normalizzaNome($evento?->nome ?? '');
        $protocollo     = $this->normalizzaNome($corso->numero_protocollo ?? $corso->nome);

        return $pdf->stream("badge-iscritti-{$nomeEventoFile}-{$protocollo}.pdf");
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
