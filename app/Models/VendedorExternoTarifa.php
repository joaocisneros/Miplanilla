<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendedorExternoTarifa extends Model
{
    protected $table = 'vendedor_externo_tarifas';
    protected $fillable = ['vendedor_externo_id', 'empresa_id', 'tipo_pago', 'pago_fijo', 'tipo_comision', 'porcentaje_comision', 'activo'];
    protected $casts = ['pago_fijo' => 'decimal:2', 'porcentaje_comision' => 'decimal:4', 'activo' => 'boolean'];
    public function vendedor(): BelongsTo { return $this->belongsTo(VendedorExterno::class, 'vendedor_externo_id'); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
}
