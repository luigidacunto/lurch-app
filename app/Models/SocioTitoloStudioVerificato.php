<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verifica permanente di un titolo di studio per un socio (es. diploma di scuola
 * secondaria superiore), indipendente dalla singola candidatura in graduatoria —
 * una volta verificato per un dato `tipo`, resta valido per qualunque graduatoria
 * futura che richieda lo stesso tipo di titolo (vedi GraduatoriaCandidato::TIPO_DIPLOMA_SUPERIORE).
 */
class SocioTitoloStudioVerificato extends Model
{
    protected $table = 'socio_titoli_studio_verificati';

    protected $fillable = [
        'socio_id',
        'tipo',
        'verificato_at',
        'verificato_da_id',
    ];

    protected function casts(): array
    {
        return [
            'verificato_at' => 'datetime',
        ];
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function verificatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificato_da_id');
    }
}
