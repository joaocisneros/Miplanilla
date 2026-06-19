<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ParametroPeriodo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'parametros_periodo';

    protected $fillable = [
        'anio', 'uit', 'rmv', 'asignacion_familiar', 'dias_base',
        'vigente_desde', 'vigente_hasta', 'confirmado', 'fuente',
    ];

    protected $casts = [
        'uit' => 'decimal:2',
        'rmv' => 'decimal:2',
        'asignacion_familiar' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'confirmado' => 'boolean',
    ];

    /** Parámetros vigentes a una fecha dada. */
    public static function vigenteEn(\DateTimeInterface|string $fecha): ?self
    {
        $fecha = $fecha instanceof \DateTimeInterface ? $fecha : new \DateTime($fecha);
        return static::where('vigente_desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha))
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
