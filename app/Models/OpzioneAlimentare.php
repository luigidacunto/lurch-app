<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpzioneAlimentare extends Model
{
    protected $table = 'opzioni_alimentari';

    protected $fillable = ['tipo', 'nome', 'etichetta', 'ordine', 'attivo'];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
            'ordine' => 'integer',
        ];
    }

    public static function allergeni(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tipo', 'allergene')->where('attivo', true)->orderBy('ordine')->get();
    }

    public static function diete(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tipo', 'dieta')->where('attivo', true)->orderBy('ordine')->get();
    }

    public static function intolleranze(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tipo', 'intolleranza')->where('attivo', true)->orderBy('ordine')->get();
    }

    public static function esclusioni(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tipo', 'esclusione')->where('attivo', true)->orderBy('ordine')->get();
    }
}
