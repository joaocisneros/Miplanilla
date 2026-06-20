<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\PlanillaService;
use App\Models\Payroll;
use App\Models\Periodo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanillaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->session()->get('empresa_id');

        $periodos = Periodo::where('empresa_id', $empresaId)
            ->with(['empresa'])
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('quincena')
            ->get()
            ->map(function ($p) {
                $payroll = Payroll::where('periodo_id', $p->id)->first();
                return [
                    'id' => $p->id,
                    'descripcion' => $p->descripcion,
                    'fecha_inicio' => $p->fecha_inicio->toDateString(),
                    'fecha_fin' => $p->fecha_fin->toDateString(),
                    'estado' => $p->estado,
                    'payroll' => $payroll ? [
                        'id' => $payroll->id,
                        'estado' => $payroll->estado,
                        'total_neto' => $payroll->total_neto,
                        'cantidad_empleados' => $payroll->cantidad_empleados,
                    ] : null,
                ];
            });

        return Inertia::render('Planilla/Index', ['periodos' => $periodos]);
    }

    public function storePeriodo(Request $request)
    {
        $empresaId = $request->session()->get('empresa_id');
        abort_if(! $empresaId, 422, 'Selecciona una empresa activa.');

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'fecha_pago' => ['nullable', 'date'],
        ]);
        $data['empresa_id'] = $empresaId;

        Periodo::create($data);

        return back()->with('success', 'Periodo creado.');
    }

    public function generar(Request $request, Periodo $periodo, PlanillaService $service)
    {
        $this->autorizar($request, $periodo);
        abort_if($periodo->estado === 'cerrado', 403, 'El periodo está cerrado.');

        $service->generar($periodo, $request->user()->id);
        $periodo->update(['estado' => 'calculado']);

        return back()->with('success', 'Planilla generada.');
    }

    public function show(Request $request, Payroll $payroll): Response
    {
        abort_if((int) $payroll->empresa_id !== (int) $request->session()->get('empresa_id'), 403);

        $payroll->load(['periodo', 'empresa', 'detalles.employee:id,apellido_paterno,apellido_materno,nombres']);

        return Inertia::render('Planilla/Show', [
            'payroll' => [
                'id' => $payroll->id,
                'estado' => $payroll->estado,
                'descripcion' => $payroll->periodo->descripcion,
                'empresa' => $payroll->empresa->razon_social,
                'total_ingresos' => $payroll->total_ingresos,
                'total_descuentos' => $payroll->total_descuentos,
                'total_neto' => $payroll->total_neto,
                'total_aportes_empleador' => $payroll->total_aportes_empleador,
                'cantidad_empleados' => $payroll->cantidad_empleados,
            ],
            'detalles' => $payroll->detalles->map(fn ($d) => [
                'id' => $d->id,
                'empleado' => $d->employee?->nombre_completo,
                'base_afecta' => $d->base_afecta,
                'total_ingresos' => $d->total_ingresos,
                'pension_total' => $d->pension_total,
                'renta_5ta' => $d->renta_5ta,
                'neto' => $d->neto,
            ]),
        ]);
    }

    public function cerrar(Request $request, Payroll $payroll)
    {
        abort_if((int) $payroll->empresa_id !== (int) $request->session()->get('empresa_id'), 403);

        $payroll->update(['estado' => 'cerrado', 'cerrado_at' => now()]);
        $payroll->periodo->update(['estado' => 'cerrado']);

        return back()->with('success', 'Planilla cerrada. Ya no se puede recalcular.');
    }

    private function autorizar(Request $request, Periodo $periodo): void
    {
        abort_if((int) $periodo->empresa_id !== (int) $request->session()->get('empresa_id'), 403);
    }
}
