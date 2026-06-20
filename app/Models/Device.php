<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $table = 'devices';

    protected $fillable = [
        'empresa_id', 'sede_id', 'nombre', 'marca', 'modelo',
        'tipo_conexion', 'config', 'ultima_sync', 'activo',
    ];

    protected $casts = [
        'config' => 'array',
        'ultima_sync' => 'datetime',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
