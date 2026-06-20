<?php

namespace App\Http\Controllers;

use App\Imports\AsistenciaImport;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AsistenciaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->session()->get('empresa_id');
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());

        $registros = Attendance::with('employee:id,apellido_paterno,apellido_materno,nombres')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->limit(500)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'empleado' => $a->employee?->nombre_completo,
                'fecha' => $a->fecha->toDateString(),
                'estado' => $a->estado,
                'minutos_tarde' => $a->minutos_tarde,
                'horas_extra' => $a->horas_extra,
            ]);

        return Inertia::render('Asistencia/Index', [
            'registros' => $registros,
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $empresaId = $request->session()->get('empresa_id');
        abort_if(! $empresaId, 422, 'Selecciona una empresa activa.');

        $import = new AsistenciaImport($empresaId);
        Excel::import($import, $request->file('archivo'));

        $msg = "{$import->importadas} registros importados.";
        if ($import->errores) {
            $msg .= ' Errores: '.implode(' | ', array_slice($import->errores, 0, 10));
            return back()->with('error', $msg);
        }

        return back()->with('success', $msg);
    }

    /** Descarga una plantilla CSV con los encabezados esperados. */
    public function plantilla(): StreamedResponse
    {
        $headers = ['dni', 'fecha', 'estado', 'minutos_tarde', 'horas_extra', 'hora_entrada', 'hora_salida'];
        $ejemplo = ['71246290', Carbon::now()->toDateString(), 'NORMAL', '0', '0', '08:00', '18:00'];

        return response()->streamDownload(function () use ($headers, $ejemplo) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $ejemplo);
            fclose($out);
        }, 'plantilla_asistencia.csv', ['Content-Type' => 'text/csv']);
    }
}
