<?php

use App\Http\Controllers\Admin\ConceptoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\ParametroPeriodoController;
use App\Http\Controllers\Admin\SedeController;
use App\Http\Controllers\Admin\TasaAfpController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Contexto: empresa/sede activa
    Route::post('/contexto/empresa', [ContextoController::class, 'setEmpresa'])->name('contexto.empresa');
    Route::post('/contexto/sede', [ContextoController::class, 'setSede'])->name('contexto.sede');
});

// Panel de administración (solo rol ADMIN)
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('empresas', [EmpresaController::class, 'index'])->name('empresas.index');
    Route::post('empresas', [EmpresaController::class, 'store'])->name('empresas.store');
    Route::put('empresas/{empresa}', [EmpresaController::class, 'update'])->name('empresas.update');
    Route::delete('empresas/{empresa}', [EmpresaController::class, 'destroy'])->name('empresas.destroy');

    // Sedes (de la empresa activa)
    Route::get('sedes', [SedeController::class, 'index'])->name('sedes.index');
    Route::post('sedes', [SedeController::class, 'store'])->name('sedes.store');
    Route::put('sedes/{sede}', [SedeController::class, 'update'])->name('sedes.update');
    Route::delete('sedes/{sede}', [SedeController::class, 'destroy'])->name('sedes.destroy');

    // Maestros (configuración de reglas)
    Route::get('parametros', [ParametroPeriodoController::class, 'index'])->name('parametros.index');
    Route::post('parametros', [ParametroPeriodoController::class, 'store'])->name('parametros.store');
    Route::put('parametros/{parametro}', [ParametroPeriodoController::class, 'update'])->name('parametros.update');

    Route::get('tasas-afp', [TasaAfpController::class, 'index'])->name('tasas-afp.index');
    Route::post('tasas-afp', [TasaAfpController::class, 'store'])->name('tasas-afp.store');
    Route::put('tasas-afp/{tasa}', [TasaAfpController::class, 'update'])->name('tasas-afp.update');
    Route::delete('tasas-afp/{tasa}', [TasaAfpController::class, 'destroy'])->name('tasas-afp.destroy');

    Route::get('conceptos', [ConceptoController::class, 'index'])->name('conceptos.index');
    Route::put('conceptos/{concepto}', [ConceptoController::class, 'update'])->name('conceptos.update');
});

require __DIR__.'/auth.php';
