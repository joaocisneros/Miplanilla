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
        $empresaId = $request->session()->get('empresa_id');

        return Inertia::render('Admin/Sedes/Index', [
            'empresa' => Empresa::find($empresaId),
            'sedes' => Sede::where('empresa_id', $empresaId)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['empresa_id'] = $request->session()->get('empresa_id');
        abort_if(! $data['empresa_id'], 422, 'No hay empresa activa seleccionada.');

        Sede::create($data);

        return back()->with('success', 'Sede registrada.');
    }

    public function update(Request $request, Sede $sede)
    {
        $this->autorizarEmpresa($request, $sede);
        $sede->update($this->validar($request));

        return back()->with('success', 'Sede actualizada.');
    }

    public function destroy(Request $request, Sede $sede)
    {
        $this->autorizarEmpresa($request, $sede);
        $sede->delete();

        return back()->with('success', 'Sede eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ]);
    }

    private function autorizarEmpresa(Request $request, Sede $sede): void
    {
        abort_if((int) $sede->empresa_id !== (int) $request->session()->get('empresa_id'), 403);
    }
}
