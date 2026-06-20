<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        $payrolls = Payroll::with(['empresa:id,razon_social', 'periodo'])
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->get();

        $porEmpresa = $payrolls->groupBy('empresa_id')->map(function ($grupo) {
            $emp = $grupo->first()->empresa;
            return [
                'empresa' => $emp->razon_social,
                'cantidad_empleados' => $grupo->sum('cantidad_empleados'),
                'total_ingresos' => round($grupo->sum('total_ingresos'), 2),
                'total_descuentos' => round($grupo->sum('total_descuentos'), 2),
                'total_neto' => round($grupo->sum('total_neto'), 2),
                'total_aportes_empleador' => round($grupo->sum('total_aportes_empleador'), 2),
            ];
        })->values();

        $totalGeneral = [
            'cantidad_empleados' => $porEmpresa->sum('cantidad_empleados'),
            'total_ingresos' => round($porEmpresa->sum('total_ingresos'), 2),
            'total_descuentos' => round($porEmpresa->sum('total_descuentos'), 2),
            'total_neto' => round($porEmpresa->sum('total_neto'), 2),
            'total_aportes_empleador' => round($porEmpresa->sum('total_aportes_empleador'), 2),
        ];

        return Inertia::render('Reportes/Consolidado', [
            'porEmpresa' => $porEmpresa,
            'totalGeneral' => $totalGeneral,
            'filtros' => ['anio' => $anio, 'mes' => $mes],
        ]);
    }
}
