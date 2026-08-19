<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendedorExterno extends Model
{
    protected $table = 'vendedores_externos';
    protected $fillable = ['tipo_documento', 'numero_documento', 'nombre', 'telefono', 'correo', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function tarifas(): HasMany { return $this->hasMany(VendedorExternoTarifa::class); }
    public function pagos(): HasMany { return $this->hasMany(PagoVentaExterna::class); }
}
