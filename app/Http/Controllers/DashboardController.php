<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Empresa;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Periodo;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $empresas = Empresa::orderBy('id')->get(['id', 'razon_social', 'nombre_comercial']);
        $nombre = fn ($e) => $e->nombre_comercial ?: $e->razon_social;

        // --- Trabajadores por empresa ---
        $porEmpresa = Employee::where('activo', true)
            ->select('empresa_id', DB::raw('count(*) as n'))->groupBy('empresa_id')->pluck('n', 'empresa_id');
        $totalTrab = (int) $porEmpresa->sum();

        // --- Último mes con planillas generadas ---
        $ultPer = Periodo::whereNotNull('quincena')
            ->whereIn('id', Payroll::pluck('periodo_id'))
            ->orderByDesc('anio')->orderByDesc('mes')->first();

        $mesLabel = '—';
        $planillaNeto = 0.0;
        $aportes = 0.0;
        $costoPorEmpresa = [];

        if ($ultPer) {
            $mesLabel = $meses[$ultPer->mes].' '.$ultPer->anio;
            $periodoIds = Periodo::where('anio', $ultPer->anio)->where('mes', $ultPer->mes)
                ->whereNotNull('quincena')->pluck('id');
            $pays = Payroll::whereIn('periodo_id', $periodoIds)->get(['id', 'empresa_id']);
            $payEmp = $pays->pluck('empresa_id', 'id');
            $detalles = PayrollDetail::whereIn('payroll_id', $pays->pluck('id'))->get();

            foreach ($detalles as $d) {
                $planillaNeto += (float) $d->neto;
                $aportes += (float) $d->essalud + (float) $d->sctr_pension + (float) $d->sctr_salud + (float) $d->vida_ley + (float) $d->senati;
                $emp = $payEmp[$d->payroll_id] ?? null;
                if ($emp !== null) {
                    $costoPorEmpresa[$emp] = ($costoPorEmpresa[$emp] ?? 0) + (float) $d->neto;
                }
            }
        }

        // --- AFP vs ONP (contratos activos) ---
        $pension = DB::table('contracts')->where('activo', true)
            ->select('sistema_pensiones', DB::raw('count(*) as n'))
            ->groupBy('sistema_pensiones')->pluck('n', 'sistema_pensiones');
        $pensionLabels = [];
        $pensionData = [];
        foreach ($pension as $sis => $n) {
            $pensionLabels[] = $sis ?: 'Sin definir';
            $pensionData[] = (int) $n;
        }

        // --- Asistencia (tardanzas y faltas registradas) ---
        $tardanzas = DB::table('attendance')->where('minutos_tarde', '>', 0)->count();
        $faltas = DB::table('attendance')->where('estado', 'like', 'FALTA%')->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_trabajadores' => $totalTrab,
                'planilla_neto' => round($planillaNeto, 2),
                'aportes_empleador' => round($aportes, 2),
                'tardanzas' => $tardanzas,
                'faltas' => $faltas,
                'mes_label' => $mesLabel,
                'empresas' => $empresas->count(),
            ],
            'charts' => [
                'trabajadoresPorEmpresa' => [
                    'labels' => $empresas->map($nombre)->values(),
                    'data' => $empresas->map(fn ($e) => (int) ($porEmpresa[$e->id] ?? 0))->values(),
                ],
                'costoPorEmpresa' => [
                    'labels' => $empresas->map($nombre)->values(),
                    'data' => $empresas->map(fn ($e) => round($costoPorEmpresa[$e->id] ?? 0, 2))->values(),
                ],
                'pension' => [
                    'labels' => $pensionLabels,
                    'data' => $pensionData,
                ],
            ],
        ]);
    }
}
