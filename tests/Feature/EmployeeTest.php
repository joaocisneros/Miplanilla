<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->empresa = Empresa::create(['ruc' => '20100000001', 'razon_social' => 'ACS PERU']);
    }

    private function rrhh(): User
    {
        return User::factory()->create()->assignRole('RRHH');
    }

    private function actuarConEmpresa(User $user)
    {
        return $this->actingAs($user)->withSession(['empresa_id' => $this->empresa->id]);
    }

    public function test_rrhh_puede_ver_listado(): void
    {
        $this->actuarConEmpresa($this->rrhh())
            ->get('/empleados')
            ->assertOk();
    }

    public function test_rrhh_puede_registrar_empleado_con_contrato_y_derechohabiente(): void
    {
        $sede = Sede::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Principal']);

        $this->actuarConEmpresa($this->rrhh())->post('/empleados', [
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'CISNEROS',
            'apellido_materno' => 'PACOTAYPE',
            'nombres' => 'KEVIN',
            'tipo_documento' => 'DNI',
            'numero_documento' => '71246290',
            'sede_id' => $sede->id,
            'categoria_ocupacional' => 'empleado',
            'fecha_ingreso' => '2026-01-01',
            'sueldo_basico' => 4500,
            'sistema_pensiones' => 'AFP',
            'afp' => 'INTEGRA',
            'tipo_afp' => 'mixta',
            'derechohabientes' => [
                ['tipo' => 'hijo', 'nombres' => 'Hijo Uno', 'fecha_nacimiento' => '2015-05-01', 'estudia' => false],
            ],
        ])->assertRedirect(route('empleados.index'));

        $this->assertDatabaseHas('employees', [
            'numero_documento' => '71246290',
            'empresa_id' => $this->empresa->id,
        ]);
        $this->assertDatabaseHas('contracts', ['sueldo_basico' => 4500, 'afp' => 'INTEGRA']);
        $this->assertDatabaseHas('derechohabientes', ['nombres' => 'Hijo Uno']);
    }

    public function test_afp_exige_tipo_flujo_o_mixta(): void
    {
        $this->actuarConEmpresa($this->rrhh())->post('/empleados', [
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'X', 'nombres' => 'Y',
            'tipo_documento' => 'DNI', 'numero_documento' => '99999999',
            'categoria_ocupacional' => 'empleado', 'fecha_ingreso' => '2026-01-01',
            'sueldo_basico' => 1500, 'sistema_pensiones' => 'AFP',
        ])->assertSessionHasErrors(['afp', 'tipo_afp']);
    }

    public function test_asignacion_familiar_se_deriva_de_hijos(): void
    {
        $emp = \App\Models\Employee::create([
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'A', 'nombres' => 'B',
            'tipo_documento' => 'DNI', 'numero_documento' => '12312312',
        ]);
        $emp->derechohabientes()->create(['tipo' => 'hijo', 'nombres' => 'Niño', 'fecha_nacimiento' => now()->subYears(10)]);

        $this->assertTrue($emp->refresh()->tieneAsignacionFamiliar());
    }
}
