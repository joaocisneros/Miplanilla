<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoVentaExterna extends Model
{
    protected $table = 'periodos_ventas_externas';
    protected $fillable = ['empresa_id', 'anio', 'mes', 'estado', 'fecha_pago', 'creado_por', 'cerrado_por', 'cerrado_en'];
    protected $casts = ['fecha_pago' => 'date', 'cerrado_en' => 'datetime'];
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function pagos(): HasMany { return $this->hasMany(PagoVentaExterna::class, 'periodo_venta_externa_id'); }
}
