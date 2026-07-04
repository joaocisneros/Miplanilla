<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaExport;
use App\Models\Empresa;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte consolidado: suma de los totales FINALES de cada empresa
 * (cada una se calcula independiente; aquí solo se agregan los totales).
 */
class ReporteController extends Controller
{
    public function consolidado(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        [$porEmpresa, $totalGeneral] = $this->datosConsolidado($anio, $mes);

        return Inertia::render('Reportes/Consolidado', [
            'porEmpresa' => $porEmpresa,
            'totalGeneral' => $totalGeneral,
            'filtros' => ['anio' => $anio, 'mes' => $mes],
        ]);
    }

    /** Exporta el consolidado (con gastos y costo total) a Excel. */
    public function consolidadoExport(Request $request)
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        [$porEmpresa, $totalGeneral] = $this->datosConsolidado($anio, $mes);

        $headings = ['Empresa', 'Empleados', 'Ingresos', 'Descuentos', 'Neto a pagar',
            'EsSalud', 'SCTR', 'Vida Ley', 'SENATI', 'Aportes empleador', 'COSTO TOTAL'];
        $rows = $porEmpresa->map(fn ($e) => [
            $e['empresa'], $e['cantidad_empleados'], $e['total_ingresos'], $e['total_descuentos'],
            $e['total_neto'], $e['essalud'], $e['sctr'], $e['vida_ley'], $e['senati'],
            $e['total_aportes_empleador'], $e['costo_total'],
        ])->values()->all();
        $rows[] = ['TOTAL GENERAL', $totalGeneral['cantidad_empleados'], $totalGeneral['total_ingresos'],
            $totalGeneral['total_descuentos'], $totalGeneral['total_neto'], $totalGeneral['essalud'],
            $totalGeneral['sctr'], $totalGeneral['vida_ley'], $totalGeneral['senati'],
            $totalGeneral['total_aportes_empleador'], $totalGeneral['costo_total']];

