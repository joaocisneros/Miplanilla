<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
        'ultimo_acceso',
        'ultimo_acceso_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'activo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** El nombre del usuario se guarda SIEMPRE en MAYÚSCULA (uniforme con empleados). */
    public function setNameAttribute($v): void
    {
        $this->attributes['name'] = $v !== null && $v !== '' ? mb_strtoupper(trim($v), 'UTF-8') : $v;
    }

    /** El correo SIEMPRE en minúscula (evita fallos de login en PostgreSQL, que distingue mayúsculas). */
    public function setEmailAttribute($v): void
    {
        $this->attributes['email'] = $v !== null ? mb_strtolower(trim($v), 'UTF-8') : $v;
    }

    /** Empleado vinculado a esta cuenta (si el usuario es un trabajador). */
    public function empleado()
    {
        return $this->hasOne(\App\Models\Employee::class);
    }

    /** Empresas a las que este usuario tiene acceso (vacío = todas). */
    public function empresas()
    {
        return $this->belongsToMany(\App\Models\Empresa::class, 'empresa_user');
    }

    /** @var array<int>|null|false Memo de IDs permitidos (false = aún no calculado). */
    protected array|null|false $empresaIdsCache = false;

    /**
     * IDs de empresas a las que el usuario puede acceder.
     * Devuelve null = SIN restricción (ve todas). Un array = solo esas empresas.
     * El super admin siempre ve todas.
     */
    public function empresasPermitidasIds(): ?array
    {
        if ($this->esSuperAdmin()) {
            return null;
        }
        if ($this->empresaIdsCache !== false) {
            return $this->empresaIdsCache;
        }

        // Consulta directa al pivote (evita el global scope de Empresa y recursión).
        $ids = \Illuminate\Support\Facades\DB::table('empresa_user')
            ->where('user_id', $this->id)->pluck('empresa_id')->map(fn ($v) => (int) $v)->all();

        return $this->empresaIdsCache = count($ids) ? $ids : null;
    }

    /** ¿El usuario está limitado a una o más empresas? */
    public function estaRestringido(): bool
    {
        return $this->empresasPermitidasIds() !== null;
    }

    /** El super administrador (primer usuario) está protegido: no se puede borrar ni cambiar su rol. */
    public function esSuperAdmin(): bool
    {
        return $this->id === 1;
    }

    /**
     * ¿Es SOLO un trabajador (rol EMPLEADO) sin ningún otro rol de gestión?
     * Se usa para limitar su acceso a sus propios datos (boleta, asistencia),
     * nunca a los de otros trabajadores.
     */
    public function esSoloEmpleado(): bool
    {
        return $this->hasRole('EMPLEADO') && ! $this->hasAnyRole(['ADMIN', 'RRHH', 'SUPERVISOR', 'CONTADOR', 'AUDITOR']);
    }
}
