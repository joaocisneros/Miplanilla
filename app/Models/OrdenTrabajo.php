<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenTrabajo extends Model
{
    protected $table = 'ordenes_trabajo';

    protected $fillable = ['contratista_id', 'empresa_id', 'codigo', 'producto', 'descripcion', 'precio', 'estado'];

    protected $casts = ['precio' => 'decimal:2'];

    public function contratista(): BelongsTo
    {
        return $this->belongsTo(Contratista::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function avances(): HasMany
    {
        return $this->hasMany(OtAvance::class, 'orden_trabajo_id');
    }
}
