<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Sede;
use Illuminate\Http\Request;

class ContextoController extends Controller
{
    /** Fija la empresa activa en sesión (y limpia la sede si cambia). */
    public function setEmpresa(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
        ]);

        $request->session()->put('empresa_id', (int) $data['empresa_id']);
        $request->session()->forget('sede_id');

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
