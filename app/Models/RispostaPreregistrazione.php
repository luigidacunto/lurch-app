<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RispostaPreregistrazione extends Model
{
    protected $table = 'risposte_preregistrazione';

    protected $fillable = [
        'iscrizioneable_type',
        'iscrizioneable_id',
        'risposte',
        'privacy_accettata',
        'consenso_gaia_accettata',
        'consenso_salute_accettata',
        'foto_video_accettata',
        'compiled_at',
        'fonte_compilazione',
        'privacy_versione_accettata',
        'ricevuta_path',
        'nullaosta_path',
        'nullaosta_verificato',
        'nullaosta_verificato_at',
        'nullaosta_verificato_da_id',
        'pagamento_verificato',
        'pagamento_verificato_at',
        'pagamento_verificato_da_id',
        'data_pagamento',
        'documento_aggiuntivo_path',
        'documento_aggiuntivo_verificato',
        'documento_aggiuntivo_verificato_at',
        'documento_aggiuntivo_verificato_da_id',
    ];

    protected function casts(): array
    {
        return [
            'risposte'                   => 'array',
            'privacy_accettata'          => 'boolean',
            'consenso_gaia_accettata'    => 'boolean',
            'consenso_salute_accettata'  => 'boolean',
            'foto_video_accettata'       => 'boolean',
            'compiled_at'                => 'datetime',
            'nullaosta_verificato'       => 'boolean',
            'nullaosta_verificato_at'    => 'datetime',
            'pagamento_verificato'       => 'boolean',
            'pagamento_verificato_at'    => 'datetime',
            'data_pagamento'             => 'date',
            'documento_aggiuntivo_verificato'    => 'boolean',
            'documento_aggiuntivo_verificato_at' => 'datetime',
        ];
    }

    public function iscrizioneable(): MorphTo
    {
        return $this->morphTo();
    }

    public function verificatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagamento_verificato_da_id');
    }

    public function nullaostaVerificatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nullaosta_verificato_da_id');
    }

    public function documentoAggiuntivoVerificatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'documento_aggiuntivo_verificato_da_id');
    }
}
