<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Incidencia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'incidencias';

    protected $fillable = [
        'attendance_id', 'employee_id', 'fecha', 'tipo', 'motivo',
        'solicitado_por', 'justificado_por', 'estado', 'adjunto',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
