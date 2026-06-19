<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Empresas/Index', [
            'empresas' => Empresa::orderBy('razon_social')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        Empresa::create($data);

        return back()->with('success', 'Empresa registrada correctamente.');
    }

    public function update(Request $request, Empresa $empresa)
    {
        $data = $this->validar($request, $empresa->id);
        $empresa->update($data);

        return back()->with('success', 'Empresa actualizada.');
    }

    public function destroy(Empresa $empresa)
    {
        $empresa->delete();

        return back()->with('success', 'Empresa eliminada.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'ruc' => ['required', 'digits:11', Rule::unique('empresas', 'ruc')->ignore($id)],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ]);
    }
}
