<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Employee;
use App\Models\Vacacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vacaciones (D.Leg. 713): 30 días por año de servicios.
 * Se acumulan 2.5 días por mes completo trabajado. El pago vacacional
 * equivale a una remuneración por los 30 días (prorrateado por día tomado).
 */
class VacacionController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id');
        $empresas = Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']);

        $filas = collect();
        if ($empresaId) {
            $empleados = Employee::with('contratoVigente')
                ->where('empresa_id', $empresaId)->where('activo', true)->get();

            $gozadosPorEmp = Vacacion::where('empresa_id', $empresaId)
                ->selectRaw('employee_id, SUM(dias) as dias, COUNT(*) as registros')
                ->groupBy('employee_id')->get()->keyBy('employee_id');

            $filas = $empleados->map(function ($emp) use ($gozadosPorEmp) {
                $contrato = $emp->contratoVigente->first();
                $ingreso = $contrato?->fecha_ingreso ? Carbon::parse($contrato->fecha_ingreso) : null;
                $mesesServicio = $ingreso ? $ingreso->diffInMonths(now()) : 0;
                $ganados = (int) floor($mesesServicio * 2.5);
                $gozados = (int) ($gozadosPorEmp[$emp->id]->dias ?? 0);

                return [
                    'id' => $emp->id,
                    'dni' => $emp->numero_documento,
                    'trabajador' => $emp->nombre_completo,
                    'ingreso' => $ingreso?->toDateString(),
                    'meses_servicio' => $mesesServicio,
                    'dias_ganados' => $ganados,
                    'dias_gozados' => $gozados,
                    'saldo' => $ganados - $gozados,
                    'sueldo' => (float) ($contrato?->sueldo_basico ?? 0),
                ];
            })->sortBy('trabajador')->values();
        }

        return Inertia::render('Vacaciones/Index', [
            'empresas' => $empresas,
            'filas' => $filas,
            'historial' => $empresaId
                ? Vacacion::with('employee:id,nombres,apellido_paterno,apellido_materno')
                    ->where('empresa_id', $empresaId)->latest('fecha_inicio')->get()
                    ->map(fn ($v) => [
                        'id' => $v->id,
                        'trabajador' => $v->employee?->nombre_completo,
                        'fecha_inicio' => $v->fecha_inicio?->toDateString(),
                        'fecha_fin' => $v->fecha_fin?->toDateString(),
                        'dias' => $v->dias,
                        'monto' => (float) $v->monto,
                        'observacion' => $v->observacion,
                    ])
                : [],
            'filtros' => ['empresa_id' => $empresaId ? (int) $empresaId : null],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'dias' => ['required', 'integer', 'min:1', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $emp = Employee::with('contratoVigente')->findOrFail($data['employee_id']);
        $sueldo = (float) ($emp->contratoVigente->first()?->sueldo_basico ?? 0);
        $monto = round($sueldo / 30 * $data['dias'], 2);

        Vacacion::create([
            ...$data,
            'monto' => $monto,
            'registrado_por' => $request->user()?->id,
        ]);

        return back()->with('success', 'Vacaciones registradas ('.$data['dias'].' días, pago S/ '.number_format($monto, 2).').');
    }

    public function destroy(Vacacion $vacacion)
    {
        $vacacion->delete();

        return back()->with('success', 'Registro de vacaciones eliminado.');
    }
}
