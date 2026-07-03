<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Candado multiempresa: limita automáticamente las consultas del modelo a las
 * empresas permitidas del usuario autenticado.
 *
 * - Sin usuario (consola, seeders, login): no hace nada.
 * - Super admin o usuario sin restricción: no hace nada (ve todo).
 * - Usuario restringido (contador/auditor de una empresa): solo ve las suyas.
 *
 * La tabla 'empresas' filtra por su columna `id`; las demás por `empresa_id`.
 */
trait RestringidoPorEmpresa
{
    protected static function bootRestringidoPorEmpresa(): void
    {
        static::addGlobalScope('empresaPermitida', function (Builder $query) {
            $user = Auth::user();
            if (! $user || ! method_exists($user, 'empresasPermitidasIds')) {
                return;
            }

            $ids = $user->empresasPermitidasIds(); // null = todas
            if ($ids === null) {
                return;
            }

            $tabla = $query->getModel()->getTable();
            $columna = $tabla === 'empresas' ? 'id' : 'empresa_id';
            $query->whereIn($tabla.'.'.$columna, $ids);
        });
    }
}
