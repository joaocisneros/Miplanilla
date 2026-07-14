<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodigoOt extends Model
{
    protected $table = 'codigos_ot';

    protected $fillable = ['codigo', 'producto', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
