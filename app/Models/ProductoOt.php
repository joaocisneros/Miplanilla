<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoOt extends Model
{
    protected $table = 'productos_ot';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
