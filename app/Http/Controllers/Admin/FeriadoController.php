<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feriado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeriadoController extends Controller
{
    public function index(): Response
    {
        // Automatico: el año actual y el siguiente siempre están listos.
        Feriado::asegurarAnio((int) now()->year);
        Feriado::asegurarAnio((int) now()->year + 1);

        return Inertia::render('Admin/Feriados/Index', [
            'feriados' => Feriado::orderBy('fecha')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Feriado::create($request->validate([
            'fecha' => ['required', 'date', 'unique:feriados,fecha'],
            'nombre' => ['required', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Feriado registrado.');
    }

    public function update(Request $request, Feriado $feriado)
    {
        $feriado->update($request->validate([
            'fecha' => ['required', 'date', 'unique:feriados,fecha,'.$feriado->id],
            'nombre' => ['required', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Feriado actualizado.');
    }

    public function destroy(Feriado $feriado)
    {
        $feriado->delete();

        return back()->with('success', 'Feriado eliminado.');
    }

    /**
     * Genera los feriados nacionales de un año: los fijos se repiten y
     * Semana Santa se calcula con el algoritmo de la Pascua (computus).
     */
    public function generarAnio(Request $request)
    {
        $anio = (int) $request->validate(['anio' => ['required', 'integer', 'min:2020', 'max:2100']])['anio'];
        $nuevos = Feriado::generarAnio($anio);

        return back()->with('success', "Feriados de {$anio} generados ({$nuevos} nuevos, Semana Santa incluida).");
    }
}
