<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Para usuarios limitados a una o más empresas (ej. un contador/auditor),
 * fuerza el parámetro `empresa_id` de cada petición a una de sus empresas
 * permitidas. Así ningún módulo puede mostrarle datos de otra empresa,
 * aunque intente cambiar el `empresa_id` a mano.
 */
class RestringeEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->esSuperAdmin()) {
            $ids = $user->empresasPermitidasIds(); // null = todas

            if ($ids !== null && count($ids) > 0) {
                $actual = (int) $request->input('empresa_id');

                // Si no envía empresa, o envía una que no le corresponde,
                // se fija en la primera empresa permitida.
                if (! in_array($actual, $ids, true)) {
                    $request->merge(['empresa_id' => $ids[0]]);
                }
            }
        }

        return $next($request);
    }
}
