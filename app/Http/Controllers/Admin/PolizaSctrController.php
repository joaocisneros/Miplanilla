<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolizaSctr;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PolizaSctrController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PolizasSctr/Index', [
            'polizas' => PolizaSctr::orderByDesc('vigente_desde')->get(),
        ]);
    }

    public function store(Request $request)
    {
        PolizaSctr::create($this->validar($request));

        return back()->with('success', 'Póliza SCTR registrada.');
    }

    public function update(Request $request, PolizaSctr $poliza)
    {
        $poliza->update($this->validar($request));

        return back()->with('success', 'Póliza SCTR actualizada.');
    }

    public function destroy(PolizaSctr $poliza)
    {
        $poliza->delete();

        return back()->with('success', 'Póliza SCTR eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'aseguradora' => ['required', 'string', 'max:120'],
            'actividad_riesgo' => ['nullable', 'string', 'max:120'],
            'tasa_salud' => ['required', 'numeric', 'min:0', 'max:1'],
            'tasa_pension' => ['required', 'numeric', 'min:0', 'max:1'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
            'confirmado' => ['boolean'],
        ]);
    }
}
