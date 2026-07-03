<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gratificacion extends Model
{
    protected $table = 'gratificaciones';

    protected $fillable = [
        'empresa_id', 'employee_id', 'anio', 'tipo',
        'meses_computables', 'dias_computables', 'rem_computable',
        'monto', 'bonificacion_extraordinaria', 'renta_5ta', 'neto', 'generado_por',
    ];

    protected $casts = [
        'rem_computable' => 'decimal:2',
        'monto' => 'decimal:2',
        'bonificacion_extraordinaria' => 'decimal:2',
        'renta_5ta' => 'decimal:2',
        'neto' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
