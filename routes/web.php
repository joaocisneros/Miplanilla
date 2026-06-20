<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\CargoController;
use App\Http\Controllers\Admin\ConceptoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\ParametroPeriodoController;
use App\Http\Controllers\Admin\SedeController;
use App\Http\Controllers\Admin\TasaAfpController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\EmployeeController;
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

    // Empleados (RRHH/ADMIN gestionan; SUPERVISOR solo ve)
    Route::get('empleados', [EmployeeController::class, 'index'])->middleware('permission:empleados.ver')->name('empleados.index');
    Route::middleware('permission:empleados.gestionar')->group(function () {
        Route::get('empleados/nuevo', [EmployeeController::class, 'create'])->name('empleados.create');
        Route::post('empleados', [EmployeeController::class, 'store'])->name('empleados.store');
        Route::get('empleados/{empleado}/editar', [EmployeeController::class, 'edit'])->name('empleados.edit');
        Route::put('empleados/{empleado}', [EmployeeController::class, 'update'])->name('empleados.update');
        Route::delete('empleados/{empleado}', [EmployeeController::class, 'destroy'])->name('empleados.destroy');
    });

    // Asistencia (importación Excel = vía principal)
    Route::get('asistencia', [AsistenciaController::class, 'index'])->middleware('permission:asistencia.ver')->name('asistencia.index');
    Route::get('asistencia/plantilla', [AsistenciaController::class, 'plantilla'])->middleware('permission:asistencia.ver')->name('asistencia.plantilla');
    Route::post('asistencia/import', [AsistenciaController::class, 'import'])->middleware('permission:asistencia.sincronizar')->name('asistencia.import');
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

    // Catálogos por empresa/globales
    Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::get('cargos', [CargoController::class, 'index'])->name('cargos.index');
    Route::post('cargos', [CargoController::class, 'store'])->name('cargos.store');
    Route::put('cargos/{cargo}', [CargoController::class, 'update'])->name('cargos.update');
    Route::delete('cargos/{cargo}', [CargoController::class, 'destroy'])->name('cargos.destroy');

    Route::get('turnos', [TurnoController::class, 'index'])->name('turnos.index');
    Route::post('turnos', [TurnoController::class, 'store'])->name('turnos.store');
    Route::put('turnos/{turno}', [TurnoController::class, 'update'])->name('turnos.update');
    Route::delete('turnos/{turno}', [TurnoController::class, 'destroy'])->name('turnos.destroy');
});

require __DIR__.'/auth.php';
