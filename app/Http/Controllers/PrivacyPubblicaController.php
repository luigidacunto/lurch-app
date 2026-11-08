<?php

namespace App\Http\Controllers;

use App\Models\PrivacyTemplate;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\View\View;

class PrivacyPubblicaController extends Controller
{
    public function show(): View
    {
        $template = PrivacyTemplate::risolvi('informativa_dati', null);

        $testo = $template
            ? PrivacyPlaceholderResolver::sostituisci($template->testo, null)
            : null;

        return view('privacy.show', [
            'blocchi'  => $testo ? $this->analizzaBlocchi($testo) : [],
            'pdf'      => $template?->pdf_path,
            'versione' => $template?->versione ?? 1,
        ]);
    }

    /**
     * Divide il testo in blocchi titolo/paragrafo per generare le ancore #punto-N —
     * il testo resta un campo libero nel pannello, senza dover editare HTML a mano.
     */
    private function analizzaBlocchi(string $testo): array
    {
        $blocchi = [];

        foreach (preg_split('/\n{2,}/', trim($testo)) as $paragrafo) {
            $righe = explode("\n", trim($paragrafo));
            $prima = $righe[0];

            if (preg_match('/^(\d+(?:\.\d+)?)\.?\s+(.+)$/u', $prima, $m)) {
                $blocchi[] = ['tipo' => 'titolo', 'id' => 'punto-' . $m[1], 'testo' => $prima];

                $resto = trim(implode("\n", array_slice($righe, 1)));
                if ($resto !== '') {
                    $blocchi[] = ['tipo' => 'testo', 'testo' => $resto];
                }
            } else {
                $blocchi[] = ['tipo' => 'testo', 'testo' => $paragrafo];
            }
        }

        return $blocchi;
    }
}
