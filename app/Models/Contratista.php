<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contratista extends Model
{
    protected $table = 'contratistas';

    protected $fillable = ['nombre', 'ruc', 'telefono', 'cuenta', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }
}
