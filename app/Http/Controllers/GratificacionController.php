<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\GratificacionService;
use App\Models\Empresa;
use App\Models\Gratificacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GratificacionController extends Controller
{
    public function __construct(private GratificacionService $service) {}

    public function index(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $tipo = $request->input('tipo', 'julio');
        $empresaId = $request->input('empresa_id');

        $filas = collect();
        $totales = ['monto' => 0, 'bonif' => 0, 'renta_5ta' => 0, 'neto' => 0, 'empleados' => 0];

        if ($empresaId) {
            $registros = Gratificacion::with('employee:id,nombres,apellido_paterno,apellido_materno,numero_documento')
                ->where('empresa_id', $empresaId)->where('anio', $anio)->where('tipo', $tipo)
                ->get();

            $filas = $registros->map(fn ($g) => [
                'id' => $g->id,
                'dni' => $g->employee?->numero_documento,
                'trabajador' => $g->employee?->nombre_completo,
                'meses' => $g->meses_computables,
                'dias' => $g->dias_computables,
                'rem_computable' => (float) $g->rem_computable,
                'monto' => (float) $g->monto,
                'bonificacion_extraordinaria' => (float) $g->bonificacion_extraordinaria,
                'renta_5ta' => (float) $g->renta_5ta,
                'neto' => (float) $g->neto,
            ])->sortBy('trabajador')->values();

            $totales = [
                'monto' => round($registros->sum('monto'), 2),
                'bonif' => round($registros->sum('bonificacion_extraordinaria'), 2),
                'renta_5ta' => round($registros->sum('renta_5ta'), 2),
                'neto' => round($registros->sum('neto'), 2),
                'empleados' => $registros->count(),
            ];
        }

        return Inertia::render('Gratificaciones/Index', [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']),
            'filas' => $filas,
            'totales' => $totales,
            'filtros' => ['anio' => $anio, 'tipo' => $tipo, 'empresa_id' => $empresaId ? (int) $empresaId : null],
        ]);
    }

    public function generar(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tipo' => ['required', 'in:julio,diciembre'],
        ]);

        $ids = $this->service->generar($data['empresa_id'], $data['anio'], $data['tipo'], $request->user()?->id);

        return redirect()->route('gratificaciones.index', $data)
            ->with('success', 'Gratificación de '.ucfirst($data['tipo']).' generada para '.count($ids).' trabajadores.');
    }

    public function pdf(Gratificacion $gratificacion)
    {
        $gratificacion->load('empresa', 'employee');

        $pdf = Pdf::loadView('beneficios.gratificacion', [
            'g' => $gratificacion,
            'emp' => $gratificacion->employee,
            'empresa' => $gratificacion->empresa,
        ]);

        return $pdf->download('gratificacion_'.$gratificacion->employee->numero_documento.'_'.$gratificacion->anio.'_'.$gratificacion->tipo.'.pdf');
    }
}
