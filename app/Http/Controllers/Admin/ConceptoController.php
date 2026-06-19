<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concepto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConceptoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Conceptos/Index', [
            'conceptos' => Concepto::orderBy('tipo')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Concepto $concepto)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'es_remunerativo' => ['boolean'],
            'afecto_afp_onp' => ['boolean'],
            'afecto_essalud' => ['boolean'],
            'afecto_sctr' => ['boolean'],
            'afecto_5ta' => ['boolean'],
            'afecta_descuento_inasistencia' => ['boolean'],
            'evalua_regularidad' => ['boolean'],
            'activo' => ['boolean'],
        ]);

        $concepto->update($data);

        return back()->with('success', 'Concepto actualizado.');
    }
}
