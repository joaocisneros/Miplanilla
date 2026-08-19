<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoVentaExterna extends Model
{
    protected $table = 'pagos_ventas_externas';
    protected $fillable = ['periodo_venta_externa_id', 'vendedor_externo_id', 'tipo_pago', 'pago_fijo', 'ventas', 'tipo_comision', 'porcentaje_comision', 'comision', 'bonos', 'adelantos', 'total', 'observacion'];
    protected $casts = ['pago_fijo' => 'decimal:2', 'ventas' => 'decimal:2', 'porcentaje_comision' => 'decimal:4', 'comision' => 'decimal:2', 'bonos' => 'decimal:2', 'adelantos' => 'decimal:2', 'total' => 'decimal:2'];
    public function periodo(): BelongsTo { return $this->belongsTo(PeriodoVentaExterna::class, 'periodo_venta_externa_id'); }
    public function vendedor(): BelongsTo { return $this->belongsTo(VendedorExterno::class, 'vendedor_externo_id'); }
}
