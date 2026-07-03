<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Registra el último acceso de cada usuario al iniciar sesión.
        // Protegido: si la columna aún no existe (migración pendiente), NO interrumpe el login.
        Event::listen(Login::class, function (Login $event) {
            try {
                $event->user->forceFill([
                    'ultimo_acceso' => now(),
                    'ultimo_acceso_ip' => request()->ip(),
                ])->saveQuietly();
            } catch (\Throwable $e) {
                // no hacer nada: el acceso continúa aunque falle el registro del último acceso
            }
        });
    }
}
