<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Empresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empresas';

    protected $fillable = [
        'ruc', 'razon_social', 'nombre_comercial', 'direccion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
