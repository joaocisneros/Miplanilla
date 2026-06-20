<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Cargos/Index', [
            'cargos' => Cargo::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Cargo::create($this->validar($request));

        return back()->with('success', 'Cargo registrado.');
    }

    public function update(Request $request, Cargo $cargo)
    {
        $cargo->update($this->validar($request));

        return back()->with('success', 'Cargo actualizado.');
    }

    public function destroy(Cargo $cargo)
    {
        $cargo->delete();

        return back()->with('success', 'Cargo eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'activo' => ['boolean'],
        ]);
    }
}
