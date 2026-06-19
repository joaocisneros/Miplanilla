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
            'contexto' => fn () => $this->contexto($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /** Empresa/sede activa + listas para el selector (solo si hay sesión). */
    private function contexto(Request $request): array
    {
        if (! $request->user()) {
            return ['empresas' => [], 'sedes' => [], 'empresa_id' => null, 'sede_id' => null];
        }

        $empresas = Empresa::where('activo', true)->orderBy('razon_social')
            ->get(['id', 'razon_social', 'nombre_comercial']);

        $empresaId = $request->session()->get('empresa_id');
        if (! $empresaId || ! $empresas->contains('id', $empresaId)) {
            $empresaId = $empresas->first()?->id;
            $request->session()->put('empresa_id', $empresaId);
        }

        $sedes = $empresaId
            ? Sede::where('empresa_id', $empresaId)->where('activo', true)
                ->orderBy('nombre')->get(['id', 'nombre'])
            : collect();

        $sedeId = $request->session()->get('sede_id');
        if ($sedeId && ! $sedes->contains('id', $sedeId)) {
            $sedeId = null;
            $request->session()->forget('sede_id');
        }

        return [
            'empresas' => $empresas,
            'sedes' => $sedes,
            'empresa_id' => $empresaId,
            'sede_id' => $sedeId,
        ];
    }
}
