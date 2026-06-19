<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametroPeriodo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ParametroPeriodoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Parametros/Index', [
            'parametros' => ParametroPeriodo::orderByDesc('anio')->get(),
        ]);
    }

    public function store(Request $request)
    {
        ParametroPeriodo::create($this->validar($request));

        return back()->with('success', 'Parámetro registrado.');
    }

    public function update(Request $request, ParametroPeriodo $parametro)
    {
        $parametro->update($this->validar($request, $parametro->id));

        return back()->with('success', 'Parámetro actualizado.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('parametros_periodo', 'anio')->ignore($id)],
            'uit' => ['nullable', 'numeric', 'min:0'],
            'rmv' => ['required', 'numeric', 'min:0'],
            'asignacion_familiar' => ['required', 'numeric', 'min:0'],
            'dias_base' => ['required', 'integer', 'min:28', 'max:31'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
            'confirmado' => ['boolean'],
            'fuente' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
