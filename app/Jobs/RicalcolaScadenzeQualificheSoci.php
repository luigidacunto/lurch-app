<?php

namespace App\Jobs;

use App\Models\QualificaSocio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RicalcolaScadenzeQualificheSoci implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int  $qualificaId,
        private readonly ?int $comitatoRegionaleId = null
    ) {}

    public function handle(): void
    {
        $query = QualificaSocio::with(['catalogoCorso.validitaRegionali', 'socio'])
            ->where('catalogo_corso_id', $this->qualificaId);

        if ($this->comitatoRegionaleId !== null) {
            $query->whereHas('socio', fn ($q) =>
                $q->where('comitato_regionale_id', $this->comitatoRegionaleId)
            );
        }

        $query->chunkById(200, function ($records) {
            foreach ($records as $qs) {
                $qs->aggiornaScadenzaCalcolata();
            }
        });
    }
}
