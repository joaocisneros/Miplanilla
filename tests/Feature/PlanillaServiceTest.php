<?php

namespace Tests\Feature;

use App\Domain\Pensiones\CalculadoraPension;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\PlanillaService;
use App\Domain\Tributario\Renta5taService;
use App\Models\Attendance;
use App\Models\Adelanto;
use App\Models\AsistenciaResumen;
use App\Models\Contract;
use App\Models\Empresa;
use App\Models\Employee;
use App\Models\Periodo;
use Database\Seeders\MaestrosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanillaServiceTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MaestrosSeeder::class);
        $this->empresa = Empresa::create(['ruc' => '20100000001', 'razon_social' => 'ACS PERU']);
        $this->periodo = Periodo::create([
            'empresa_id' => $this->empresa->id, 'anio' => 2026, 'mes' => 6, 'quincena' => 1,
            'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-15',
        ]);
    }

    private function crearEmpleado(array $contrato): Employee
    {
        $emp = Employee::create([
            'empresa_id' => $this->empresa->id,
            'apellido_paterno' => 'TEST', 'nombres' => uniqid(),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
        ]);
        $emp->contratos()->create(array_merge([
            'categoria_ocupacional' => 'empleado', 'fecha_ingreso' => '2026-01-01', 'activo' => true,
        ], $contrato));

        return $emp;
    }

    private function service(): PlanillaService
    {
        return new PlanillaService(
            new CalculadoraPlanilla(new CalculadoraPension()),
            new Renta5taService()
        );
    }

    public function test_genera_planilla_con_afp_sin_asistencia_asume_periodo_completo(): void
    {
        $this->crearEmpleado([
            'sueldo_basico' => 4500, 'sistema_pensiones' => 'AFP', 'afp' => 'INTEGRA', 'tipo_afp' => 'mixta',
        ]);

        $payroll = $this->service()->generar($this->periodo);

        $this->assertEquals(1, $payroll->cantidad_empleados);
        $detalle = $payroll->detalles->first();
        // 15 días asumidos: base = (4500/30)*15 = 2250 ; neto = 2250 - 276.75 = 1973.25
        $this->assertEquals(2250.00, (float) $detalle->base_afecta);
        $this->assertEquals(1973.25, (float) $detalle->neto);
        $this->assertEquals(1973.25, (float) $payroll->total_neto);
    }

    public function test_descuenta_faltas_de_asistencia(): void
    {
        $emp = $this->crearEmpleado([
            'sueldo_basico' => 3000, 'sistema_pensiones' => 'ONP',
        ]);
        // 2 faltas en el periodo
        Attendance::create(['empresa_id' => $this->empresa->id, 'employee_id' => $emp->id, 'fecha' => '2026-06-02', 'estado' => 'FALTA']);
        Attendance::create(['empresa_id' => $this->empresa->id, 'employee_id' => $emp->id, 'fecha' => '2026-06-03', 'estado' => 'FALTA']);

        $payroll = $this->service()->generar($this->periodo);
        $detalle = $payroll->detalles->first();

        // dias_trabajados = 15 - 2 = 13 ; base = (3000/30)*13 = 1300
        $this->assertEquals(1300.00, (float) $detalle->base_afecta);
        // ONP 13% = 169 ; neto = 1300 - 169 = 1131
        $this->assertEquals(169.00, (float) $detalle->pension_total);
        $this->assertEquals(1131.00, (float) $detalle->neto);
    }

    public function test_empleado_sin_contrato_se_omite(): void
    {
        Employee::create([
            'empresa_id' => $this->empresa->id, 'apellido_paterno' => 'SIN', 'nombres' => 'CONTRATO',
            'tipo_documento' => 'DNI', 'numero_documento' => '88888888',
        ]);

        $payroll = $this->service()->generar($this->periodo);
        $this->assertEquals(0, $payroll->cantidad_empleados);
    }

    public function test_mensual_consolida_los_resumenes_de_ambas_quincenas(): void
    {
        $emp = $this->crearEmpleado(['sueldo_basico' => 3000, 'sistema_pensiones' => 'ONP']);
        foreach ([[1, 14, 1, 10], [2, 13, 2, 20]] as [$q, $dias, $faltas, $tarde]) {
            AsistenciaResumen::create([
                'empresa_id' => $this->empresa->id, 'employee_id' => $emp->id,
                'anio' => 2026, 'mes' => 6, 'quincena' => $q,
                'dias_trabajados' => $dias, 'faltas' => $faltas, 'tardanza_min' => $tarde,
            ]);
        }
        $mensual = Periodo::create([
            'empresa_id' => $this->empresa->id, 'anio' => 2026, 'mes' => 7, 'quincena' => null,
            'fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31',
        ]);
        // Se trasladan los resúmenes al mes del periodo mensual de prueba.
        AsistenciaResumen::query()->update(['mes' => 7]);

        $detalle = $this->service()->generar($mensual)->detalles->first();

        $this->assertEquals(27, $detalle->desglose['asistencia']['dias_trabajados']);
        $this->assertEquals(3, $detalle->desglose['asistencia']['faltas']);
        $this->assertEquals(30, $detalle->desglose['asistencia']['minutos_tarde']);
    }

    public function test_adelanto_mensual_solo_se_descuenta_en_segunda_quincena(): void
    {
        $emp = $this->crearEmpleado(['sueldo_basico' => 3000, 'sistema_pensiones' => 'ONP']);
        Adelanto::create([
            'empresa_id' => $this->empresa->id, 'employee_id' => $emp->id,
            'tipo' => 'adelanto', 'anio' => 2026, 'mes' => 6, 'monto' => 200,
        ]);
        $primera = $this->service()->generar($this->periodo)->detalles->first();
        $segundaPeriodo = Periodo::create([
            'empresa_id' => $this->empresa->id, 'anio' => 2026, 'mes' => 6, 'quincena' => 2,
            'fecha_inicio' => '2026-06-16', 'fecha_fin' => '2026-06-30',
        ]);
        $segunda = $this->service()->generar($segundaPeriodo)->detalles->first();

        $this->assertEquals(0, $primera->desglose['descuentos']['adelantos']);
        $this->assertEquals(200, $segunda->desglose['descuentos']['adelantos']);
    }
}
