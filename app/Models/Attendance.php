<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Attendance extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'attendance';

    protected $fillable = [
        'empresa_id', 'employee_id', 'fecha', 'estado',
        'hora_entrada_real', 'hora_salida_real', 'minutos_tarde',
        'horas_extra', 'horas_extra_aprobadas', 'origen', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'minutos_tarde' => 'integer',
        'horas_extra' => 'decimal:2',
        'horas_extra_aprobadas' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
