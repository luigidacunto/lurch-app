<?php

namespace App\Models;

use App\Models\Socio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComitatoRegionale extends Model
{
    use SoftDeletes;

    protected $table = 'comitati_regionali';

    protected $fillable = [
        'nome', 'rappresentante_legale', 'indirizzo', 'email', 'codice', 'attivo',
        'url_portale', 'logo', 'privacy_responsabile',
        'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password',
        'mail_from_address', 'mail_from_name',
    ];

    protected $hidden = ['mail_password'];

    protected function casts(): array
    {
        return [
            'attivo'        => 'boolean',
            'mail_port'     => 'integer',
            'mail_password' => 'encrypted',
        ];
    }

    /** Account SMTP della regione completo (server, mittente e credenziali). */
    public function haPostaConfigurata(): bool
    {
        return filled($this->mail_host) && filled($this->mail_port)
            && filled($this->mail_username) && filled($this->mail_password)
            && filled($this->mail_from_address);
    }

    public function scopeAttivo($query)
    {
        return $query->where('attivo', true);
    }

    public function comitatiLocali(): HasMany
    {
        return $this->hasMany(ComitatoLocale::class, 'comitato_regionale_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'comitato_regionale_id');
    }

    public function catalogoCorsi(): HasMany
    {
        return $this->hasMany(CatalogoCorso::class, 'comitato_regionale_id');
    }

    public function eventi(): HasMany
    {
        return $this->hasMany(Evento::class, 'comitato_regionale_id');
    }

    public function partecipanti(): HasMany
    {
        return $this->hasMany(Socio::class, 'comitato_regionale_id');
    }
}
