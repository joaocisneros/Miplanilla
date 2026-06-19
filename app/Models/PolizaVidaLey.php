<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PolizaVidaLey extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'polizas_vida_ley';

    protected $fillable = [
        'aseguradora', 'tasa', 'base', 'vigente_desde', 'vigente_hasta', 'confirmado',
    ];

    protected $casts = [
        'tasa' => 'decimal:4',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'confirmado' => 'boolean',
    ];
}
