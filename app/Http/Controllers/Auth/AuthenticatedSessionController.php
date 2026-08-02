<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->registrarSesion($request, 'login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->registrarSesion($request, 'logout');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /** Registra accesos sin guardar contraseñas, tokens ni contenido de sesión. */
    private function registrarSesion(Request $request, string $evento): void
    {
        $usuario = $request->user();

        if (! $usuario) {
            return;
        }

        DB::table(config('audit.drivers.database.table', 'audits'))->insert([
            'user_type' => $usuario::class,
            'user_id' => $usuario->id,
            'event' => $evento,
            'auditable_type' => $usuario::class,
            'auditable_id' => $usuario->id,
            'old_values' => json_encode([], JSON_UNESCAPED_UNICODE),
            'new_values' => json_encode([
                'sesion' => $evento === 'login' ? 'Inicio de sesión' : 'Cierre de sesión',
            ], JSON_UNESCAPED_UNICODE),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1023),
            'tags' => 'sesion',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
