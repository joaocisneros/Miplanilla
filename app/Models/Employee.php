<?php

namespace App\Models;

use App\Models\Concerns\RestringidoPorEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Employee extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;
    use RestringidoPorEmpresa;

    protected $table = 'employees';

    protected $fillable = [
        'empresa_id', 'sede_id',
        'apellido_paterno', 'apellido_materno', 'nombres',
        'tipo_documento', 'numero_documento', 'ruc',
        'fecha_nacimiento', 'genero', 'estado_civil', 'lugar_nacimiento', 'profesion',
        'telefono', 'correo', 'direccion', 'distrito', 'provincia', 'departamento',
        'tipo_vivienda', 'nivel_educativo',
        'banco', 'cuenta_corriente', 'cuenta_ahorros', 'cci',
        'emergencia_nombre', 'emergencia_telefono', 'emergencia_parentesco',
        'codigo_biometrico', 'user_id', 'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    /** Nombres y apellidos SIEMPRE en MAYÚSCULA (evita mezclar mayúsculas/minúsculas). */
    private static function mayus($v)
    {
        return $v !== null && $v !== '' ? mb_strtoupper(trim($v), 'UTF-8') : $v;
    }

    public function setApellidoPaternoAttribute($v): void
    {
        $this->attributes['apellido_paterno'] = self::mayus($v);
    }

    public function setApellidoMaternoAttribute($v): void
    {
        $this->attributes['apellido_materno'] = self::mayus($v);
    }

    public function setNombresAttribute($v): void
    {
        $this->attributes['nombres'] = self::mayus($v);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellido_paterno} {$this->apellido_materno} {$this->nombres}");
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function contratoVigente()
    {
        return $this->hasMany(Contract::class)->where('activo', true)->latest('fecha_ingreso');
    }

    public function derechohabientes(): HasMany
    {
        return $this->hasMany(Derechohabiente::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    /** Tiene derecho a asignación familiar: hijos < 18 o hasta 24 estudiando. */
    public function tieneAsignacionFamiliar(): bool
    {
        return $this->derechohabientes()
            ->where('tipo', 'hijo')
            ->get()
            ->contains(function ($d) {
                if (! $d->fecha_nacimiento) {
                    return false;
                }
                $edad = $d->fecha_nacimiento->age;
                return $edad < 18 || ($edad < 24 && $d->estudia);
            });
    }
}
