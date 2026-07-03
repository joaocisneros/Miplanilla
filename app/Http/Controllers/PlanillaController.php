<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\PlanillaService;
use App\Models\Empresa;
use App\Models\Payroll;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanillaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;

        $periodos = Periodo::with(['empresa:id,razon_social,nombre_comercial'])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('quincena')
            ->get()
            ->map(function ($p) {
                $payroll = Payroll::where('periodo_id', $p->id)->first();
                return [
                    'id' => $p->id,
                    'descripcion' => $p->descripcion,
                    'empresa' => $p->empresa?->nombre_comercial ?? $p->empresa?->razon_social,
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

        return Inertia::render('Planilla/Index', [
            'periodos' => $periodos,
            'filtros' => ['empresa_id' => $empresaId],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    public function storePeriodo(Request $request)
    {
        $data = $this->validarPeriodo($request, conEmpresa: true);

        Periodo::create($data);

        return back()->with('success', 'Periodo creado.');
    }

    /**
     * Crea (si no existe) el mismo periodo en TODAS las empresas y genera cada
     * planilla por separado. Agiliza la operación sin mezclar empresas:
     * cada una conserva su planilla independiente (SUNAT/SUNAFIL por separado).
     */
    public function generarTodas(Request $request, PlanillaService $service)
    {
        $data = $this->validarPeriodo($request);

        $empresas = Empresa::where('activo', true)->get();
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

        return back()->with('success', "Planillas generadas en {$generadas} empresa(s), cada una por separado.");
    }

    public function generar(Request $request, Periodo $periodo, PlanillaService $service)
    {
        abort_if($periodo->estado === 'cerrado', 403, 'El periodo está cerrado.');

        $service->generar($periodo, $request->user()->id);
        $periodo->update(['estado' => 'calculado']);

        return back()->with('success', 'Planilla generada.');
    }

    public function show(Request $request, Payroll $payroll): Response
    {
        $payroll->load(['periodo', 'empresa', 'detalles.employee:id,apellido_paterno,apellido_materno,nombres', 'detalles.employee.contratoVigente']);

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
            'detalles' => $payroll->detalles->map(function ($d) {
                $c = $d->employee?->contratoVigente->first();
                $sistema = $c?->sistema_pensiones === 'AFP'
                    ? 'AFP '.($c->afp ?? '')
                    : ($c?->sistema_pensiones ?? '—');

                return [
                    'id' => $d->id,
                    'empleado' => $d->employee?->nombre_completo,
                    'sistema' => trim($sistema),
                    'base_afecta' => $d->base_afecta,
                    'total_ingresos' => $d->total_ingresos,
                    'total_descuentos' => $d->total_descuentos,
                    'pension_total' => $d->pension_total,
                    'renta_5ta' => $d->renta_5ta,
                    'rem_neta_quincenal' => $d->desglose['bloques']['remuneracion_neta_quincenal'] ?? null,
                    'total_movilidad' => $d->desglose['bloques']['total_movilidad_quincenal'] ?? null,
                    'neto' => $d->neto,
                    'desglose' => $d->desglose,
                ];
            }),
        ]);
    }

    public function cerrar(Request $request, Payroll $payroll)
    {
        $payroll->update(['estado' => 'cerrado', 'cerrado_at' => now()]);
        $payroll->periodo->update(['estado' => 'cerrado']);

        return back()->with('success', 'Planilla cerrada. Ya no se puede recalcular.');
    }

    private function validarPeriodo(Request $request, bool $conEmpresa = false): array
    {
        $reglas = [
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'fecha_pago' => ['nullable', 'date'],
        ];
        if ($conEmpresa) {
            $reglas['empresa_id'] = ['required', 'exists:empresas,id'];
        }

        return $request->validate($reglas);
    }
}
