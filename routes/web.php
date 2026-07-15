<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\CargoController;
use App\Http\Controllers\Admin\ConceptoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\ParametroPeriodoController;
use App\Http\Controllers\Admin\PolizaSctrController;
use App\Http\Controllers\Admin\PolizaVidaLeyController;
use App\Http\Controllers\Admin\SedeController;
use App\Http\Controllers\Admin\TasaAfpController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AdelantoController;
use App\Http\Controllers\IngresoAdicionalController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\BoletaController;
use App\Http\Controllers\CtsController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\VacacionController;
use App\Http\Controllers\EmpleadoDocumentoController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GratificacionController;
use App\Http\Controllers\PlanillaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Si ya inició sesión va al panel; si no, al login (más directo para el cliente).
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Empleados (RRHH/ADMIN gestionan; SUPERVISOR solo ve)
    Route::get('empleados', [EmployeeController::class, 'index'])->middleware('permission:empleados.ver')->name('empleados.index');
    Route::get('empleados/export', [EmployeeController::class, 'export'])->middleware('permission:empleados.ver')->name('empleados.export');
    Route::get('empleados/consulta-dni/{dni}', [EmployeeController::class, 'consultaDni'])->middleware('permission:empleados.gestionar')->name('empleados.consultaDni');
    Route::get('empleados/{empleado}/ficha', [EmpleadoDocumentoController::class, 'ficha'])->middleware('permission:empleados.ver')->name('empleados.ficha');
    Route::get('empleados/{empleado}/contrato', [EmpleadoDocumentoController::class, 'contrato'])->middleware('permission:empleados.ver')->name('empleados.contrato');
    Route::get('documentos/{documento}/descargar', [EmpleadoDocumentoController::class, 'descargar'])->middleware('permission:empleados.ver')->name('empleados.documentos.descargar');
    Route::middleware('permission:empleados.gestionar')->group(function () {
        Route::post('empleados', [EmployeeController::class, 'store'])->name('empleados.store');
        Route::put('empleados/{empleado}', [EmployeeController::class, 'update'])->name('empleados.update');
        Route::delete('empleados/{empleado}', [EmployeeController::class, 'destroy'])->name('empleados.destroy');
        Route::patch('empleados/{empleado}/estado', [EmployeeController::class, 'toggleActivo'])->name('empleados.estado');
        Route::post('empleados/{empleado}/documentos', [EmpleadoDocumentoController::class, 'subir'])->name('empleados.documentos.subir');
        Route::delete('documentos/{documento}', [EmpleadoDocumentoController::class, 'eliminar'])->name('empleados.documentos.eliminar');
    });

    // Asistencia (registro diario manual + importación Excel/biométrico)
    Route::get('asistencia', [AsistenciaController::class, 'index'])->middleware('permission:asistencia.ver')->name('asistencia.index');
    Route::get('asistencia/resumen', [AsistenciaController::class, 'resumen'])->middleware('permission:asistencia.ver')->name('asistencia.resumen');
    Route::get('asistencia/diario', [AsistenciaController::class, 'diario'])->middleware('permission:asistencia.ver')->name('asistencia.diario');
    Route::post('asistencia/diario', [AsistenciaController::class, 'guardarDiario'])->middleware('permission:asistencia.sincronizar|asistencia.justificar')->name('asistencia.diario.guardar');
    Route::post('asistencia/empleado-mes', [AsistenciaController::class, 'guardarEmpleadoMes'])->middleware('permission:asistencia.sincronizar|asistencia.justificar')->name('asistencia.empleado-mes.guardar');
    Route::get('asistencia/plantilla', [AsistenciaController::class, 'plantilla'])->middleware('permission:asistencia.ver')->name('asistencia.plantilla');
    Route::post('asistencia/import', [AsistenciaController::class, 'import'])->middleware('permission:asistencia.sincronizar')->name('asistencia.import');
    Route::get('asistencia/plantilla-marcaciones', [AsistenciaController::class, 'plantillaMarcaciones'])->middleware('permission:asistencia.ver')->name('asistencia.plantilla-marcaciones');
    Route::post('asistencia/import-marcaciones', [AsistenciaController::class, 'importMarcaciones'])->middleware('permission:asistencia.sincronizar')->name('asistencia.import-marcaciones');
    Route::get('asistencia/plantilla-resumen', [AsistenciaController::class, 'plantillaResumen'])->middleware('permission:asistencia.ver')->name('asistencia.plantilla-resumen');
    Route::post('asistencia/import-resumen', [AsistenciaController::class, 'importResumen'])->middleware('permission:asistencia.sincronizar')->name('asistencia.import-resumen');
    // Plantilla mensual (formato A: fila por día) + anual (pestaña por mes) + importador
    Route::get('asistencia/plantilla-mensual', [AsistenciaController::class, 'plantillaMensual'])->middleware('permission:asistencia.ver')->name('asistencia.plantilla-mensual');
    Route::get('asistencia/plantilla-anual', [AsistenciaController::class, 'plantillaAnual'])->middleware('permission:asistencia.ver')->name('asistencia.plantilla-anual');
    Route::post('asistencia/import-mensual', [AsistenciaController::class, 'importMensual'])->middleware('permission:asistencia.sincronizar')->name('asistencia.import-mensual');

    // Planilla
    Route::get('planilla', [PlanillaController::class, 'index'])->middleware('permission:planilla.ver')->name('planilla.index');
    Route::get('planilla/{payroll}', [PlanillaController::class, 'show'])->middleware('permission:planilla.ver')->name('planilla.show');
    Route::get('planilla/{payroll}/detalle-excel', [PlanillaController::class, 'exportDetalle'])->middleware('permission:planilla.ver')->name('planilla.detalle-excel');
    Route::middleware('permission:planilla.generar')->group(function () {
        Route::post('planilla/periodos', [PlanillaController::class, 'storePeriodo'])->name('planilla.periodos.store');
        Route::post('planilla/generar-todas', [PlanillaController::class, 'generarTodas'])->name('planilla.generar-todas');
        Route::post('planilla/periodos/{periodo}/generar', [PlanillaController::class, 'generar'])->name('planilla.generar');
    });
    // Cerrar/reabrir: SOLO el administrador (no basta el permiso planilla.cerrar).
    Route::post('planilla/{payroll}/cerrar', [PlanillaController::class, 'cerrar'])->middleware('role:ADMIN')->name('planilla.cerrar');
    Route::post('planilla/{payroll}/reabrir', [PlanillaController::class, 'reabrir'])->middleware('role:ADMIN')->name('planilla.reabrir');

    // Recibos por Honorarios (RxH): mismo patrón que Planilla (lista de periodos -> detalle por trabajador)
    Route::get('honorarios', [\App\Http\Controllers\HonorarioController::class, 'index'])->middleware('permission:planilla.ver')->name('honorarios.index');
    Route::post('honorarios/generar', [\App\Http\Controllers\HonorarioController::class, 'generar'])->middleware('permission:planilla.generar')->name('honorarios.generar');
    Route::get('honorarios/detalle/{detalle}/recibo', [\App\Http\Controllers\HonorarioController::class, 'recibo'])->middleware('permission:boletas.ver')->name('honorarios.recibo');
    Route::get('honorarios/{payroll}', [\App\Http\Controllers\HonorarioController::class, 'show'])->middleware('permission:planilla.ver')->name('honorarios.show');
    Route::get('honorarios/{payroll}/excel', [\App\Http\Controllers\HonorarioController::class, 'export'])->middleware('permission:planilla.ver')->name('honorarios.excel');
    Route::get('honorarios/{payroll}/recibos-zip', [\App\Http\Controllers\HonorarioController::class, 'reciboZip'])->middleware('permission:boletas.ver')->name('honorarios.recibos-zip');
    Route::post('honorarios/{payroll}/recalcular', [\App\Http\Controllers\HonorarioController::class, 'recalcular'])->middleware('permission:planilla.generar')->name('honorarios.recalcular');
    Route::post('honorarios/{payroll}/cerrar', [\App\Http\Controllers\HonorarioController::class, 'cerrar'])->middleware('role:ADMIN')->name('honorarios.cerrar');
    Route::post('honorarios/{payroll}/reabrir', [\App\Http\Controllers\HonorarioController::class, 'reabrir'])->middleware('role:ADMIN')->name('honorarios.reabrir');

    // Contratistas (pago por avance de obra; separado de planilla/RxH)
    Route::get('contratistas', [\App\Http\Controllers\ContratistaController::class, 'index'])->middleware('permission:contratistas.ver')->name('contratistas.index');
    Route::get('contratistas/corte/excel', [\App\Http\Controllers\ContratistaController::class, 'exportCorte'])->middleware('permission:contratistas.ver')->name('contratistas.corte.excel');
    Route::get('contratistas/export/general', [\App\Http\Controllers\ContratistaController::class, 'exportGeneral'])->middleware('permission:contratistas.ver')->name('contratistas.export.general');
    Route::post('contratistas/avances', [\App\Http\Controllers\ContratistaController::class, 'storeAvance'])->middleware('permission:contratistas.avance')->name('contratistas.avances.store');
    Route::middleware('permission:contratistas.gestionar')->group(function () {
        Route::post('contratistas', [\App\Http\Controllers\ContratistaController::class, 'storeContratista'])->name('contratistas.store');
        Route::put('contratistas/{contratista}', [\App\Http\Controllers\ContratistaController::class, 'updateContratista'])->name('contratistas.update');
        Route::delete('contratistas/{contratista}', [\App\Http\Controllers\ContratistaController::class, 'destroyContratista'])->name('contratistas.destroy');
        Route::post('contratistas/ots', [\App\Http\Controllers\ContratistaController::class, 'storeOt'])->name('contratistas.ots.store');
        Route::put('contratistas/ots/{ot}', [\App\Http\Controllers\ContratistaController::class, 'updateOt'])->name('contratistas.ots.update');
        Route::delete('contratistas/ots/{ot}', [\App\Http\Controllers\ContratistaController::class, 'destroyOt'])->name('contratistas.ots.destroy');
        Route::post('contratistas/codigos', [\App\Http\Controllers\ContratistaController::class, 'storeCodigo'])->name('contratistas.codigos.store');
        Route::put('contratistas/codigos/{codigo}', [\App\Http\Controllers\ContratistaController::class, 'updateCodigo'])->name('contratistas.codigos.update');
        Route::delete('contratistas/codigos/{codigo}', [\App\Http\Controllers\ContratistaController::class, 'destroyCodigo'])->name('contratistas.codigos.destroy');
        Route::post('contratistas/productos', [\App\Http\Controllers\ContratistaController::class, 'storeProducto'])->name('contratistas.productos.store');
        Route::put('contratistas/productos/{producto}', [\App\Http\Controllers\ContratistaController::class, 'updateProducto'])->name('contratistas.productos.update');
        Route::delete('contratistas/productos/{producto}', [\App\Http\Controllers\ContratistaController::class, 'destroyProducto'])->name('contratistas.productos.destroy');
        Route::post('contratistas/trabajos', [\App\Http\Controllers\ContratistaController::class, 'storeTrabajo'])->name('contratistas.trabajos.store');
        Route::put('contratistas/trabajos/{trabajo}', [\App\Http\Controllers\ContratistaController::class, 'updateTrabajo'])->name('contratistas.trabajos.update');
        Route::delete('contratistas/trabajos/{trabajo}', [\App\Http\Controllers\ContratistaController::class, 'destroyTrabajo'])->name('contratistas.trabajos.destroy');
        Route::delete('contratistas/avances/{avance}', [\App\Http\Controllers\ContratistaController::class, 'destroyAvance'])->name('contratistas.avances.destroy');
        Route::post('contratistas/corte/pagar', [\App\Http\Controllers\ContratistaController::class, 'pagarCorte'])->name('contratistas.corte.pagar');
    });

    // Gratificaciones (Julio / Diciembre)
    Route::get('gratificaciones', [GratificacionController::class, 'index'])->middleware('permission:planilla.ver')->name('gratificaciones.index');
    Route::post('gratificaciones/generar', [GratificacionController::class, 'generar'])->middleware('permission:planilla.generar')->name('gratificaciones.generar');
    Route::get('gratificaciones/{gratificacion}/pdf', [GratificacionController::class, 'pdf'])->middleware('permission:planilla.ver')->name('gratificaciones.pdf');

    // CTS (mayo / noviembre)
    Route::get('cts', [CtsController::class, 'index'])->middleware('permission:planilla.ver')->name('cts.index');
    Route::post('cts/generar', [CtsController::class, 'generar'])->middleware('permission:planilla.generar')->name('cts.generar');
    Route::get('cts/{ct}/pdf', [CtsController::class, 'pdf'])->middleware('permission:planilla.ver')->name('cts.pdf');

    // Vacaciones
    Route::get('vacaciones', [VacacionController::class, 'index'])->middleware('permission:planilla.ver')->name('vacaciones.index');
    Route::post('vacaciones', [VacacionController::class, 'store'])->middleware('permission:planilla.generar')->name('vacaciones.store');
    Route::delete('vacaciones/{vacacion}', [VacacionController::class, 'destroy'])->middleware('permission:planilla.generar')->name('vacaciones.destroy');

    // Liquidación de cese
    Route::get('liquidacion', [LiquidacionController::class, 'index'])->middleware('permission:planilla.ver')->name('liquidacion.index');
    Route::get('liquidacion/pdf', [LiquidacionController::class, 'pdf'])->middleware('permission:planilla.ver')->name('liquidacion.pdf');

    // Adelantos / préstamos
    Route::get('adelantos', [AdelantoController::class, 'index'])->middleware('permission:planilla.ver')->name('adelantos.index');
    Route::post('adelantos', [AdelantoController::class, 'store'])->middleware('permission:planilla.generar')->name('adelantos.store');
    Route::delete('adelantos/grupo/{grupo}', [AdelantoController::class, 'destroyGrupo'])->middleware('permission:planilla.generar')->name('adelantos.destroy-grupo');
    Route::delete('adelantos/{adelanto}', [AdelantoController::class, 'destroy'])->middleware('permission:planilla.generar')->name('adelantos.destroy');

    // Ingresos adicionales aprobados por el supervisor (horas extra + bonos)
    Route::get('adicionales', [IngresoAdicionalController::class, 'index'])->middleware('permission:planilla.ver')->name('adicionales.index');
    Route::post('adicionales', [IngresoAdicionalController::class, 'store'])->middleware('permission:planilla.generar')->name('adicionales.store');

    // Boletas PDF
    Route::get('boletas/{detalle}/pdf', [BoletaController::class, 'pdf'])->middleware('permission:boletas.ver')->name('boletas.pdf');
    Route::get('planilla/{payroll}/boletas-zip', [BoletaController::class, 'zip'])->middleware('permission:boletas.ver')->name('boletas.zip');

    // Reportes
    Route::get('reportes/consolidado', [ReporteController::class, 'consolidado'])->middleware('permission:reportes.ver')->name('reportes.consolidado');
    Route::get('reportes/consolidado/export', [ReporteController::class, 'consolidadoExport'])->middleware('permission:reportes.ver')->name('reportes.consolidado.export');
    Route::get('reportes/tributos', [ReporteController::class, 'tributos'])->middleware('permission:reportes.ver')->name('reportes.tributos');
    Route::get('reportes/retenciones', [ReporteController::class, 'retenciones'])->middleware('permission:reportes.ver')->name('reportes.retenciones');
    Route::get('reportes/retenciones/export', [ReporteController::class, 'retencionesExport'])->middleware('permission:reportes.ver')->name('reportes.retenciones.export');
    Route::get('reportes/plame', [ReporteController::class, 'plame'])->middleware('permission:reportes.ver')->name('reportes.plame');
});

