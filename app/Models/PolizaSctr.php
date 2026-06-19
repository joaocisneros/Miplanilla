<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PolizaSctr extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'polizas_sctr';

    protected $fillable = [
        'aseguradora', 'actividad_riesgo', 'tasa_salud', 'tasa_pension',
        'vigente_desde', 'vigente_hasta', 'confirmado',
    ];

    protected $casts = [
        'tasa_salud' => 'decimal:4',
        'tasa_pension' => 'decimal:4',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'confirmado' => 'boolean',
    ];
}
