<?php

namespace Tests\Feature;

use App\Models\Contratista;
use App\Models\OrdenTrabajo;
use App\Models\OtAvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContratistaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['contratistas.ver', 'contratistas.gestionar', 'contratistas.avance'] as $p) {
            Permission::findOrCreate($p);
        }
        Role::findOrCreate('ADMIN')->givePermissionTo(['contratistas.ver', 'contratistas.gestionar', 'contratistas.avance']);
        Role::findOrCreate('SUPERVISOR')->givePermissionTo(['contratistas.ver', 'contratistas.avance']);
        Role::findOrCreate('EMPLEADO');
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('ADMIN');
    }

    private function ot(float $precio = 3000): OrdenTrabajo
    {
        $c = Contratista::create(['nombre' => 'JACK GOMEZ', 'activo' => true]);

        return OrdenTrabajo::create([
            'contratista_id' => $c->id, 'codigo' => '034/26',
            'producto' => 'SR TOLVA 22M3', 'descripcion' => 'ARMADO DE CASCO', 'precio' => $precio,
        ]);
    }

    public function test_admin_ve_el_modulo(): void
    {
        $this->actingAs($this->admin())->get('/contratistas')->assertOk();
    }

    public function test_empleado_no_puede_entrar(): void
    {
        $u = User::factory()->create()->assignRole('EMPLEADO');
        $this->actingAs($u)->get('/contratistas')->assertForbidden();
    }

    public function test_crear_contratista_y_ot(): void
    {
        $this->actingAs($this->admin())
            ->post('/contratistas', ['nombre' => 'SABINO LAURENTE', 'ruc' => '10450802380'])
            ->assertSessionHas('success');
        $c = Contratista::where('nombre', 'SABINO LAURENTE')->first();
        $this->assertNotNull($c);

        $this->actingAs($this->admin())
            ->post('/contratistas/ots', [
                'contratista_id' => $c->id, 'codigo' => '78/26', 'precio' => 1000,
                'producto' => 'TOLVA 20M3', 'descripcion' => 'Puerta basculante',
            ])->assertSessionHas('success');
        $this->assertDatabaseHas('ordenes_trabajo', ['contratista_id' => $c->id, 'codigo' => '78/26']);
    }

    public function test_codigo_de_ot_no_se_repite_para_el_mismo_contratista(): void
    {
        $ot = $this->ot();
        $this->actingAs($this->admin())
            ->post('/contratistas/ots', ['contratista_id' => $ot->contratista_id, 'codigo' => '034/26', 'precio' => 500])
            ->assertSessionHasErrors('codigo');
    }

    public function test_supervisor_registra_avance_y_calcula_monto(): void
    {
        $ot = $this->ot(3000);
        $sup = User::factory()->create()->assignRole('SUPERVISOR');

        $this->actingAs($sup)
            ->post('/contratistas/avances', ['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-15', 'porcentaje' => 40])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ot_avances', ['orden_trabajo_id' => $ot->id, 'porcentaje' => 40, 'registrado_por' => $sup->id]);
    }

    public function test_avance_no_puede_pasar_del_100_por_ciento(): void
    {
        $ot = $this->ot();
        OtAvance::create(['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-15', 'porcentaje' => 80]);

        $this->actingAs($this->admin())
            ->post('/contratistas/avances', ['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-30', 'porcentaje' => 30])
            ->assertSessionHasErrors('porcentaje');

        $this->assertEquals(80, (float) $ot->avances()->sum('porcentaje'));
    }

    public function test_al_llegar_al_100_la_ot_queda_terminada(): void
    {
        $ot = $this->ot();
        OtAvance::create(['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-15', 'porcentaje' => 60]);

        $this->actingAs($this->admin())
            ->post('/contratistas/avances', ['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-30', 'porcentaje' => 40]);

        $this->assertEquals('terminada', $ot->fresh()->estado);
    }

    public function test_pagar_corte_marca_solo_los_avances_del_rango(): void
    {
        $ot = $this->ot();
        $dentro = OtAvance::create(['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-20', 'porcentaje' => 50]);
        $fuera = OtAvance::create(['orden_trabajo_id' => $ot->id, 'fecha' => '2026-07-05', 'porcentaje' => 20]);

        $this->actingAs($this->admin())
            ->post('/contratistas/corte/pagar', ['desde' => '2026-06-16', 'hasta' => '2026-06-30'])
            ->assertSessionHas('success');

        $this->assertTrue($dentro->fresh()->pagado);
        $this->assertFalse($fuera->fresh()->pagado);
    }

    public function test_no_se_puede_eliminar_avance_pagado(): void
    {
        $ot = $this->ot();
        $a = OtAvance::create(['orden_trabajo_id' => $ot->id, 'fecha' => '2026-06-20', 'porcentaje' => 50, 'pagado' => true]);

        $this->actingAs($this->admin())
            ->delete('/contratistas/avances/'.$a->id)
            ->assertStatus(422);
        $this->assertDatabaseHas('ot_avances', ['id' => $a->id]);
    }

    public function test_no_se_elimina_contratista_con_ots(): void
    {
        $ot = $this->ot();
        $this->actingAs($this->admin())
            ->delete('/contratistas/'.$ot->contratista_id)
            ->assertSessionHas('error');
        $this->assertDatabaseHas('contratistas', ['id' => $ot->contratista_id]);
    }

    public function test_supervisor_no_gestiona_ni_paga(): void
    {
        $ot = $this->ot();
        $sup = User::factory()->create()->assignRole('SUPERVISOR');

        $this->actingAs($sup)->post('/contratistas', ['nombre' => 'X'])->assertForbidden();
        $this->actingAs($sup)->post('/contratistas/corte/pagar', ['desde' => '2026-06-01', 'hasta' => '2026-06-15'])->assertForbidden();
    }
}
