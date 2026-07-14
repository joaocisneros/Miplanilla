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

        $this->actingAs($user)
            ->post(route('asistencia.import'), ['empresa_id' => $this->empresa->id, 'archivo' => $file])
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

    /** La plantilla viene pre-llenada con todo el año: solo debe entrar el mes elegido. */
    public function test_plantilla_importa_solo_el_mes_elegido(): void
    {
        $emp = Employee::create([
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'CISNEROS', 'nombres' => 'KEVIN',
            'tipo_documento' => 'DNI', 'numero_documento' => '71246290',
        ]);

        $csv = "EMPRESA,DNI,NOMBRE,FECHA,DIA,ESTADO,ENTRADA,SALIDA,HE APROB,OBSERVACION,MODALIDAD\n"
            ."ACS,71246290,CISNEROS KEVIN,15/05/2026,Vie,Presente,07:00,18:00,,,PLANILLA\n"     // mayo: fuera del mes
            ."ACS,71246290,CISNEROS KEVIN,02/06/2026,Mar,Presente,08:00,18:00,,,PLANILLA\n"     // junio: entra
            ."ACS,71246290,CISNEROS KEVIN,03/06/2026,Mie,Falta,,,,,PLANILLA\n"                  // junio: entra
            ."ACS,71246290,CISNEROS KEVIN,01/12/2026,Mar,Presente,07:00,18:00,,,PLANILLA\n";    // futuro: jamas
        $path = tempnam(sys_get_temp_dir(), 'asis').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'ASISTENCIA_2026.csv', 'text/csv', null, true);

        $user = User::factory()->create()->assignRole('RRHH');
        $this->actingAs($user)
            ->post(route('asistencia.import-mensual'), ['archivo' => $file, 'mes' => 6])
            ->assertRedirect();

        $this->assertDatabaseCount('attendance', 2); // SOLO los 2 dias de junio
        $this->assertTrue(\App\Models\Attendance::whereDate('fecha', '2026-06-02')
            ->where('employee_id', $emp->id)->where('estado', 'NORMAL')->exists());
        $this->assertTrue(\App\Models\Attendance::whereDate('fecha', '2026-06-03')
            ->where('employee_id', $emp->id)->where('estado', 'FALTA')->exists());
        $this->assertFalse(\App\Models\Attendance::whereDate('fecha', '2026-05-15')->exists());
        $this->assertFalse(\App\Models\Attendance::whereDate('fecha', '2026-12-01')->exists());
    }

    /** Sin mes elegido ("todo el año"): entra lo pasado, pero lo futuro jamas. */
    public function test_plantilla_ignora_fechas_futuras(): void
    {
        Employee::create([
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'CISNEROS', 'nombres' => 'KEVIN',
            'tipo_documento' => 'DNI', 'numero_documento' => '71246290',
        ]);

        $csv = "EMPRESA,DNI,NOMBRE,FECHA,DIA,ESTADO,ENTRADA,SALIDA,HE APROB,OBSERVACION\n"
            ."ACS,71246290,CISNEROS KEVIN,15/05/2026,Vie,Presente,07:00,18:00,,\n"   // pasado: entra
            ."ACS,71246290,CISNEROS KEVIN,01/12/2026,Mar,Presente,07:00,18:00,,\n";  // futuro: no
        $path = tempnam(sys_get_temp_dir(), 'asis').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'ASISTENCIA_2026.csv', 'text/csv', null, true);

        $user = User::factory()->create()->assignRole('RRHH');
        $this->actingAs($user)
            ->post(route('asistencia.import-mensual'), ['archivo' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('attendance', 1);
        $this->assertTrue(\App\Models\Attendance::whereDate('fecha', '2026-05-15')->exists());
        $this->assertFalse(\App\Models\Attendance::whereDate('fecha', '2026-12-01')->exists());
    }

    public function test_dni_inexistente_no_rompe_la_importacion(): void
    {
        $csv = "dni,fecha,estado\n00000000,2026-06-02,NORMAL\n";
        $path = tempnam(sys_get_temp_dir(), 'asis').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'a.csv', 'text/csv', null, true);
        $user = User::factory()->create()->assignRole('RRHH');

        $this->actingAs($user)
            ->post(route('asistencia.import'), ['empresa_id' => $this->empresa->id, 'archivo' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('attendance', 0);
    }
}