// Panel de administración (solo rol ADMIN)
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('empresas', [EmpresaController::class, 'index'])->name('empresas.index');
    Route::post('empresas', [EmpresaController::class, 'store'])->name('empresas.store');
    Route::put('empresas/{empresa}', [EmpresaController::class, 'update'])->name('empresas.update');
    Route::delete('empresas/{empresa}', [EmpresaController::class, 'destroy'])->name('empresas.destroy');

    // Usuarios del sistema (crear y asignar rol)
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::put('roles-permisos', [UsuarioController::class, 'actualizarPermisos'])->name('roles.permisos');
    Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'toggleActivo'])->name('usuarios.estado');
    Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

    // Bitácora / Auditoría: historial de cambios (quién cambió qué)
    Route::get('auditoria', [\App\Http\Controllers\Admin\AuditoriaController::class, 'index'])->name('auditoria.index');

    // Sedes (de la empresa activa)
    Route::get('sedes', [SedeController::class, 'index'])->name('sedes.index');
    Route::post('sedes', [SedeController::class, 'store'])->name('sedes.store');
    Route::put('sedes/{sede}', [SedeController::class, 'update'])->name('sedes.update');
    Route::delete('sedes/{sede}', [SedeController::class, 'destroy'])->name('sedes.destroy');

    Route::get('conceptos', [ConceptoController::class, 'index'])->name('conceptos.index');
    Route::put('conceptos/{concepto}', [ConceptoController::class, 'update'])->name('conceptos.update');

    // Pólizas SCTR
    Route::get('polizas-sctr', [PolizaSctrController::class, 'index'])->name('polizas-sctr.index');
    Route::post('polizas-sctr', [PolizaSctrController::class, 'store'])->name('polizas-sctr.store');
    Route::put('polizas-sctr/{poliza}', [PolizaSctrController::class, 'update'])->name('polizas-sctr.update');
    Route::delete('polizas-sctr/{poliza}', [PolizaSctrController::class, 'destroy'])->name('polizas-sctr.destroy');

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

    Route::get('feriados', [\App\Http\Controllers\Admin\FeriadoController::class, 'index'])->name('feriados.index');
    Route::post('feriados', [\App\Http\Controllers\Admin\FeriadoController::class, 'store'])->name('feriados.store');
    Route::put('feriados/{feriado}', [\App\Http\Controllers\Admin\FeriadoController::class, 'update'])->name('feriados.update');
    Route::delete('feriados/{feriado}', [\App\Http\Controllers\Admin\FeriadoController::class, 'destroy'])->name('feriados.destroy');
    Route::post('feriados/generar', [\App\Http\Controllers\Admin\FeriadoController::class, 'generarAnio'])->name('feriados.generar');
});

