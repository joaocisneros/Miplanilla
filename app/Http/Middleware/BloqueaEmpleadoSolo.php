<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea a los usuarios con SOLO el rol EMPLEADO de las pantallas operativas
 * de gestión (ej. registro diario de asistencia, plantillas de importación),
 * que muestran datos de TODOS los trabajadores de la empresa, no solo los suyos.
 */
class BloqueaEmpleadoSolo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->esSoloEmpleado(), 403, 'No autorizado para esta sección.');

        return $next($request);
    }
}
