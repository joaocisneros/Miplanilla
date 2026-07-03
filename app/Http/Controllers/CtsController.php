<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\CtsService;
use App\Models\Cts;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CtsController extends Controller
{
    public function __construct(private CtsService $service) {}

    public function index(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $periodo = $request->input('periodo', 'mayo');
        $empresaId = $request->input('empresa_id');

        $filas = collect();
        $totales = ['monto' => 0, 'empleados' => 0];

        if ($empresaId) {
            $registros = Cts::with('employee:id,nombres,apellido_paterno,apellido_materno,numero_documento')
                ->where('empresa_id', $empresaId)->where('anio', $anio)->where('periodo', $periodo)
                ->get();

            $filas = $registros->map(fn ($c) => [
                'id' => $c->id,
                'dni' => $c->employee?->numero_documento,
                'trabajador' => $c->employee?->nombre_completo,
                'meses' => $c->meses_computables,
                'dias' => $c->dias_computables,
                'rem_computable' => (float) $c->rem_computable,
                'sexto_gratificacion' => (float) $c->sexto_gratificacion,
                'monto' => (float) $c->monto,
            ])->sortBy('trabajador')->values();

            $totales = ['monto' => round($registros->sum('monto'), 2), 'empleados' => $registros->count()];
        }

        return Inertia::render('Cts/Index', [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']),
            'filas' => $filas,
            'totales' => $totales,
            'filtros' => ['anio' => $anio, 'periodo' => $periodo, 'empresa_id' => $empresaId ? (int) $empresaId : null],
        ]);
    }

    public function generar(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodo' => ['required', 'in:mayo,noviembre'],
        ]);

        $ids = $this->service->generar($data['empresa_id'], $data['anio'], $data['periodo'], $request->user()?->id);

        return redirect()->route('cts.index', $data)
            ->with('success', 'CTS de '.ucfirst($data['periodo']).' generada para '.count($ids).' trabajadores.');
    }

    public function pdf(Cts $ct)
    {
        $ct->load('empresa', 'employee');

        $pdf = Pdf::loadView('beneficios.cts', [
            'c' => $ct,
            'emp' => $ct->employee,
            'empresa' => $ct->empresa,
        ]);

        return $pdf->download('cts_'.$ct->employee->numero_documento.'_'.$ct->anio.'_'.$ct->periodo.'.pdf');
    }
}
