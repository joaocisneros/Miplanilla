<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Sede extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sedes';

    protected $fillable = [
        'empresa_id', 'nombre', 'direccion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
