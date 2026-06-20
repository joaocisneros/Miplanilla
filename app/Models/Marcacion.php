<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Marcacion extends Model
{
    protected $table = 'marcaciones';

    protected $fillable = [
        'empresa_id', 'employee_id', 'device_id',
        'codigo_trabajador_origen', 'codigo_dispositivo_origen',
        'fecha_hora_marca', 'zona_horaria', 'fecha_hora_recepcion',
        'tipo', 'metodo', 'origen', 'hash_unico', 'raw_payload', 'procesada',
    ];

    protected $casts = [
        'fecha_hora_marca' => 'datetime',
        'fecha_hora_recepcion' => 'datetime',
        'raw_payload' => 'array',
        'procesada' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