        return Excel::download(new PlantillaExport($headings, $rows), "consolidado_{$anio}_{$mes}.xlsx");
    }

    /**
     * Calcula el consolidado por empresa con el desglose de gastos del empleador
     * (EsSalud, SCTR, Vida Ley, SENATI) y el COSTO TOTAL real de la planilla.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function datosConsolidado(int $anio, int $mes): array
    {
        $payrolls = Payroll::with(['empresa:id,razon_social', 'periodo', 'detalles'])
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->get();

        $porEmpresa = $payrolls->groupBy('empresa_id')->map(function ($grupo) {
            $emp = $grupo->first()->empresa;
            $det = $grupo->flatMap->detalles;
            $sum = fn ($col) => round($det->sum(fn ($d) => (float) $d->$col), 2);

            $ingresos = $sum('total_ingresos');
            $essalud = $sum('essalud');
            $sctr = round($sum('sctr_pension') + $sum('sctr_salud'), 2);
            $vida = $sum('vida_ley');
            $senati = $sum('senati');
            $aportes = round($essalud + $sctr + $vida + $senati, 2);

            return [
                'empresa' => $emp->razon_social,
                // Trabajadores ÚNICOS (un trabajador cuenta una vez, no por quincena).
                'cantidad_empleados' => $det->pluck('employee_id')->unique()->count(),
                'total_ingresos' => $ingresos,
                'total_descuentos' => $sum('total_descuentos'),
                'total_neto' => $sum('neto'),
                'essalud' => $essalud,
                'sctr' => $sctr,
                'vida_ley' => $vida,
                'senati' => $senati,
                'total_aportes_empleador' => $aportes,
                // Lo que REALMENTE le cuesta la planilla a la empresa: bruto + aportes.
                'costo_total' => round($ingresos + $aportes, 2),
            ];
        })->values();

        $suma = fn ($c) => round($porEmpresa->sum($c), 2);
        $totalGeneral = [
            'cantidad_empleados' => $porEmpresa->sum('cantidad_empleados'),
            'total_ingresos' => $suma('total_ingresos'),
            'total_descuentos' => $suma('total_descuentos'),
            'total_neto' => $suma('total_neto'),
            'essalud' => $suma('essalud'),
            'sctr' => $suma('sctr'),
            'vida_ley' => $suma('vida_ley'),
            'senati' => $suma('senati'),
            'total_aportes_empleador' => $suma('total_aportes_empleador'),
            'costo_total' => $suma('costo_total'),
        ];

        return [$porEmpresa, $totalGeneral];
    }

    /**
     * Reporte de tributos y aportes por empresa y mes: cuánto se declara/paga
     * a SUNAT (PLAME), a la AFP (AFPnet) y a las aseguradoras (SCTR / Vida Ley).
     * Cada empresa se presenta por separado (SUNAT/SUNAFIL audita independiente).
     */
    public function tributos(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);

        $payrolls = Payroll::with(['empresa:id,razon_social,ruc', 'detalles', 'periodo'])
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->get();

        $porEmpresa = $payrolls->groupBy('empresa_id')->map(function ($grupo) {
            return $this->resumirEmpresa($grupo->first()->empresa, $grupo->flatMap->detalles);
        })->values();

        $totalGeneral = [
            'base_imponible' => round($porEmpresa->sum('base_imponible'), 2),
            'essalud' => round($porEmpresa->sum('essalud'), 2),
            'onp' => round($porEmpresa->sum('onp'), 2),
            'renta_5ta' => round($porEmpresa->sum('renta_5ta'), 2),
            'senati' => round($porEmpresa->sum('senati'), 2),
            'total_sunat' => round($porEmpresa->sum('total_sunat'), 2),
            'afp_total' => round($porEmpresa->sum('afp_total'), 2),
            'sctr_total' => round($porEmpresa->sum('sctr_total'), 2),
            'vida_ley' => round($porEmpresa->sum('vida_ley'), 2),
            'total_seguros' => round($porEmpresa->sum('total_seguros'), 2),
        ];

        return Inertia::render('Reportes/Tributos', [
            'porEmpresa' => $porEmpresa,
            'totalGeneral' => $totalGeneral,
            'filtros' => ['anio' => $anio, 'mes' => $mes],
        ]);
    }

    /** Agrega los detalles de una empresa en los buckets de tributos/aportes. */
    private function resumirEmpresa(Empresa $empresa, $detalles): array
    {
        $base = $essalud = $onp = $afp = $renta = $sctrP = $sctrS = $vida = $senati = 0.0;
        $nOnp = $nAfp = 0;
        $afpPorAfp = [];

        foreach ($detalles as $d) {
            $base += (float) $d->base_afecta;
            $essalud += (float) $d->essalud;
            $renta += (float) $d->renta_5ta;
            $sctrP += (float) $d->sctr_pension;
            $sctrS += (float) $d->sctr_salud;
            $vida += (float) $d->vida_ley;
            $senati += (float) $d->senati;

            $pen = $d->desglose['descuentos']['pension'] ?? [];
            $sistema = $pen['detalle']['sistema'] ?? null;

            if ($sistema === 'AFP') {
                $afp += (float) $d->pension_total;
                $nAfp++;
                $nombre = $pen['detalle']['afp'] ?? 'AFP';
                $afpPorAfp[$nombre] ??= ['afp' => $nombre, 'aporte' => 0.0, 'comision' => 0.0, 'prima' => 0.0, 'total' => 0.0, 'empleados' => 0];
                $afpPorAfp[$nombre]['aporte'] += (float) ($pen['aporte'] ?? 0);
                $afpPorAfp[$nombre]['comision'] += (float) ($pen['comision'] ?? 0);
                $afpPorAfp[$nombre]['prima'] += (float) ($pen['prima'] ?? 0);
                $afpPorAfp[$nombre]['total'] += (float) $d->pension_total;
                $afpPorAfp[$nombre]['empleados']++;
            } else {
                $onp += (float) $d->pension_total;
                $nOnp++;
            }
        }

        $r = fn ($v) => round($v, 2);
        $sctrTotal = $sctrP + $sctrS;
        $totalSunat = $essalud + $onp + $renta + $senati;

        return [
            'empresa_id' => $empresa->id,
            'empresa' => $empresa->razon_social,
            'ruc' => $empresa->ruc,
            'empleados' => $detalles->pluck('employee_id')->unique()->count(),
            'empleados_onp' => $nOnp,
            'empleados_afp' => $nAfp,
            'base_imponible' => $r($base),
            // --- SUNAT (PLAME) ---
            'essalud' => $r($essalud),
            'onp' => $r($onp),
            'renta_5ta' => $r($renta),
            'senati' => $r($senati),
            'total_sunat' => $r($totalSunat),
            // --- AFP (AFPnet) ---
            'afp_total' => $r($afp),
            'afp_detalle' => array_map(fn ($a) => array_merge($a, [
                'aporte' => $r($a['aporte']), 'comision' => $r($a['comision']),
                'prima' => $r($a['prima']), 'total' => $r($a['total']),
            ]), array_values($afpPorAfp)),
            // --- Seguros (pago a aseguradora) ---
            'sctr_pension' => $r($sctrP),
            'sctr_salud' => $r($sctrS),
            'sctr_total' => $r($sctrTotal),
            'vida_ley' => $r($vida),
            'total_seguros' => $r($sctrTotal + $vida),
        ];
    }

    /**
     * Exporta el detalle por trabajador en formato CSV compatible con la
     * Planilla Electrónica (PLAME): una fila por trabajador con su base
     * imponible, tributos y aportes. Pensado para cargar/transcribir en SUNAT.
     */
    public function plame(Request $request): StreamedResponse
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        $empresaId = (int) $request->input('empresa_id');

        $empresa = Empresa::findOrFail($empresaId);

        $detalles = Payroll::where('empresa_id', $empresaId)
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->with(['detalles.employee:id,nombres,apellido_paterno,apellido_materno,numero_documento'])
            ->get()
            ->flatMap->detalles;

        $headers = [
            'DNI', 'Trabajador', 'Sistema pensión', 'AFP', 'Base imponible',
            'EsSalud (9%)', 'ONP', 'AFP aporte', 'AFP comisión', 'AFP prima',
            'Renta 5ta', 'SCTR pensión', 'SCTR salud', 'Vida Ley', 'SENATI', 'Neto',
        ];

        $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($empresa->razon_social));
        $nombre = "plame_{$slug}_{$anio}_".str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.csv';

        // Una fila por trabajador por mes (PLAME es mensual): si hay varias
        // quincenas en el mes, se suman los montos del mismo trabajador.
        $porTrabajador = $detalles->groupBy('employee_id')->map(function ($filas) {
            $emp = $filas->first()->employee;
            $pen0 = $filas->first()->desglose['descuentos']['pension'] ?? [];
            $sistema = $pen0['detalle']['sistema'] ?? 'ONP';
            $afp = $sistema === 'AFP' ? ($pen0['detalle']['afp'] ?? '') : '';

            $sum = fn ($cb) => $filas->sum($cb);
            $sumPen = fn ($k) => $filas->sum(fn ($d) => (float) ($d->desglose['descuentos']['pension'][$k] ?? 0));

            return [
                'dni' => $emp?->numero_documento,
                'nombre' => $emp?->nombre_completo,
                'sistema' => $sistema,
                'afp' => $afp,
                'base' => $sum(fn ($d) => (float) $d->base_afecta),
                'essalud' => $sum(fn ($d) => (float) $d->essalud),
                'onp' => $sistema === 'ONP' ? $sum(fn ($d) => (float) $d->pension_total) : 0.0,
                'afp_aporte' => $sumPen('aporte'),
                'afp_comision' => $sumPen('comision'),
                'afp_prima' => $sumPen('prima'),
                'renta_5ta' => $sum(fn ($d) => (float) $d->renta_5ta),
                'sctr_pension' => $sum(fn ($d) => (float) $d->sctr_pension),
                'sctr_salud' => $sum(fn ($d) => (float) $d->sctr_salud),
                'vida_ley' => $sum(fn ($d) => (float) $d->vida_ley),
                'senati' => $sum(fn ($d) => (float) $d->senati),
                'neto' => $sum(fn ($d) => (float) $d->neto),
            ];
        })->sortBy('nombre')->values();

        return response()->streamDownload(function () use ($porTrabajador, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete acentos
            fputcsv($out, $headers, ';');

            $n2 = fn ($v) => number_format((float) $v, 2, '.', '');
            foreach ($porTrabajador as $t) {
                fputcsv($out, [
                    $t['dni'], $t['nombre'], $t['sistema'], $t['afp'],
                    $n2($t['base']), $n2($t['essalud']), $n2($t['onp']),
                    $n2($t['afp_aporte']), $n2($t['afp_comision']), $n2($t['afp_prima']),
                    $n2($t['renta_5ta']), $n2($t['sctr_pension']), $n2($t['sctr_salud']),
                    $n2($t['vida_ley']), $n2($t['senati']), $n2($t['neto']),
                ], ';');
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
