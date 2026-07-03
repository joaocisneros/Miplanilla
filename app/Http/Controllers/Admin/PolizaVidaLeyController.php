<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolizaVidaLey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PolizaVidaLeyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PolizasVidaLey/Index', [
            'polizas' => PolizaVidaLey::orderByDesc('vigente_desde')->get(),
        ]);
    }

    public function store(Request $request)
    {
        PolizaVidaLey::create($this->validar($request));

        return back()->with('success', 'Póliza Vida Ley registrada.');
    }

    public function update(Request $request, PolizaVidaLey $poliza)
    {
        $poliza->update($this->validar($request));

        return back()->with('success', 'Póliza Vida Ley actualizada.');
    }

    public function destroy(PolizaVidaLey $poliza)
    {
        $poliza->delete();

        return back()->with('success', 'Póliza Vida Ley eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'aseguradora' => ['required', 'string', 'max:120'],
            'tasa' => ['required', 'numeric', 'min:0', 'max:1'],
            'base' => ['nullable', 'string', 'max:120'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
            'confirmado' => ['boolean'],
        ]);
    }
}
