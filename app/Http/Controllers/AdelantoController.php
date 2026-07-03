<?php

namespace App\Http\Controllers;

use App\Models\Adelanto;
use App\Models\Empresa;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Adelantos y préstamos al trabajador. Se descuentan automáticamente del neto
 * en la planilla del mes correspondiente:
 *  - Adelanto: un solo descuento en el mes indicado.
 *  - Préstamo: se divide en N cuotas mensuales consecutivas.
 */
class AdelantoController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id');

        $registros = collect();
        if ($empresaId) {
            $registros = Adelanto::with('employee:id,nombres,apellido_paterno,apellido_materno,numero_documento')
                ->where('empresa_id', $empresaId)
                ->orderByDesc('anio')->orderByDesc('mes')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'trabajador' => $a->employee?->nombre_completo,
                    'dni' => $a->employee?->numero_documento,
                    'tipo' => $a->tipo,
                    'anio' => $a->anio,
                    'mes' => $a->mes,
                    'monto' => (float) $a->monto,
                    'concepto' => $a->concepto,
                    'grupo' => $a->grupo,
                    'cuota' => $a->cuota_num ? "{$a->cuota_num}/{$a->cuotas_total}" : null,
                ]);
        }

        return Inertia::render('Adelantos/Index', [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']),
            'empleados' => $empresaId
                ? Employee::where('empresa_id', $empresaId)->where('activo', true)
                    ->orderBy('apellido_paterno')->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'numero_documento'])
                    ->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre_completo.' ('.$e->numero_documento.')'])
                : [],
            'registros' => $registros,
            'filtros' => ['empresa_id' => $empresaId ? (int) $empresaId : null],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'tipo' => ['required', 'in:adelanto,prestamo'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'cuotas' => ['required_if:tipo,prestamo', 'nullable', 'integer', 'min:1', 'max:60'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ]);

        $base = [
            'empresa_id' => $data['empresa_id'],
            'employee_id' => $data['employee_id'],
            'tipo' => $data['tipo'],
            'concepto' => $data['concepto'] ?? null,
            'registrado_por' => $request->user()?->id,
        ];

        if ($data['tipo'] === 'adelanto') {
            Adelanto::create([...$base, 'anio' => $data['anio'], 'mes' => $data['mes'], 'monto' => $data['monto']]);

            return back()->with('success', 'Adelanto registrado. Se descontará en la planilla del periodo indicado.');
        }

        // Préstamo: dividir en N cuotas mensuales consecutivas.
        $n = (int) $data['cuotas'];
        $cuota = round($data['monto'] / $n, 2);
        $grupo = (string) Str::uuid();
        $anio = (int) $data['anio'];
        $mes = (int) $data['mes'];

        DB::transaction(function () use ($n, $cuota, $data, $base, $grupo, &$anio, &$mes) {
            $acumulado = 0;
            for ($i = 1; $i <= $n; $i++) {
                // La última cuota ajusta el redondeo para cuadrar el total exacto.
                $montoCuota = $i === $n ? round($data['monto'] - $acumulado, 2) : $cuota;
                $acumulado += $montoCuota;

                Adelanto::create([
                    ...$base,
                    'anio' => $anio, 'mes' => $mes, 'monto' => $montoCuota,
                    'grupo' => $grupo, 'cuota_num' => $i, 'cuotas_total' => $n,
                ]);

                $mes++;
                if ($mes > 12) { $mes = 1; $anio++; }
            }
        });

        return back()->with('success', "Préstamo registrado en {$n} cuotas. Se descontarán automáticamente cada mes.");
    }

    public function destroy(Adelanto $adelanto)
    {
        $adelanto->delete();

        return back()->with('success', 'Registro eliminado.');
    }

    public function destroyGrupo(string $grupo)
    {
        Adelanto::where('grupo', $grupo)->delete();

        return back()->with('success', 'Préstamo completo eliminado (todas sus cuotas).');
    }
}