// Parámetros legales, Tasas AFP y Pólizas Vida Ley: ADMIN y CONTADOR (el contador
// suele ser quien actualiza estas tasas cada año). El resto del panel Admin (Empresas,
// Usuarios, Sedes, Conceptos, Pólizas SCTR, etc.) sigue exclusivo de ADMIN arriba.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:config.ver')->group(function () {
        Route::get('parametros', [ParametroPeriodoController::class, 'index'])->name('parametros.index');
        Route::get('tasas-afp', [TasaAfpController::class, 'index'])->name('tasas-afp.index');
        Route::get('polizas-vida-ley', [PolizaVidaLeyController::class, 'index'])->name('polizas-vida-ley.index');
    });
    Route::middleware('permission:config.editar')->group(function () {
        Route::post('parametros', [ParametroPeriodoController::class, 'store'])->name('parametros.store');
        Route::put('parametros/{parametro}', [ParametroPeriodoController::class, 'update'])->name('parametros.update');

        Route::post('tasas-afp', [TasaAfpController::class, 'store'])->name('tasas-afp.store');
        Route::put('tasas-afp/{tasa}', [TasaAfpController::class, 'update'])->name('tasas-afp.update');
        Route::delete('tasas-afp/{tasa}', [TasaAfpController::class, 'destroy'])->name('tasas-afp.destroy');

        Route::post('polizas-vida-ley', [PolizaVidaLeyController::class, 'store'])->name('polizas-vida-ley.store');
        Route::put('polizas-vida-ley/{poliza}', [PolizaVidaLeyController::class, 'update'])->name('polizas-vida-ley.update');
        Route::delete('polizas-vida-ley/{poliza}', [PolizaVidaLeyController::class, 'destroy'])->name('polizas-vida-ley.destroy');
    });
});

require __DIR__.'/auth.php';
