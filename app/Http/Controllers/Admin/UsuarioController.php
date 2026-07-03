<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        $usuarios = User::with([
            'roles:id,name',
            'empleado:id,user_id,empresa_id,apellido_paterno,nombres',
            'empleado.empresa:id,razon_social,nombre_comercial',
        ])->orderBy('id')->get()->map(function ($u) {
            $emp = $u->empleado;
            $c = $emp?->contratos()->latest('id')->first();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'rol' => $u->roles->first()?->name,
                'es_super' => $u->esSuperAdmin(),
                'ultimo_acceso' => $u->ultimo_acceso?->diffForHumans(),
                'ultimo_acceso_fecha' => $u->ultimo_acceso?->format('d/m/Y H:i'),
                'empleado_id' => $emp?->id,
                'empleado' => $emp ? [
                    'nombre' => trim($emp->apellido_paterno.' '.$emp->nombres),
                    'empresa' => $emp->empresa?->nombre_comercial ?: $emp->empresa?->razon_social,
                    'area' => $c?->area_id ? optional(Area::find($c->area_id))->nombre : null,
                    'cargo' => $c?->cargo_id ? optional(Cargo::find($c->cargo_id))->nombre : null,
                ] : null,
            ];
        });

        // Empleados SIN cuenta todavía (para vincular). No mezcla nada: solo referencia.
        $empleados = Employee::where('activo', true)->whereNull('user_id')
            ->with('empresa:id,razon_social,nombre_comercial')
            ->orderBy('apellido_paterno')->get()
            ->map(function ($e) {
                $c = $e->contratos()->latest('id')->first();

                return [
                    'id' => $e->id,
                    'nombre' => trim($e->apellido_paterno.' '.$e->nombres),
                    'empresa' => $e->empresa?->nombre_comercial ?: $e->empresa?->razon_social,
                    'area' => $c?->area_id ? optional(Area::find($c->area_id))->nombre : null,
                    'cargo' => $c?->cargo_id ? optional(Cargo::find($c->cargo_id))->nombre : null,
                ];
            });

        // Permisos de cada rol (referencia para el admin).
        $rolesDetalle = Role::with('permissions:id,name')->orderBy('name')->get()
            ->map(fn ($r) => [
                'nombre' => $r->name,
                'permisos' => $r->permissions->pluck('name')->values(),
            ]);

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->pluck('name'),
            'empleados' => $empleados,
            'rolesDetalle' => $rolesDetalle,
            'todosPermisos' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'rol' => ['required', 'exists:roles,name'],
            'empleado_id' => ['nullable', 'exists:employees,id'],
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->syncRoles([$data['rol']]);

            // Vínculo opcional con un empleado (para heredar su empresa/área/cargo).
            if (! empty($data['empleado_id'])) {
                Employee::where('id', $data['empleado_id'])->update(['user_id' => $user->id]);
            }
        });

        return back()->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $usuario)
    {
        // Super admin protegido: solo él mismo puede editarlo.
        abort_if($usuario->esSuperAdmin() && ! $request->user()->esSuperAdmin(), 403, 'El super administrador está protegido.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', Password::defaults()],
            'rol' => ['required', 'exists:roles,name'],
            'empleado_id' => ['nullable', 'exists:employees,id'],
        ]);

        // Al super admin nunca se le quita el rol ADMIN.
        $rol = $usuario->esSuperAdmin() ? 'ADMIN' : $data['rol'];

        DB::transaction(function () use ($data, $usuario, $rol) {
            $usuario->update([
                'name' => $data['name'],
                'email' => $data['email'],
                ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
            ]);
            $usuario->syncRoles([$rol]);

            // Re-vincular empleado: suelto el anterior y asigno el nuevo (no borra empleados).
            Employee::where('user_id', $usuario->id)->update(['user_id' => null]);
            if (! empty($data['empleado_id'])) {
                Employee::where('id', $data['empleado_id'])->update(['user_id' => $usuario->id]);
            }
        });

        return back()->with('success', 'Usuario actualizado.');
    }

    /** Actualiza los permisos de un rol (marcar/desmarcar). El rol ADMIN siempre mantiene todos. */
    public function actualizarPermisos(Request $request)
    {
        $data = $request->validate([
            'rol' => ['required', 'exists:roles,name'],
            'permisos' => ['array'],
            'permisos.*' => ['string', 'exists:permissions,name'],
        ]);

        // El ADMIN es intocable: siempre tiene todos los permisos.
        abort_if($data['rol'] === 'ADMIN', 403, 'El rol ADMIN siempre tiene todos los permisos.');

        $role = Role::findByName($data['rol']);
        $role->syncPermissions($data['permisos'] ?? []);

        return back()->with('success', "Permisos de {$data['rol']} actualizados.");
    }

    public function destroy(Request $request, User $usuario)
    {
        abort_if($usuario->esSuperAdmin(), 403, 'El super administrador no se puede eliminar.');
        abort_if($usuario->id === $request->user()->id, 403, 'No puedes eliminar tu propio usuario.');

        // Solo se suelta el vínculo; el empleado NO se borra.
        Employee::where('user_id', $usuario->id)->update(['user_id' => null]);
        $usuario->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}
