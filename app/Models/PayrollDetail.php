<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDetail extends Model
{
    protected $table = 'payroll_details';

    protected $fillable = [
        'payroll_id', 'employee_id', 'modalidad',
        'base_afecta', 'total_ingresos', 'pension_total', 'renta_5ta',
        'total_descuentos', 'neto',
        'essalud', 'sctr_pension', 'sctr_salud', 'vida_ley', 'senati', 'desglose',
    ];

    protected $casts = [
        'desglose' => 'array',
        'base_afecta' => 'decimal:2',
        'total_ingresos' => 'decimal:2',
        'pension_total' => 'decimal:2',
        'renta_5ta' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'neto' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
