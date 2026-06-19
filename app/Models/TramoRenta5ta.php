<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TramoRenta5ta extends Model
{
    protected $table = 'tramos_renta_5ta';

    protected $fillable = [
        'orden', 'desde_uit', 'hasta_uit', 'tasa', 'vigente_desde', 'vigente_hasta',
    ];

    protected $casts = [
        'desde_uit' => 'decimal:2',
        'hasta_uit' => 'decimal:2',
        'tasa' => 'decimal:4',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];
}
