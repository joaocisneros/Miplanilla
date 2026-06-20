<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AsistenciaImportTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->empresa = Empresa::create(['ruc' => '20100000001', 'razon_social' => 'ACS PERU']);
    }

    public function test_importa_asistencia_desde_csv(): void
    {
        $emp = Employee::create([
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'CISNEROS', 'nombres' => 'KEVIN',
            'tipo_documento' => 'DNI', 'numero_documento' => '71246290',
        ]);

        $csv = "dni,fecha,estado,minutos_tarde,horas_extra,hora_entrada,hora_salida\n"
            . "71246290,2026-06-02,NORMAL,15,2,08:00,18:00\n"
            . "71246290,2026-06-03,FALTA,0,0,,\n";
        $path = tempnam(sys_get_temp_dir(), 'asis').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'asistencia.csv', 'text/csv', null, true);

        $user = User::factory()->create()->assignRole('RRHH');

        $this->actingAs($user)->withSession(['empresa_id' => $this->empresa->id])
            ->post(route('asistencia.import'), ['archivo' => $file])
            ->assertRedirect();

        $this->assertTrue(
            \App\Models\Attendance::whereDate('fecha', '2026-06-02')
                ->where('employee_id', $emp->id)->where('estado', 'NORMAL')
                ->where('minutos_tarde', 15)->where('horas_extra', 2)->exists()
        );
        $this->assertTrue(
            \App\Models\Attendance::whereDate('fecha', '2026-06-03')
                ->where('employee_id', $emp->id)->where('estado', 'FALTA')->exists()
        );
    }

    public function test_dni_inexistente_no_rompe_la_importacion(): void
    {
        $csv = "dni,fecha,estado\n00000000,2026-06-02,NORMAL\n";
        $path = tempnam(sys_get_temp_dir(), 'asis').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'a.csv', 'text/csv', null, true);
        $user = User::factory()->create()->assignRole('RRHH');

        $this->actingAs($user)->withSession(['empresa_id' => $this->empresa->id])
            ->post(route('asistencia.import'), ['archivo' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('attendance', 0);
    }
}
