<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adelanto extends Model
{
    protected $table = 'adelantos';

    protected $fillable = [
        'empresa_id', 'employee_id', 'tipo', 'anio', 'mes', 'monto',
        'concepto', 'grupo', 'cuota_num', 'cuotas_total', 'registrado_por',
    ];

    protected $casts = [
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
