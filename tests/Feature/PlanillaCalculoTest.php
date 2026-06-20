<?php

namespace Tests\Feature;

use App\Domain\Pensiones\CalculadoraPension;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Tributario\Renta5taService;
use Carbon\Carbon;
use Database\Seeders\MaestrosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriz de casos validada a mano (reglas CORRECTAS, no copia del Excel).
 * Ver ESPECIFICACION_PLANILLA.md §9–§10.
 */
class PlanillaCalculoTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MaestrosSeeder::class); // tasas AFP/ONP + tramos 5ta 2026
        $this->periodo = Carbon::parse('2026-06-15');
    }

    private function motor(): CalculadoraPlanilla
    {
        return new CalculadoraPlanilla(new CalculadoraPension());
    }

    /** Caso 1: AFP INTEGRA mixta, quincena completa, sin incidencias. */
    public function test_caso_afp_quincena_completa(): void
    {
        $r = $this->motor()->calcular([
            'sueldo_basico' => 4500, 'asignacion_familiar' => 0, 'dias_base' => 30,
            'dias_trabajados' => 15, 'minutos_tarde' => 0,
            'sistema_pensiones' => 'AFP', 'afp' => 'INTEGRA', 'tipo_afp' => 'mixta',
            'fecha_periodo' => $this->periodo,
        ]);

        // base = (4500/30)*15 = 2250
        $this->assertEquals(2250.00, $r['base_afecta']);
        // aporte 10% = 225 ; comisión 0.56% = 12.60 ; prima 1.74% = 39.15 ; total = 276.75
        $this->assertEquals(225.00, $r['descuentos']['pension']['aporte']);
        $this->assertEquals(12.60, $r['descuentos']['pension']['comision']);
        $this->assertEquals(39.15, $r['descuentos']['pension']['prima']);
        $this->assertEquals(276.75, $r['descuentos']['pension']['total']);
        // EsSalud 9% = 202.50
        $this->assertEquals(202.50, $r['aportes_empleador']['essalud']);
        // neto = 2250 - 276.75 = 1973.25
        $this->assertEquals(1973.25, $r['neto']);
    }

    /** Caso 2: ONP, 2 faltas (13 días), 4 horas extra al 25%, con asignación familiar. */
    public function test_caso_onp_con_faltas_y_horas_extra(): void
    {
        $r = $this->motor()->calcular([
            'sueldo_basico' => 1500, 'asignacion_familiar' => 113, 'dias_base' => 30,
            'dias_trabajados' => 13, 'minutos_tarde' => 0,
            'horas_extra_25' => 4,
            'sistema_pensiones' => 'ONP',
            'fecha_periodo' => $this->periodo,
        ]);

        // remBase=1613; jornal=53.7667; devengada=round(*13)=698.97
        // HE: valor_hora=6.72083; *1.25*4=33.60 ; base=732.57
        $this->assertEquals(33.60, $r['ingresos']['horas_extra']);
        $this->assertEquals(732.57, $r['base_afecta']);
        // ONP 13% = 95.23
        $this->assertEquals(95.23, $r['descuentos']['pension']['total']);
        // neto = 732.57 - 95.23 = 637.34
        $this->assertEquals(637.34, $r['neto']);
        // EsSalud 9% = 65.93
        $this->assertEquals(65.93, $r['aportes_empleador']['essalud']);
    }

    /** Caso 3: movilidad NO entra a la base afecta (es condición de trabajo). */
    public function test_movilidad_no_afecta_la_base(): void
    {
        $r = $this->motor()->calcular([
            'sueldo_basico' => 3000, 'movilidad' => 300, 'dias_base' => 30,
            'dias_trabajados' => 15, 'sistema_pensiones' => 'ONP',
            'fecha_periodo' => $this->periodo,
        ]);

        // base afecta solo sobre básico: (3000/30)*15 = 1500
        $this->assertEquals(1500.00, $r['base_afecta']);
        // movilidad prorrateada = (300/30)*15 = 150, va al neto pero no a la base
        $this->assertEquals(150.00, $r['ingresos']['movilidad']);
        // ONP sobre 1500 = 195
        $this->assertEquals(195.00, $r['descuentos']['pension']['total']);
        // neto = 1500 + 150 - 195 = 1455
        $this->assertEquals(1455.00, $r['neto']);
    }

    /** Renta 5ta: impuesto anual por escala progresiva. */
    public function test_renta_5ta_escala_progresiva(): void
    {
        $svc = new Renta5taService();
        // renta neta 30,000 con UIT 5,500:
        // 0..27500 *8% = 2200 ; 27500..30000 (2500) *14% = 350 ; total = 2550
        $this->assertEquals(2550.00, $svc->impuestoAnual(30000, 5500, $this->periodo));
        // renta neta <= 0 -> sin impuesto
        $this->assertEquals(0.0, $svc->impuestoAnual(0, 5500, $this->periodo));
    }

    /** Renta 5ta: retención mensual por proyección (método SUNAT). */
    public function test_renta_5ta_retencion_mensual(): void
    {
        $svc = new Renta5taService();
        // rem 10,000/mes, enero, 12 meses: proy=120000; -38500=81500
        // imp: 27500*8%=2200 ; (81500-27500)=54000*14%=7560 ; total=9760 ; /12 = 813.33
        $ret = $svc->retencionMensual(10000, 5500, 1, $this->periodo, 0, 0, 12);
        $this->assertEquals(813.33, $ret);
    }
}
