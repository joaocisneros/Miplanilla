<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Sede;
use Illuminate\Http\Request;

class ContextoController extends Controller
{
    /** Fija la empresa activa en sesión (o "todas"). Limpia la sede al cambiar. */
    public function setEmpresa(Request $request)
    {
        $valor = $request->input('empresa_id');
        $request->session()->forget('sede_id');

        // "todas" = vista consolidada de todas las empresas (solo lectura)
        if ($valor === 'todas' || $valor === '' || $valor === null) {
            $request->session()->put('empresa_modo', 'todas');
            $request->session()->forget('empresa_id');

            return back();
        }

        $request->validate(['empresa_id' => ['exists:empresas,id']]);
        $request->session()->put('empresa_id', (int) $valor);
        $request->session()->forget('empresa_modo');

        return back();
    }

    /** Fija la sede activa (debe pertenecer a la empresa activa). */
    public function setSede(Request $request)
    {
        $data = $request->validate([
            'sede_id' => ['nullable'],
        ]);

        if (empty($data['sede_id'])) {
            $request->session()->forget('sede_id');

            return back();
        }

        $sede = Sede::findOrFail($data['sede_id']);
        // La sede debe ser de la empresa activa
        if ((int) $sede->empresa_id !== (int) $request->session()->get('empresa_id')) {
            abort(403, 'La sede no pertenece a la empresa activa.');
        }

        $request->session()->put('sede_id', $sede->id);

        return back();
    }
}
