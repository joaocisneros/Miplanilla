<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsistenciaResumen extends Model
{
    protected $table = 'asistencia_resumenes';

    protected $fillable = [
        'empresa_id', 'employee_id', 'anio', 'mes', 'quincena',
        'dias_trabajados', 'faltas', 'tardanza_min', 'horas_extra',
        'sabado', 'feriados_domingos', 'vacaciones', 'licencia', 'importado_por',
    ];

    protected $casts = [
        'dias_trabajados' => 'decimal:2',
        'horas_extra' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
