<?php

namespace App\Services;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\ComitatoSottosede;

/**
 * Risolve una stringa GAIA in {locale, sottosede}.
 *
 * Formati supportati:
 *   "Litorale Pisano"                   → match su comitato_sottosedi.nome
 *   "Comitato di Pisa"                  → match su comitati_locali.nome
 *   "Comitato di Pisa: Litorale Pisano" → split su ":", match locale + sottosede
 *
 * Gli indici vengono caricati una volta sola (lazy) e riutilizzati.
 */
class ComitatoResolver
{
    /** @var array<string, ComitatoLocale> */
    private array $byNome = [];

    /** @var array<string, ComitatoSottosede> */
    private array $bySottosede = [];

    /** @var array<string, ComitatoRegionale> */
    private array $byNomeRegionale = [];

    private bool $loaded = false;

    public function buildIndici(): void
    {
        if ($this->loaded) {
            return;
        }

        $locali = ComitatoLocale::with('sottosedi')->whereNull('deleted_at')->get();

        foreach ($locali as $locale) {
            $this->byNome[$this->normalizza($locale->nome)] = $locale;

            foreach ($locale->sottosedi as $sub) {
                $chiave = $this->normalizza($sub->nome);
                if ($chiave !== '') {
                    $this->bySottosede[$chiave] = $sub;
                }
            }
        }

        foreach (ComitatoRegionale::whereNull('deleted_at')->get() as $regionale) {
            $this->byNomeRegionale[$this->normalizza($regionale->nome)] = $regionale;
        }

        $this->loaded = true;
    }

    /**
     * Risolve una stringa GAIA come Comitato Regionale (es. "Comitato Regionale Toscana").
     * Usato per i dipendenti assunti direttamente dal regionale, senza comitato locale.
     */
    public function risolviRegionale(string $valore): ?ComitatoRegionale
    {
        $this->buildIndici();

        $chiave = $this->normalizza(trim($valore));
        if ($chiave === '') {
            return null;
        }

        return $this->byNomeRegionale[$chiave] ?? null;
    }

    /**
     * @return array{locale: ComitatoLocale, sottosede: ?ComitatoSottosede}|null
     */
    public function risolvi(string $valore): ?array
    {
        $this->buildIndici();

        $valore = trim($valore);
        if ($valore === '') {
            return null;
        }

        // Formato "Comitato X: Sottosede Y"
        if (str_contains($valore, ':')) {
            [$parteLocale, $parteSub] = array_map('trim', explode(':', $valore, 2));

            $locale = $this->byNome[$this->normalizza($parteLocale)] ?? null;
            if (! $locale) {
                return null;
            }

            $sub = $this->bySottosede[$this->normalizza($parteSub)] ?? null;
            // Verifica che la sottosede appartenga al comitato trovato
            if ($sub && $sub->comitato_locale_id !== $locale->id) {
                $sub = null;
            }

            return ['locale' => $locale, 'sottosede' => $sub];
        }

        // Formato plain: prova prima come comitato locale, poi come sottosede
        $chiave = $this->normalizza($valore);

        if (isset($this->byNome[$chiave])) {
            return ['locale' => $this->byNome[$chiave], 'sottosede' => null];
        }

        if (isset($this->bySottosede[$chiave])) {
            $sub = $this->bySottosede[$chiave];
            return ['locale' => $sub->comitatoLocale, 'sottosede' => $sub];
        }

        return null;
    }

    public function normalizza(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        return preg_replace("/('\s+)/u", "'", $nome);
    }
}
