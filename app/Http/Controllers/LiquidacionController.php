<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\LiquidacionService;
use App\Models\Empresa;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiquidacionController extends Controller
{
    public function __construct(private LiquidacionService $service) {}

    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id');
        $employeeId = $request->input('employee_id');
        $fechaCese = $request->input('fecha_cese');

        $empleados = collect();
        if ($empresaId) {
            $empleados = Employee::where('empresa_id', $empresaId)->where('activo', true)
                ->orderBy('apellido_paterno')->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'numero_documento'])
                ->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre_completo.' ('.$e->numero_documento.')']);
        }

        $resultado = null;
        $trabajador = null;
        if ($employeeId && $fechaCese) {
            $emp = Employee::with('contratoVigente')->find($employeeId);
            if ($emp) {
                $resultado = $this->service->calcular($emp, Carbon::parse($fechaCese));
                $trabajador = $emp->nombre_completo;
            }
        }

        return Inertia::render('Liquidacion/Index', [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']),
            'empleados' => $empleados,
            'resultado' => $resultado,
            'trabajador' => $trabajador,
            'filtros' => [
                'empresa_id' => $empresaId ? (int) $empresaId : null,
                'employee_id' => $employeeId ? (int) $employeeId : null,
                'fecha_cese' => $fechaCese,
            ],
        ]);
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'fecha_cese' => ['required', 'date'],
        ]);

        $emp = Employee::with('contratoVigente', 'empresa')->findOrFail($data['employee_id']);
        $resultado = $this->service->calcular($emp, Carbon::parse($data['fecha_cese']));

        abort_if(isset($resultado['error']), 422, $resultado['error'] ?? 'No se pudo calcular.');

        $pdf = Pdf::loadView('beneficios.liquidacion', [
            'resultado' => $resultado,
            'emp' => $emp,
            'empresa' => $emp->empresa,
        ]);

        return $pdf->download('liquidacion_'.$emp->numero_documento.'.pdf');
    }
}
