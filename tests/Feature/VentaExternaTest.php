<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\PagoVentaExterna;
use App\Models\PeriodoVentaExterna;
use App\Models\User;
use App\Models\VendedorExterno;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaExternaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Empresa $empresa;
    private VendedorExterno $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('ADMIN');
        $this->empresa = Empresa::create(['ruc' => '20100000001', 'razon_social' => 'ACS INDUSTRIA', 'regimen_laboral' => 'general', 'modo_calculo' => 'excel']);
        $this->vendedor = VendedorExterno::create(['numero_documento' => '10700039', 'nombre' => 'INFANTE LUDEÑA GRETA ROSARIO']);
    }

    public function test_configura_tarifa_genera_y_calcula_pago(): void
    {
        $this->actingAs($this->admin)->post("/ventas-externas/vendedores/{$this->vendedor->id}/tarifas", [
            'empresa_id' => $this->empresa->id, 'tipo_pago' => 'mixto', 'pago_fijo' => 800,
            'tipo_comision' => 'porcentaje', 'porcentaje_comision' => 5,
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)->post('/ventas-externas/generar', [
            'empresa_id' => $this->empresa->id, 'anio' => 2026, 'mes' => 7,
        ])->assertRedirect();

        $pago = PagoVentaExterna::firstOrFail();
        $this->actingAs($this->admin)->put("/ventas-externas/pagos/{$pago->id}", [
            'pago_fijo' => 800, 'ventas' => 10000, 'tipo_comision' => 'porcentaje',
            'porcentaje_comision' => 5, 'comision' => 0, 'bonos' => 100, 'adelantos' => 200,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('pagos_ventas_externas', ['id' => $pago->id, 'comision' => 500, 'total' => 1200]);
    }

    public function test_periodo_cerrado_no_permite_editar_pago(): void
    {
        $periodo = PeriodoVentaExterna::create(['empresa_id' => $this->empresa->id, 'anio' => 2026, 'mes' => 7, 'estado' => 'cerrado']);
        $pago = PagoVentaExterna::create(['periodo_venta_externa_id' => $periodo->id, 'vendedor_externo_id' => $this->vendedor->id, 'tipo_pago' => 'fijo', 'pago_fijo' => 100, 'total' => 100]);

        $this->actingAs($this->admin)->put("/ventas-externas/pagos/{$pago->id}", [
            'pago_fijo' => 200, 'ventas' => 0, 'tipo_comision' => 'manual', 'porcentaje_comision' => 0,
            'comision' => 0, 'bonos' => 0, 'adelantos' => 0,
        ])->assertSessionHas('error');
        $this->assertEquals(100, (float) $pago->fresh()->total);
    }
}
