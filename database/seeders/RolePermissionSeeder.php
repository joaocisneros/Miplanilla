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
            // Contratistas (pago por avance de obra)
            'contratistas.ver', 'contratistas.gestionar', 'contratistas.avance',
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
                'contratistas.ver', 'contratistas.gestionar', 'contratistas.avance',
            ],
            'SUPERVISOR' => [
                'asistencia.ver', 'asistencia.validar', 'asistencia.justificar',
                'empleados.ver', 'reportes.ver',
                // El supervisor registra el avance de obra (no gestiona pagos).
                'contratistas.ver', 'contratistas.avance',
            ],
            // Auditor externo (SUNAT/SUNAFIL, revisor puntual): solo lectura general.
            'AUDITOR' => [
                'empleados.ver', 'asistencia.ver', 'planilla.ver',
                'boletas.ver', 'reportes.ver',
            ],
            // Contador: SOLO LECTURA de lo que necesita para declarar (Planilla,
            // Honorarios, Gratificaciones, CTS, Vacaciones, Boletas, Reportes SUNAT).
            // NO ve Empleados (datos personales) ni Asistencia diaria (operativo).
            // Sí puede VER y EDITAR Parámetros/Tasas AFP/Pólizas Vida Ley (config.*):
            // suele ser quien actualiza esas tasas legales cada año.
            'CONTADOR' => [
                'planilla.ver', 'boletas.ver', 'reportes.ver',
                'config.ver', 'config.editar',
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
