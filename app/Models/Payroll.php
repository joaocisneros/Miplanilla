<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $table = 'payrolls';

    protected $fillable = [
        'empresa_id', 'periodo_id', 'estado',
        'total_ingresos', 'total_descuentos', 'total_neto', 'total_aportes_empleador',
        'cantidad_empleados', 'generado_por', 'cerrado_at',
    ];

    protected $casts = [
        'total_ingresos' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_neto' => 'decimal:2',
        'total_aportes_empleador' => 'decimal:2',
        'cerrado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
