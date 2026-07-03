<?php

namespace App\Models;

use App\Models\Concerns\RestringidoPorEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Empresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use RestringidoPorEmpresa;

    protected $table = 'empresas';

    protected $fillable = [
        'ruc', 'razon_social', 'nombre_comercial', 'direccion', 'activo',
        'representante_legal', 'representante_dni', 'representante_cargo',
        'regimen_laboral', 'remype_numero', 'remype_fecha', 'giro', 'modo_calculo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'remype_fecha' => 'date',
    ];

    /** Etiquetas legibles de los regímenes laborales. */
    public const REGIMENES = [
        'general' => 'Régimen General (D.Leg. 728)',
        'microempresa' => 'Microempresa (Ley MYPE)',
        'pequena' => 'Pequeña Empresa (Ley MYPE)',
    ];

    /** ¿El régimen de la empresa otorga gratificación y CTS completas? Solo el general. */
    public function tieneBeneficiosCompletos(): bool
    {
        return ($this->regimen_laboral ?? 'general') === 'general';
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
