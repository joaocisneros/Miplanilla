<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtAvance extends Model
{
    protected $table = 'ot_avances';

    protected $fillable = ['orden_trabajo_id', 'fecha', 'porcentaje', 'pagado', 'fecha_pago', 'nota', 'registrado_por'];

    protected $casts = [
        'fecha' => 'date',
        'fecha_pago' => 'date',
        'porcentaje' => 'decimal:2',
        'pagado' => 'boolean',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }
}
