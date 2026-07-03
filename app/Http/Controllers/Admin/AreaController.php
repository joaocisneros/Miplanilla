<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;

        return Inertia::render('Admin/Areas/Index', [
            'areas' => Area::with('empresa:id,razon_social,nombre_comercial')
                ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
                ->orderBy('nombre')->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'es_riesgo' => $a->es_riesgo,
                    'activo' => $a->activo,
                    'empresa_id' => $a->empresa_id,
                    'empresa' => $a->empresa?->nombre_comercial ?? $a->empresa?->razon_social,
                ]),
            'filtros' => ['empresa_id' => $empresaId],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    public function store(Request $request)
    {
        Area::create($this->validar($request));

        return back()->with('success', 'Área registrada.');
    }

    public function update(Request $request, Area $area)
    {
        $area->update($this->validar($request));

        return back()->with('success', 'Área actualizada.');
    }

    public function destroy(Area $area)
    {
        $area->delete();

        return back()->with('success', 'Área eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'es_riesgo' => ['boolean'],
            'activo' => ['boolean'],
        ]);
    }
}
