<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TasaAfp extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tasas_afp';

    protected $fillable = [
        'afp', 'tipo', 'aporte_obligatorio', 'comision_flujo', 'comision_saldo',
        'prima_seguro', 'rem_max_asegurable', 'vigente_desde', 'vigente_hasta',
        'confirmado', 'fuente',
    ];

    protected $casts = [
        'aporte_obligatorio' => 'decimal:4',
        'comision_flujo' => 'decimal:4',
        'comision_saldo' => 'decimal:4',
        'prima_seguro' => 'decimal:4',
        'rem_max_asegurable' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'confirmado' => 'boolean',
    ];
}
