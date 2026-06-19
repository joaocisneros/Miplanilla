<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';

    protected $fillable = [
        'nombre', 'hora_entrada', 'hora_salida', 'refrigerio_min',
        'tolerancia_min', 'cruza_medianoche', 'minutos_jornada', 'activo',
    ];

    protected $casts = [
        'cruza_medianoche' => 'boolean',
        'activo' => 'boolean',
    ];
}
