<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba de humo: TODAS las pantallas principales deben cargar sin error
 * para un ADMIN. Atrapa rutas rotas, controladores con errores y páginas
 * que exploten al renderizar (aunque estén vacías de datos).
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_pantallas_cargan_sin_error(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('ADMIN');

        $pantallas = [
            '/dashboard',
            '/empleados',
            '/asistencia',
            '/asistencia/diario',
            '/asistencia/resumen',
            '/planilla',
            '/honorarios',
            '/adicionales',
            '/adelantos',
            '/contratistas',
            '/gratificaciones',
            '/cts',
            '/vacaciones',
            '/liquidacion',
            '/reportes/consolidado',
            '/reportes/tributos',
            '/reportes/retenciones',
            '/admin/empresas',
            '/admin/sedes',
            '/admin/areas',
            '/admin/cargos',
            '/admin/turnos',
            '/admin/feriados',
            '/admin/usuarios',
            '/admin/parametros',
            '/admin/tasas-afp',
            '/admin/polizas-sctr',
            '/admin/polizas-vida-ley',
            '/admin/conceptos',
            '/admin/auditoria',
        ];

        foreach ($pantallas as $url) {
            $resp = $this->actingAs($admin)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "La pantalla {$url} devolvió {$resp->status()} en vez de 200"
            );
        }
    }
}
