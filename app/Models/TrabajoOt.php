<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catálogo de trabajos/descripciones para las órdenes de trabajo. */
class TrabajoOt extends Model
{
    protected $table = 'trabajos_ot';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
