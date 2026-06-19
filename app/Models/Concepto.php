<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concepto extends Model
{
    protected $table = 'conceptos';

    protected $fillable = [
        'codigo', 'nombre', 'tipo',
        'es_remunerativo', 'afecto_afp_onp', 'afecto_essalud', 'afecto_sctr',
        'afecto_5ta', 'afecta_descuento_inasistencia', 'evalua_regularidad',
        'estrategia_calculo', 'activo',
    ];

    protected $casts = [
        'es_remunerativo' => 'boolean',
        'afecto_afp_onp' => 'boolean',
        'afecto_essalud' => 'boolean',
        'afecto_sctr' => 'boolean',
        'afecto_5ta' => 'boolean',
        'afecta_descuento_inasistencia' => 'boolean',
        'evalua_regularidad' => 'boolean',
        'activo' => 'boolean',
    ];
}
