<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmpresaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ADMIN', 'RRHH'] as $r) {
            Role::findOrCreate($r);
        }
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('ADMIN');
    }

    public function test_admin_puede_ver_listado_de_empresas(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/empresas')
            ->assertOk();
    }

    public function test_no_admin_no_puede_acceder(): void
    {
        $rrhh = User::factory()->create()->assignRole('RRHH');

        $this->actingAs($rrhh)
            ->get('/admin/empresas')
            ->assertForbidden();
    }

    public function test_admin_puede_registrar_empresa(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/empresas', [
                'ruc' => '20512345678',
                'razon_social' => 'ACS EIRL',
                'activo' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('empresas', [
            'ruc' => '20512345678',
            'razon_social' => 'ACS EIRL',
        ]);
    }

    public function test_ruc_debe_tener_11_digitos(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/empresas', [
                'ruc' => '123',
                'razon_social' => 'X',
            ])
            ->assertSessionHasErrors('ruc');
    }
}
