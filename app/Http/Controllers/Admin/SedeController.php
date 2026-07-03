<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Sede;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SedeController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;

        return Inertia::render('Admin/Sedes/Index', [
            'sedes' => Sede::with('empresa:id,razon_social,nombre_comercial')
                ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
                ->orderBy('nombre')->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'nombre' => $s->nombre,
                    'direccion' => $s->direccion,
                    'activo' => $s->activo,
                    'empresa_id' => $s->empresa_id,
                    'empresa' => $s->empresa?->nombre_comercial ?? $s->empresa?->razon_social,
                ]),
            'filtros' => ['empresa_id' => $empresaId],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    public function store(Request $request)
    {
        Sede::create($this->validar($request));

        return back()->with('success', 'Sede registrada.');
    }

    public function update(Request $request, Sede $sede)
    {
        $sede->update($this->validar($request));

        return back()->with('success', 'Sede actualizada.');
    }

    public function destroy(Sede $sede)
    {
        $sede->delete();

        return back()->with('success', 'Sede eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ]);
    }
}
