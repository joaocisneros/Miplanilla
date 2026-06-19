<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Contract extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'contracts';

    protected $fillable = [
        'employee_id', 'area_id', 'cargo_id', 'turno_id',
        'tipo_contrato', 'categoria_ocupacional', 'fecha_ingreso', 'fecha_cese',
        'sueldo_basico', 'percibe_asignacion_familiar', 'movilidad', 'otros',
        'sistema_pensiones', 'afp', 'tipo_afp', 'codigo_afp', 'fecha_afiliacion_pension',
        'aporta_sctr', 'aporta_senati', 'tiene_vida_ley', 'activo',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_cese' => 'date',
        'fecha_afiliacion_pension' => 'date',
        'sueldo_basico' => 'decimal:2',
        'movilidad' => 'decimal:2',
        'otros' => 'decimal:2',
        'percibe_asignacion_familiar' => 'boolean',
        'aporta_sctr' => 'boolean',
        'aporta_senati' => 'boolean',
        'tiene_vida_ley' => 'boolean',
        'activo' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }
}
