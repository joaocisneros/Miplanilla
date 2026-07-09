<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\PlanillaService;
use App\Exports\PlanillaDetalleExport;
use App\Models\Empresa;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Periodo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Módulo Recibos por Honorarios (RxH). Sigue el mismo patrón que Planilla:
 * el índice lista PERIODOS (uno por empresa), y al entrar (Ver) se ve el
 * detalle por trabajador con su recibo individual. El cálculo (sueldo neto:
 * honorario por días + sábados + domingos/feriados − tardanzas − faltas, SIN
 * aportes, descuentos ni beneficios) lo hace el mismo motor de Planilla.
 */
class HonorarioController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;

        $periodos = Payroll::with(['periodo', 'empresa:id,razon_social,nombre_comercial', 'detalles'])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->get()
            ->map(function ($p) {
                // Modalidad congelada en el detalle, no la actual del empleado.
                $dets = $p->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') === 'honorarios');
                if ($dets->isEmpty()) {
                    return null;
                }

                return [
                    'payroll_id' => $p->id,
                    'descripcion' => $p->periodo->descripcion,
                    'empresa' => $p->empresa->nombre_comercial ?? $p->empresa->razon_social,
                    'fecha_inicio' => $p->periodo->fecha_inicio->toDateString(),
                    'fecha_fin' => $p->periodo->fecha_fin->toDateString(),
                    'estado' => $p->estado,
                    'cantidad_empleados' => $dets->count(),
                    'total_neto' => round($dets->sum('neto'), 2),
                ];
            })
            ->filter()
            ->sortByDesc(fn ($r) => $r['fecha_inicio'])
            ->values();

        return Inertia::render('Honorarios/Index', [
            'periodos' => $periodos,
            'filtros' => ['empresa_id' => $empresaId],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    /** Detalle por trabajador de una planilla (payroll) concreta, solo honorarios. */
    public function show(Payroll $payroll): Response
    {
        $payroll->load(['periodo', 'empresa:id,razon_social', 'detalles.employee']);

        $filas = $this->filasDe($payroll);

        return Inertia::render('Honorarios/Show', [
            'payroll' => [
                'id' => $payroll->id,
                'descripcion' => $payroll->periodo->descripcion,
                'empresa' => $payroll->empresa->razon_social,
                'estado' => $payroll->estado,
                'total_neto' => round($filas->sum('neto'), 2),
                'cantidad_empleados' => $filas->count(),
            ],
            'filas' => $filas,
        ]);
    }

    /**
     * Genera (o recalcula) los honorarios del periodo indicado, creando la planilla
     * en cada empresa que tenga trabajadores activos por honorarios. Es independiente
     * del módulo Planilla: el cliente no necesita entrar ahí para generar RxH.
     */
    public function generar(Request $request, PlanillaService $service)
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'fecha_pago' => ['nullable', 'date'],
        ]);

        $empresas = Empresa::where('activo', true)
            ->whereHas('empleados', fn ($q) => $q->where('modalidad', 'honorarios')->where('activo', true))
            ->get();

        if ($empresas->isEmpty()) {
            return back()->with('error', 'No hay trabajadores activos por honorarios en ninguna empresa.');
        }

        $generadas = 0;
        foreach ($empresas as $empresa) {
            $periodo = Periodo::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'anio' => $data['anio'],
                    'mes' => $data['mes'],
                    'quincena' => $data['quincena'] ?? null,
                ],
                $data
            );

            if ($periodo->estado === 'cerrado') {
                continue;
            }

            $service->generar($periodo, $request->user()->id);
            $periodo->update(['estado' => 'calculado']);
            $generadas++;
        }

        return back()->with('success', "Honorarios generados/recalculados en {$generadas} empresa(s).");
    }

    /**
     * Recalcula una planilla (payroll) puntual desde la lista de Honorarios.
     * Nota: recalcula TODO el periodo (planilla + honorarios), porque ambos
     * viven en el mismo payroll; no existe un recálculo "solo honorarios".
     */
    public function recalcular(Request $request, Payroll $payroll, PlanillaService $service)
    {
        abort_if($payroll->estado === 'cerrado', 403, 'El periodo está cerrado.');

        $service->generar($payroll->periodo, $request->user()->id);
        $payroll->periodo->update(['estado' => 'calculado']);

        return back()->with('success', 'Honorarios recalculados.');
    }

    /** Exporta a Excel el detalle de honorarios de una planilla (payroll) concreta. */
    public function export(Payroll $payroll)
    {
        $payroll->load(['periodo', 'empresa:id,razon_social', 'detalles.employee']);
        $filas = $this->filasDe($payroll);

        $headings = ['N°', 'DNI', 'Apellidos y Nombres', 'Días trab.', 'Faltas', 'Tardanza (min)',
            'Honorario', 'Sábados', 'Dom/Fer', 'NETO A PAGAR'];
        $rows = [];
        $i = 1;
        foreach ($filas as $f) {
            $rows[] = [$i++, $f['dni'], $f['nombre'], $f['dias'], $f['faltas'], $f['tardanza_min'],
                $f['honorario'], $f['sabado'], $f['domingo'], $f['neto']];
        }
        $moneyCols = [7, 8, 9, 10];
        $nombre = 'honorarios_'.\Illuminate\Support\Str::slug($payroll->empresa->razon_social).'_'.\Illuminate\Support\Str::slug($payroll->periodo->descripcion).'.xlsx';

        return Excel::download(new PlanillaDetalleExport($headings, $rows, $moneyCols, 10, 'D2'), $nombre);
    }

    /** Descarga el recibo por honorarios (PDF) de un trabajador. */
    public function recibo(Request $request, PayrollDetail $detalle)
    {
        abort_unless(($detalle->modalidad ?? 'planilla') === 'honorarios', 404);

        $pdf = $this->generarReciboPdf($detalle);

        return $pdf->download($this->nombreRecibo($detalle));
    }

    /** Descarga TODOS los recibos por honorarios de una planilla (payroll) en un ZIP. */
    public function reciboZip(Payroll $payroll)
    {
        $payroll->load(['periodo', 'empresa:id,razon_social', 'detalles.employee']);

        $detalles = $payroll->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') === 'honorarios');

        if ($detalles->isEmpty()) {
            return back()->with('error', 'No hay recibos por honorarios para descargar en este periodo.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'recibos_').'.zip';
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($detalles as $detalle) {
            $pdf = $this->generarReciboPdf($detalle);
            $zip->addFromString($this->nombreRecibo($detalle), $pdf->output());
        }
        $zip->close();

        $slug = \Illuminate\Support\Str::slug($payroll->empresa->razon_social);
        $nombre = "recibos_honorarios_{$slug}_{$payroll->periodo->anio}_".str_pad((string) $payroll->periodo->mes, 2, '0', STR_PAD_LEFT).'.zip';

        return response()->download($tmp, $nombre)->deleteFileAfterSend(true);
    }

    /** Arma las filas (solo honorarios) de una planilla ya cargada. */
    private function filasDe(Payroll $payroll)
    {
        return $payroll->detalles
            ->filter(fn ($d) => ($d->modalidad ?? 'planilla') === 'honorarios')
            ->map(function ($d) {
                $g = (array) $d->desglose;
                $ing = $g['ingresos'] ?? [];
                $asis = $g['asistencia'] ?? [];

                return [
                    'id' => $d->id,
                    'dni' => (string) $d->employee?->numero_documento,
                    'nombre' => $d->employee?->nombre_completo,
                    'dias' => $asis['dias_trabajados'] ?? 0,
                    'faltas' => $asis['faltas'] ?? 0,
                    'tardanza_min' => $asis['minutos_tarde'] ?? 0,
                    'honorario' => round((float) ($ing['remuneracion_devengada'] ?? 0), 2),
                    'sabado' => round((float) ($ing['sabado'] ?? 0), 2),
                    'domingo' => round((float) ($ing['domingo_feriado'] ?? 0), 2),
                    'neto' => round((float) $d->neto, 2),
                    'desglose' => $g,
                ];
            })->sortBy('nombre')->values();
    }

    private function generarReciboPdf(PayrollDetail $detalle): \Barryvdh\DomPDF\PDF
    {
        $detalle->loadMissing([
            'employee.contratoVigente.cargo:id,nombre',
            'employee.contratoVigente.area:id,nombre',
            'payroll.empresa', 'payroll.periodo',
        ]);

        return Pdf::loadView('honorarios.recibo', [
            'd' => $detalle,
            'emp' => $detalle->employee,
            'contrato' => $detalle->employee->contratoVigente->first(),
            'empresa' => $detalle->payroll->empresa,
            'periodo' => $detalle->payroll->periodo,
            'desglose' => $detalle->desglose,
        ]);
    }

    private function nombreRecibo(PayrollDetail $detalle): string
    {
        return 'recibo_honorarios_'.$detalle->employee->numero_documento.'_'.$detalle->payroll->periodo->anio.'_'.$detalle->payroll->periodo->mes.'.pdf';
    }
}
