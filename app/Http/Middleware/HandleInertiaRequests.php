<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\Sede;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],
            'listas' => fn () => $this->listas($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /** Listas globales para los filtros de cada módulo (empresas + sedes). */
    private function listas(Request $request): array
    {
        if (! $request->user()) {
            return ['empresas' => [], 'sedes' => []];
        }

        return [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial']),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'empresa_id']),
        ];
    }
}
