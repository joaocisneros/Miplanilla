<?php

namespace App\Models;

use App\Models\Concerns\RestringidoPorEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    use RestringidoPorEmpresa;

    protected $table = 'areas';

    protected $fillable = [
        'empresa_id', 'nombre', 'es_riesgo', 'activo',
    ];

    protected $casts = [
        'es_riesgo' => 'boolean',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
