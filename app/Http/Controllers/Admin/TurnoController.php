<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TurnoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Turnos/Index', [
            'turnos' => Turno::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Turno::create($this->validar($request));

        return back()->with('success', 'Turno registrado.');
    }

    /**
     * Rotación de vigilancia: intercambia el turno de TODOS los trabajadores
     * activos que están en "Vigilancia Día" o "Vigilancia Noche" — los de día
     * pasan a noche y viceversa, en un solo clic. El día de descanso fijo de
     * cada uno NO se toca (es independiente del turno).
     */
    public function rotarVigilancia()
    {
        $dia = Turno::where('nombre', 'like', '%Vigilancia%Día%')->orWhere('nombre', 'like', '%Vigilancia Dia%')->first();
        $noche = Turno::where('nombre', 'like', '%Vigilancia%Noche%')->first();

        if (! $dia || ! $noche) {
            return back()->with('error', 'No se encontraron los turnos "Vigilancia Día" y "Vigilancia Noche".');
        }

        $n = 0;
        \DB::transaction(function () use ($dia, $noche, &$n) {
            // Se capturan los IDs ANTES de mover nada, para no chocar entre sí.
            $idsDia = \App\Models\Contract::where('activo', true)->where('turno_id', $dia->id)->pluck('id');
            $idsNoche = \App\Models\Contract::where('activo', true)->where('turno_id', $noche->id)->pluck('id');

            \App\Models\Contract::whereIn('id', $idsDia)->update(['turno_id' => $noche->id]);
            \App\Models\Contract::whereIn('id', $idsNoche)->update(['turno_id' => $dia->id]);
            $n = $idsDia->count() + $idsNoche->count();
        });

        return back()->with('success', "Rotación aplicada: {$n} vigilante(s) intercambiaron de turno (día↔noche).");
    }

    public function update(Request $request, Turno $turno)
    {
        $turno->update($this->validar($request));

        return back()->with('success', 'Turno actualizado.');
    }

    public function destroy(Turno $turno)
    {
        $turno->delete();

        return back()->with('success', 'Turno eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'hora_entrada' => ['required', 'date_format:H:i'],
            'hora_salida' => ['required', 'date_format:H:i'],
            'refrigerio_min' => ['required', 'integer', 'min:0', 'max:480'],
            'tolerancia_min' => ['required', 'integer', 'min:0', 'max:120'],
            'cruza_medianoche' => ['boolean'],
            'trabaja_sabado' => ['boolean'],
            'hora_salida_sabado' => ['nullable', 'date_format:H:i'],
            'trabaja_domingo' => ['boolean'],
            'activo' => ['boolean'],
        ]);
    }
}
