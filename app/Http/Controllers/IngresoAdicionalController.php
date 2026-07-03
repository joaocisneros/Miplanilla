<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Employee;
use App\Models\IngresoAdicional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ingresos adicionales aprobados por el supervisor (horas extra y bonos), por
 * trabajador y periodo. Son afectos y entran a la planilla al generarla.
 * Solo lo que se registra aquí = lo aprobado = lo que se paga.
 */
class IngresoAdicionalController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->integer('empresa_id') ?: null;
        $anio = $request->integer('anio') ?: (int) date('Y');
        $mes = $request->integer('mes') ?: (int) date('n');
        $quincena = $request->input('quincena');
        $quincena = ($quincena === '' || $quincena === null) ? null : (int) $quincena;

        $filas = collect();
        if ($empresaId) {
            $existentes = IngresoAdicional::where('empresa_id', $empresaId)
                ->where('anio', $anio)->where('mes', $mes)
                ->where('quincena', $quincena)
                ->get()->keyBy('employee_id');

            $filas = Employee::with('contratoVigente.cargo:id,nombre')
                ->where('empresa_id', $empresaId)->where('activo', true)
                ->orderBy('apellido_paterno')->get()
                ->map(function ($e) use ($existentes) {
                    $a = $existentes->get($e->id);
                    $c = $e->contratoVigente->first();

                    return [
                        'employee_id' => $e->id,
                        'trabajador' => $e->nombre_completo,
                        'dni' => $e->numero_documento,
                        'cargo' => $c?->cargo?->nombre ?? '—',
                        'horas' => $a ? (float) $a->horas : 0,
                        'minutos' => $a ? (int) $a->minutos : 0,
                        'aprobado' => $a ? (bool) $a->aprobado : false,
                        'monto_horas' => $a ? (float) $a->monto_horas : 0,
                        'sabado' => $a ? (float) $a->sabado : 0,
                        'domingo_feriado' => $a ? (float) $a->domingo_feriado : 0,
                        'bono' => $a ? (float) $a->bono : 0,
                        'nota' => $a?->nota ?? '',
                    ];
                });
        }

        return Inertia::render('Adicionales/Index', [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']),
            'filas' => $filas,
            'filtros' => ['empresa_id' => $empresaId, 'anio' => $anio, 'mes' => $mes, 'quincena' => $quincena],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'filas' => ['required', 'array'],
            'filas.*.employee_id' => ['required', 'exists:employees,id'],
            'filas.*.horas' => ['nullable', 'numeric', 'min:0'],
            'filas.*.minutos' => ['nullable', 'integer', 'min:0', 'max:59'],
            'filas.*.aprobado' => ['nullable', 'boolean'],
            'filas.*.monto_horas' => ['nullable', 'numeric', 'min:0'],
            'filas.*.sabado' => ['nullable', 'numeric', 'min:0'],
            'filas.*.domingo_feriado' => ['nullable', 'numeric', 'min:0'],
            'filas.*.bono' => ['nullable', 'numeric', 'min:0'],
            'filas.*.nota' => ['nullable', 'string', 'max:255'],
        ]);

        $quincena = $data['quincena'] ?? null;

        DB::transaction(function () use ($data, $quincena, $request) {
            foreach ($data['filas'] as $f) {
                $horas = (float) ($f['horas'] ?? 0);
                $minutos = (int) ($f['minutos'] ?? 0);
                $aprobado = (bool) ($f['aprobado'] ?? false);
                $montoHoras = (float) ($f['monto_horas'] ?? 0);
                $sabado = (float) ($f['sabado'] ?? 0);
                $domingo = (float) ($f['domingo_feriado'] ?? 0);
                $bono = (float) ($f['bono'] ?? 0);
                $nota = $f['nota'] ?? null;

                $clave = [
                    'empresa_id' => $data['empresa_id'],
                    'employee_id' => $f['employee_id'],
                    'anio' => $data['anio'],
                    'mes' => $data['mes'],
                    'quincena' => $quincena,
                ];

                // Si no hay nada cargado, borrar el registro (limpiado).
                if ($horas == 0 && $minutos == 0 && $montoHoras == 0 && $sabado == 0 && $domingo == 0 && $bono == 0 && ! $nota) {
                    IngresoAdicional::where($clave)->delete();

                    continue;
                }

                IngresoAdicional::updateOrCreate($clave, [
                    'horas' => $horas,
                    'minutos' => $minutos,
                    'aprobado' => $aprobado,
                    'monto_horas' => $montoHoras,
                    'sabado' => $sabado,
                    'domingo_feriado' => $domingo,
                    'bono' => $bono,
                    'nota' => $nota,
                    'registrado_por' => $request->user()?->id,
                ]);
            }
        });

        return back()->with('success', 'Adicionales guardados. Entrarán a la planilla al generarla.');
    }
}
