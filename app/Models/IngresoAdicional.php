<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoAdicional extends Model
{
    protected $table = 'ingresos_adicionales';

    protected $fillable = [
        'empresa_id', 'employee_id', 'anio', 'mes', 'quincena',
        'horas', 'minutos', 'aprobado', 'monto_horas', 'sabado', 'domingo_feriado',
        'bono', 'otros_afectos', 'renta_5ta_manual', 'nota', 'registrado_por',
    ];

    protected $casts = [
        'horas' => 'decimal:2',
        'minutos' => 'integer',
        'aprobado' => 'boolean',
        'monto_horas' => 'decimal:2',
        'sabado' => 'decimal:2',
        'domingo_feriado' => 'decimal:2',
        'bono' => 'decimal:2',
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
