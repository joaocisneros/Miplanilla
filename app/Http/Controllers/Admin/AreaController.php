<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->session()->get('empresa_id');

        return Inertia::render('Admin/Areas/Index', [
            'areas' => Area::where('empresa_id', $empresaId)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['empresa_id'] = $request->session()->get('empresa_id');
        abort_if(! $data['empresa_id'], 422, 'Selecciona una empresa activa.');

        Area::create($data);

        return back()->with('success', 'Área registrada.');
    }

    public function update(Request $request, Area $area)
    {
        abort_if((int) $area->empresa_id !== (int) $request->session()->get('empresa_id'), 403);
        $area->update($this->validar($request));

        return back()->with('success', 'Área actualizada.');
    }

    public function destroy(Request $request, Area $area)
    {
        abort_if((int) $area->empresa_id !== (int) $request->session()->get('empresa_id'), 403);
        $area->delete();

        return back()->with('success', 'Área eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'es_riesgo' => ['boolean'],
            'activo' => ['boolean'],
        ]);
    }
}
