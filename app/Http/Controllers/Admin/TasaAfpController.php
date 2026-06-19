<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TasaAfp;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TasaAfpController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/TasasAfp/Index', [
            'tasas' => TasaAfp::orderBy('afp')->orderBy('tipo')->get(),
        ]);
    }

    public function store(Request $request)
    {
        TasaAfp::create($this->validar($request));

        return back()->with('success', 'Tasa registrada.');
    }

    public function update(Request $request, TasaAfp $tasa)
    {
        $tasa->update($this->validar($request));

        return back()->with('success', 'Tasa actualizada.');
    }

    public function destroy(TasaAfp $tasa)
    {
        $tasa->delete();

        return back()->with('success', 'Tasa eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'afp' => ['required', 'string', 'max:50'],
            'tipo' => ['required', 'in:mixta,sueldo,onp'],
            'aporte_obligatorio' => ['required', 'numeric', 'min:0', 'max:1'],
            'comision_flujo' => ['required', 'numeric', 'min:0', 'max:1'],
            'comision_saldo' => ['required', 'numeric', 'min:0', 'max:1'],
            'prima_seguro' => ['required', 'numeric', 'min:0', 'max:1'],
            'rem_max_asegurable' => ['nullable', 'numeric', 'min:0'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
            'confirmado' => ['boolean'],
            'fuente' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
