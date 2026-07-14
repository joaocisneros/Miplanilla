<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Employee;
use App\Models\IngresoAdicional;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdicionalesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Employee $empleado;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->empresa = Empresa::create(['ruc' => '20100000001', 'razon_social' => 'ACS PERU']);
        $this->empleado = Employee::create([
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'GARCIA', 'nombres' => 'LUIS',
            'tipo_documento' => 'DNI', 'numero_documento' => '11223344',
        ]);
        $this->rrhh = User::factory()->create()->assignRole('RRHH');
    }

    private function guardar(array $fila)
    {
        return $this->actingAs($this->rrhh)->post('/adicionales', [
            'empresa_id' => $this->empresa->id,
            'anio' => 2026, 'mes' => 6, 'quincena' => 2,
            'filas' => [array_merge(['employee_id' => $this->empleado->id], $fila)],
        ]);
    }

    public function test_guarda_los_montos_del_periodo(): void
    {
        $this->guardar(['monto_horas' => 28.75, 'sabado' => 153.33, 'domingo_feriado' => 76.67, 'bono' => 100, 'nota' => 'comisión ventas'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ingresos_adicionales', [
            'employee_id' => $this->empleado->id,
            'anio' => 2026, 'mes' => 6, 'quincena' => 2,
            'monto_horas' => 28.75, 'sabado' => 153.33,
            'domingo_feriado' => 76.67, 'bono' => 100,
            'nota' => 'comisión ventas',
        ]);
    }

    public function test_monto_de_horas_queda_aprobado_automaticamente(): void
    {
        // Sin checkbox de aprobado en la pantalla: si hay monto, el motor debe pagarlo.
        $this->guardar(['monto_horas' => 50]);

        $reg = IngresoAdicional::where('employee_id', $this->empleado->id)->first();
        $this->assertTrue((bool) $reg->aprobado, 'El monto de H.E. debe quedar aprobado solo');
        $this->assertEquals(50.0, (float) $reg->monto_horas);
    }

    public function test_dejar_todo_en_cero_limpia_el_registro(): void
    {
        $this->guardar(['bono' => 100]);
        $this->assertDatabaseCount('ingresos_adicionales', 1);

        $this->guardar(['bono' => 0]); // limpiar
        $this->assertDatabaseCount('ingresos_adicionales', 0);
    }

    public function test_actualizar_no_duplica(): void
    {
        $this->guardar(['bono' => 100]);
        $this->guardar(['bono' => 300]); // correccion (el caso GARCIA)

        $this->assertDatabaseCount('ingresos_adicionales', 1);
        $this->assertEquals(300.0, (float) IngresoAdicional::first()->bono);
    }
}
