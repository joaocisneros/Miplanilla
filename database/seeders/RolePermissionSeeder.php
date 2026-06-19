<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        // Permisos granulares por módulo
        $permisos = [
            // Configuración / maestros (ADMIN)
            'config.ver', 'config.editar',
            'usuarios.ver', 'usuarios.gestionar',
            'auditoria.ver',
            // Empleados (RRHH)
            'empleados.ver', 'empleados.gestionar',
            // Asistencia
            'asistencia.ver', 'asistencia.sincronizar', 'asistencia.justificar', 'asistencia.validar',
            // Planilla
            'planilla.ver', 'planilla.generar', 'planilla.cerrar',
            // Boletas / reportes
            'boletas.ver', 'boletas.generar',
            'reportes.ver',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $roles = [
            'ADMIN' => $permisos, // todo
            'RRHH' => [
                'empleados.ver', 'empleados.gestionar',
                'asistencia.ver', 'asistencia.sincronizar', 'asistencia.justificar',
                'planilla.ver', 'planilla.generar', 'planilla.cerrar',
                'boletas.ver', 'boletas.generar', 'reportes.ver',
            ],
            'SUPERVISOR' => [
                'asistencia.ver', 'asistencia.validar', 'asistencia.justificar',
                'empleados.ver', 'reportes.ver',
            ],
            'EMPLEADO' => [
                'asistencia.ver', 'boletas.ver',
            ],
        ];

        foreach ($roles as $nombre => $perms) {
            $role = Role::firstOrCreate(['name' => $nombre]);
            $role->syncPermissions($perms);
        }
    }
}
