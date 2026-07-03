<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cts extends Model
{
    protected $table = 'cts';

    protected $fillable = [
        'empresa_id', 'employee_id', 'anio', 'periodo',
        'meses_computables', 'dias_computables', 'rem_computable',
        'sexto_gratificacion', 'monto', 'generado_por',
    ];

    protected $casts = [
        'rem_computable' => 'decimal:2',
        'sexto_gratificacion' => 'decimal:2',
        'monto' => 'decimal:2',
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
