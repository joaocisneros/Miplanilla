<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TurnoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Turnos/Index', [
            'turnos' => Turno::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Turno::create($this->validar($request));

        return back()->with('success', 'Turno registrado.');
    }

    public function update(Request $request, Turno $turno)
    {
        $turno->update($this->validar($request));

        return back()->with('success', 'Turno actualizado.');
    }

    public function destroy(Turno $turno)
    {
        $turno->delete();

        return back()->with('success', 'Turno eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'hora_entrada' => ['required', 'date_format:H:i'],
            'hora_salida' => ['required', 'date_format:H:i'],
            'refrigerio_min' => ['required', 'integer', 'min:0', 'max:480'],
            'tolerancia_min' => ['required', 'integer', 'min:0', 'max:120'],
            'cruza_medianoche' => ['boolean'],
            'activo' => ['boolean'],
        ]);
    }
}
