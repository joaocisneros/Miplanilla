<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\PlanillaService;
use App\Domain\Planilla\ValidadorAsistenciaPeriodo;
use App\Exports\PlanillaDetalleExport;
use App\Exports\PlanillaClienteExport;
use App\Models\Empresa;
use App\Models\Payroll;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PlanillaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;

        $periodos = Periodo::with(['empresa:id,razon_social,nombre_comercial'])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('quincena')
            ->get()
            ->map(function ($p) {
                $payroll = Payroll::with('detalles')->where('periodo_id', $p->id)->first();
                // Los de honorarios (RxH) no cuentan aquí: tienen su propio módulo y sus
                // propios totales, para no mezclar cifras con la planilla regular. Se usa
                // la modalidad CONGELADA en el detalle (no la actual del empleado), porque
                // si el trabajador cambia de modalidad después, este periodo ya calculado
                // no debe reclasificarse solo.
                $dets = $payroll?->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios');

                return [
                    'id' => $p->id,
                    'descripcion' => $p->descripcion,
                    'empresa' => $p->empresa?->nombre_comercial ?? $p->empresa?->razon_social,
                    'fecha_inicio' => $p->fecha_inicio->toDateString(),
                    'fecha_fin' => $p->fecha_fin->toDateString(),
                    'estado' => $p->estado,
                    'payroll' => $payroll ? [
                        'id' => $payroll->id,
                        'estado' => $payroll->estado,
                        'total_neto' => round($dets->sum('neto'), 2),
                        'cantidad_empleados' => $dets->count(),
                    ] : null,
                ];
            });

        return Inertia::render('Planilla/Index', [
            'periodos' => $periodos,
            'filtros' => ['empresa_id' => $empresaId],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    public function storePeriodo(Request $request, ValidadorAsistenciaPeriodo $validador)
    {
        $data = $this->validarPeriodo($request, conEmpresa: true);

        if ($mensaje = $validador->mensaje(new Periodo($data))) {
            return back()->with('error', $mensaje);
        }

        if ($error = $this->conflictoQuincenaMensual($data['empresa_id'], (int) $data['anio'], (int) $data['mes'], $data['quincena'] ?? null)) {
            return back()->with('error', $error);
        }

        Periodo::create($data);

        return back()->with('success', 'Periodo creado.');
    }

    /** Elimina solamente períodos vacíos que todavía no tienen planilla generada. */
    public function destroyPeriodo(Periodo $periodo)
    {
        if (Payroll::where('periodo_id', $periodo->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: este período ya tiene planilla u honorarios generados.');
        }

        $periodo->delete();

        return back()->with('success', 'Período vacío eliminado correctamente.');
    }

    /**
     * Un mes se maneja por quincenas O mensual, nunca ambos: mezclar duplica
     * los pagos en el Consolidado y los reportes. Devuelve el mensaje de error
     * si el periodo pedido choca con lo que ya existe.
     */
    private function conflictoQuincenaMensual(int $empresaId, int $anio, int $mes, ?int $quincena): ?string
    {
        $base = Periodo::where('empresa_id', $empresaId)->where('anio', $anio)->where('mes', $mes);

        if ($quincena === null && (clone $base)->whereNotNull('quincena')->exists()) {
            return 'Este mes ya se maneja por QUINCENAS: generar el "mes completo" duplicaría los pagos. Usa 1ra o 2da quincena.';
        }
        if ($quincena !== null && (clone $base)->whereNull('quincena')->exists()) {
            return 'Este mes ya tiene una planilla MENSUAL: generar quincenas duplicaría los pagos. Elimina primero la mensual o usa ese periodo.';
        }

        return null;
    }

    /**
     * Crea (si no existe) el mismo periodo en TODAS las empresas y genera cada
     * planilla por separado. Agiliza la operación sin mezclar empresas:
     * cada una conserva su planilla independiente (SUNAT/SUNAFIL por separado).
     */
    public function generarTodas(Request $request, PlanillaService $service, ValidadorAsistenciaPeriodo $validador)
    {
        $data = $this->validarPeriodo($request);

        $empresas = Empresa::where('activo', true)->get();
        $generadas = 0;

        foreach ($empresas as $empresa) {
            $periodoValidar = new Periodo(array_merge($data, ['empresa_id' => $empresa->id]));
            if ($mensaje = $validador->mensaje($periodoValidar)) {
                return back()->with('error', $mensaje);
            }
        }

        foreach ($empresas as $empresa) {
            // Candado quincena/mensual: si choca, se salta esa empresa.
            if ($this->conflictoQuincenaMensual($empresa->id, (int) $data['anio'], (int) $data['mes'], $data['quincena'] ?? null)) {
                continue;
            }

            $periodo = Periodo::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'anio' => $data['anio'],
                    'mes' => $data['mes'],
                    'quincena' => $data['quincena'] ?? null,
                ],
                $data
            );

            if ($periodo->estado === 'cerrado') {
                continue;
            }

            $service->generar($periodo, $request->user()->id);
            $periodo->update(['estado' => 'calculado']);
            $generadas++;
        }

        if ($generadas === 0) {
            return back()->with('error', 'No se generó ninguna planilla: el periodo elegido choca con periodos existentes (quincenas vs mes completo) o todo está cerrado.');
        }

        return back()->with('success', "Planillas generadas en {$generadas} empresa(s), cada una por separado.");
    }

    public function generar(Request $request, Periodo $periodo, PlanillaService $service, ValidadorAsistenciaPeriodo $validador)
    {
        abort_if($periodo->estado === 'cerrado', 403, 'El periodo está cerrado.');

        if ($mensaje = $validador->mensaje($periodo)) {
            return back()->with('error', $mensaje);
        }

        $service->generar($periodo, $request->user()->id);
        $periodo->update(['estado' => 'calculado']);

        return back()->with('success', 'Planilla generada.');
    }

    public function show(Request $request, Payroll $payroll): Response
    {
        $payroll->load(['periodo', 'empresa', 'detalles.employee:id,apellido_paterno,apellido_materno,nombres,modalidad', 'detalles.employee.contratoVigente']);

        // Solo empleados de PLANILLA aquí; los honorarios (RxH) van en su propio módulo.
        // Se usa la modalidad congelada en el detalle, no la actual del empleado.
        $dets = $payroll->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios')->values();

        // Registros de origen de los "extras" (horas extra, sabados, domingos y bonos),
        // para poder revisar en pantalla de donde sale cada monto. Usa la misma regla
        // de periodo que el motor de calculo (ver PlanillaService).
        $periodo = $payroll->periodo;
        $adicQuery = \App\Models\IngresoAdicional::where('empresa_id', $periodo->empresa_id)
            ->where('anio', $periodo->anio)
            ->where('mes', $periodo->mes);
        $adicionales = $periodo->quincena === null
            ? (clone $adicQuery)->whereNull('quincena')->get()
            : (clone $adicQuery)->where('quincena', $periodo->quincena)->get();
        if ($periodo->quincena === null && $adicionales->isEmpty()) {
            $adicionales = (clone $adicQuery)->whereIn('quincena', [1, 2])->get();
        }
        $adicPorEmpleado = $adicionales->groupBy('employee_id');
        $usuariosAdic = \App\Models\User::whereIn('id', $adicionales->pluck('registrado_por')->filter()->unique())
            ->pluck('name', 'id');

        return Inertia::render('Planilla/Show', [
            'payroll' => [
                'id' => $payroll->id,
                'estado' => $payroll->estado,
                'descripcion' => $payroll->periodo->descripcion,
                'quincena' => $payroll->periodo->quincena,
                'es_mensual' => $payroll->periodo->quincena === null,
                'empresa' => $payroll->empresa->razon_social,
                'total_ingresos' => round($dets->sum('total_ingresos'), 2),
                'total_descuentos' => round($dets->sum('total_descuentos'), 2),
                'total_neto' => round($dets->sum('neto'), 2),
                'total_aportes_empleador' => round($dets->sum(fn ($d) => (float) $d->essalud + (float) $d->sctr_pension + (float) $d->sctr_salud + (float) $d->vida_ley + (float) $d->senati), 2),
                'cantidad_empleados' => $dets->count(),
            ],
            'detalles' => $dets->map(function ($d) use ($adicPorEmpleado, $usuariosAdic) {
                $c = $d->employee?->contratoVigente->first();
                $sistema = $c?->sistema_pensiones === 'AFP'
                    ? 'AFP '.($c->afp ?? '')
                    : ($c?->sistema_pensiones ?? '—');

                return [
                    'id' => $d->id,
                    'empleado' => $d->employee?->nombre_completo,
                    'sistema' => trim($sistema),
                    'base_afecta' => $d->base_afecta,
                    'total_ingresos' => $d->total_ingresos,
                    'total_descuentos' => $d->total_descuentos,
                    'pension_total' => $d->pension_total,
                    'renta_5ta' => $d->renta_5ta,
                    'rem_neta_quincenal' => $d->desglose['bloques']['remuneracion_neta_quincenal'] ?? null,
                    'total_movilidad' => $d->desglose['bloques']['total_movilidad_quincenal'] ?? null,
                    'neto' => $d->neto,
                    'desglose' => $d->desglose,
                    'adicionales' => ($adicPorEmpleado[$d->employee_id] ?? collect())->map(fn ($a) => [
                        'id' => $a->id,
                        'horas' => (int) $a->horas,
                        'minutos' => (int) $a->minutos,
                        'monto_horas' => (float) $a->monto_horas,
                        'aprobado' => (bool) $a->aprobado,
                        'sabado' => (float) $a->sabado,
                        'domingo_feriado' => (float) $a->domingo_feriado,
                        'bono' => (float) $a->bono,
                        'otros_afectos' => (float) $a->otros_afectos,
                        'nota' => $a->nota,
                        'registrado_por' => $usuariosAdic[$a->registrado_por] ?? null,
                        'fecha' => $a->created_at?->format('d/m/Y H:i'),
                    ])->values(),
                ];
            }),
        ]);
    }

    /**
     * Exporta la planilla DETALLADA a Excel: una fila por trabajador con todas
     * sus columnas (básico, movilidad, HE, sábado, incentivos, pensión, renta 5ta,
     * adelantos, neto, aportes del empleador). Es el "extraíble a Excel" del cliente.
     */
    public function exportDetalle(Payroll $payroll)
    {
        @set_time_limit(180);
        $payroll->load([
            'periodo', 'empresa',
            'detalles.employee:id,numero_documento,apellido_paterno,apellido_materno,nombres,fecha_nacimiento,genero',
            'detalles.employee.contratoVigente.cargo:id,nombre',
            'detalles.employee.contratoVigente.area:id,nombre',
        ]);

        $detalles = $payroll->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios');
        $n = fn ($v) => round((float) $v, 2);
        // El cliente prefiere la celda vacia antes que un 0.00: asi de un vistazo
        // se ve solo lo que realmente tiene monto. null hace que no se escriba.
        $b = fn ($v) => ((float) $v) == 0.0 ? null : round((float) $v, 2);
        $employeeIds = $detalles->pluck('employee_id')->all();
        $resumenQuery = \App\Models\AsistenciaResumen::where('empresa_id', $payroll->empresa_id)
            ->whereIn('employee_id', $employeeIds)
            ->where('anio', $payroll->periodo->anio)->where('mes', $payroll->periodo->mes);
        $resumenes = $payroll->periodo->quincena === null
            ? $resumenQuery->get()->groupBy('employee_id')
            : $resumenQuery->where('quincena', $payroll->periodo->quincena)->get()->groupBy('employee_id');
        $estados = \App\Models\Attendance::where('empresa_id', $payroll->empresa_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('fecha', [$payroll->periodo->fecha_inicio, $payroll->periodo->fecha_fin])
            ->get()->groupBy('employee_id');

        // Gratificaciones del año, para las columnas de julio y diciembre.
        $gratis = \DB::table('gratificaciones')
            ->where('empresa_id', $payroll->empresa_id)
            ->where('anio', $payroll->periodo->anio)
            ->whereIn('employee_id', $employeeIds)
            ->get()->groupBy('employee_id');

        $rows = [];
        $i = 1;
        foreach ($detalles as $d) {
            $e = $d->employee;
            $c = $e?->contratoVigente->first();
            $g = (array) $d->desglose;
            $ing = $g['ingresos'] ?? [];
            $desc = $g['descuentos'] ?? [];
            $pen = $desc['pension'] ?? [];
            $penDet = $pen['detalle'] ?? [];
            $asis = $g['asistencia'] ?? [];
            $ap = $g['aportes_empleador'] ?? [];
            $sistemaBase = strtoupper((string) ($penDet['sistema'] ?? $c?->sistema_pensiones ?? ''));
            $afpNombre = strtoupper((string) ($penDet['afp'] ?? $c?->afp ?? ''));
            $tipoAfp = strtolower((string) ($penDet['tipo'] ?? $c?->tipo_afp ?? ''));
            $tipoTexto = $tipoAfp === 'mixta' ? 'MIXTA' : ($tipoAfp !== '' ? 'FLUJO' : '');
            $sistema = $sistemaBase === 'AFP' ? trim($afpNombre.' '.$tipoTexto) : $sistemaBase;
            $resumenEmp = $resumenes->get($d->employee_id, collect());
            // Para mensual se prefiere el resumen mensual; si no existe, se suman
            // primera y segunda quincena, igual que en el motor de cálculo.
            $resumenMensual = $resumenEmp->first(fn ($r) => $r->quincena === null);
            $resumenUsado = $resumenMensual ? collect([$resumenMensual]) : $resumenEmp;
            $regs = $estados->get($d->employee_id, collect());
            $contar = fn (string $estado) => $regs->where('estado', $estado)->count();
            $vacDias = $resumenUsado->isNotEmpty() ? (int) $resumenUsado->sum('vacaciones') : $contar('VACACIONES');
            $licDias = $resumenUsado->isNotEmpty() ? (int) $resumenUsado->sum('licencia') : $contar('LICENCIA');
            $asigMensual = $n($asis['asignacion_familiar'] ?? 0);
            $movMensual = $n($asis['movilidad_mensual'] ?? 0);
            $porFuera = $n($c?->otros ?? 0);
            $afpAporte = $n($pen['aporte'] ?? 0);
            $afpComision = $n($pen['comision'] ?? 0);
            $afpPrima = $n($pen['prima'] ?? 0);
            $adelanto = $n($desc['adelantos'] ?? 0);

            $gratEmp = $gratis->get($d->employee_id, collect());
            $gratJul = $n($gratEmp->firstWhere('tipo', 'julio')->monto ?? 0);
            $gratDic = $n($gratEmp->firstWhere('tipo', 'diciembre')->monto ?? 0);
            $movQuincenal = $n($g['bloques']['total_movilidad_quincenal'] ?? 0);
            // El cliente llama "TOTAL REM NETA BASICA" a la base afecta: es el
            // monto ANTES de descontar AFP/ONP (su hoja resta la pensión después).
            $baseAfecta = $n($d->base_afecta);
            // "TOTAL NETO A PAGAR" es antes de adelantos; "NETO A PAGAR" ya los descuenta.
            $totalNeto = $n($g['bloques']['suma_neto'] ?? ($baseAfecta + $movQuincenal - (float) $d->pension_total - (float) $d->renta_5ta));

            // 53 columnas en el orden exacto de la hoja del cliente.
            // Los importes en cero van vacios; solo aparece lo que tiene valor.
            $rows[] = [
                /*  1 ITEM                */ $i++,
                /*  2 APELLIDO Y NOMBRES  */ $e?->nombre_completo,
                /*  3 FECHA DE NACIM.     */ $e?->fecha_nacimiento?->toDateString(),
                /*  4 DNI                 */ (string) $e?->numero_documento,
                /*  5 SUELDO BASICO       */ $b($c?->sueldo_basico ?? 0),
                /*  6 ASIG FAM            */ $b($asigMensual),
                /*  7 MOV                 */ $b($movMensual),
                /*  8 TOTAL MENSUAL       */ $b(($c?->sueldo_basico ?? 0) + $asigMensual + $movMensual),
                /*  9 DIAS TRBAJADOS      */ $b($asis['dias_trabajados'] ?? ($g['dias_trabajados'] ?? 0)),
                /* 10 DIAS FALTOS CANT    */ $b($asis['faltas'] ?? 0),
                /* 11 DIAS FALTOS DESCTO  */ $b($asis['descuento_faltas'] ?? 0),
                /* 12 SUBSIDIO DIAS       */ $b($contar('SUBSIDIO')),
                /* 13 SUBSIDIO MONTO      */ $b($ing['subsidio'] ?? 0),
                /* 14 ADELANTO GRAT       */ null, // pendiente: no se registra por concepto
                /* 15 ADELANTO BONIF GRAT */ null, // pendiente
                /* 16 ADELANTO VACACIONES */ null, // pendiente
                /* 17 DESCANSO DIAS       */ $b($contar('DESCANSO_MEDICO')),
                /* 18 DESCANSO MONTO      */ null, // pendiente: no se separa del subsidio
                /* 19 VACACIONES DIAS     */ $b($vacDias),
                /* 20 VACACIONES MONTO    */ $b($ing['vacaciones'] ?? 0),
                /* 21 GRATIFICACION JUL   */ $b($gratJul),
                /* 22 GRATIFICACION DIC   */ $b($gratDic),
                /* 23 LICENCIA            */ $b($ing['licencia'] ?? 0),
                /* 24 TARDANZAS CANT      */ $b($asis['minutos_tarde'] ?? 0),
                /* 25 TARDANZAS DESCUENTO */ $b($desc['tardanza'] ?? 0),
                /* 26 TOTAL REM NETA BAS. */ $b($baseAfecta),
                /* 27 HE HORAS            */ $b($resumenUsado->sum('horas_extra')),
                /* 28 HE MONTO            */ $b($ing['horas_extra'] ?? 0),
                /* 29 SABADOS DIAS        */ $b($resumenUsado->sum('sabado')),
                /* 30 SABADOS MONTO       */ $b($ing['sabado'] ?? 0),
                /* 31 DOM/FER DIAS        */ $b($resumenUsado->sum('feriados_domingos')),
                /* 32 DOM/FER MONTO       */ $b($ing['domingo_feriado'] ?? 0),
                /* 33 INSENTIVOS PROD.    */ null, // pendiente: hoy no se separa
                /* 34 INSENTIVOS OTROS    */ $b($ing['incentivos'] ?? 0),
                /* 35 TOTAL REM X MOVIL   */ $b($movQuincenal),
                /* 36 SISTEMA PENSIONES   */ trim($sistema) ?: null,
                /* 37 COM                 */ $sistemaBase === 'AFP' ? round((float) ($penDet['tasa_comision'] ?? 0), 4) : null,
                /* 38 (sin titulo) PRIMA  */ $sistemaBase === 'AFP' ? round((float) ($penDet['tasa_prima'] ?? 0), 4) : null,
                /* 39 ONP 13%             */ $sistemaBase === 'ONP' ? $b($afpAporte) : null,
                /* 40 AFP 10%             */ $sistemaBase === 'AFP' ? $b($afpAporte) : null,
                /* 41 AFP COMISION        */ $b($afpComision),
                /* 42 AFP PRIMA           */ $b($afpPrima),
                /* 43 DSCTO AFP           */ $sistemaBase === 'AFP' ? $b($d->pension_total) : null,
                /* 44 DESCUENTO AFP Y ONP */ $b($d->pension_total),
                /* 45 RTA. 5TA CATEG      */ $b($desc['renta_5ta'] ?? 0),
                /* 46 TOTAL NETO A PAGAR  */ $b($totalNeto),
                /* 47 ESSALUD 9%          */ $b($ap['essalud'] ?? 0),
                /* 48 SCTR PENSION        */ $b($ap['sctr_pension'] ?? 0),
                /* 49 SCTR SALUD          */ $b($ap['sctr_salud'] ?? 0),
                /* 50 SVL DL 688          */ $b($ap['vida_ley'] ?? 0),
                /* 51 ADELANTOS           */ $b($adelanto),
                /* 52 REINTEGRO           */ $b($g['reintegros'] ?? 0),
                /* 53 NETO A PAGAR        */ $b($d->neto),
            ];
        }

        $nombre = 'planilla_detallada_'.Str::slug($payroll->empresa->razon_social).'_'.Str::slug($payroll->periodo->descripcion).'.xlsx';
        $anioCorto = substr((string) $payroll->periodo->anio, -2);

        return Excel::download(new PlanillaClienteExport($rows, $anioCorto), $nombre);
    }

    private function exportDetalleAnterior(Payroll $payroll)
    {
        @set_time_limit(180);
        $payroll->load([
            'periodo', 'empresa',
            'detalles.employee:id,numero_documento,apellido_paterno,apellido_materno,nombres',
            'detalles.employee.contratoVigente.cargo:id,nombre',
        ]);

        // Honorarios (RxH) no va en este Excel: tiene su propio export en el módulo Honorarios.
        $detalles = $payroll->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios');

        $n = fn ($v) => round((float) $v, 2);

        // Orden pedido por el cliente: la pensión al costado del Sistema, y el
        // descuento de tardanza al costado de sus minutos.
        $headings = [
            'N°', 'DNI', 'Apellidos y Nombres', 'Cargo',
            'Sistema', 'Aporte pensión', 'Comisión', 'Prima',
            'Sueldo devengado', 'Movilidad', 'H. Extra', 'Sábado', 'Dom/Fer', 'Incentivo/Bono', 'Gratificación', 'Vacaciones',
            'TOTAL INGRESOS',
            'Días trab.', 'Faltas', 'Tardanza (min)', 'Desc. tardanza',
            'Renta 5ta', 'Adelantos',
            'TOTAL DESCUENTOS', 'Reintegros', 'NETO A PAGAR',
            'EsSalud', 'SCTR', 'Vida Ley',
        ];

        $rows = [];
        $i = 1;
        foreach ($detalles as $d) {
            $e = $d->employee;
            $c = $e?->contratoVigente->first();
            $g = (array) $d->desglose;
            $ing = $g['ingresos'] ?? [];
            $desc = $g['descuentos'] ?? [];
            $pen = $desc['pension'] ?? [];
            $penDet = $pen['detalle'] ?? [];
            $asis = $g['asistencia'] ?? [];
            $ap = $g['aportes_empleador'] ?? [];
            $sistema = ($penDet['sistema'] ?? '') === 'AFP' ? 'AFP '.($penDet['afp'] ?? '') : ($penDet['sistema'] ?? 'ONP');

            $rows[] = [
                $i++,
                (string) $e?->numero_documento,
                $e?->nombre_completo,
                $c?->cargo?->nombre ?? '',
                trim($sistema),
                $n($pen['aporte'] ?? 0),
                $n($pen['comision'] ?? 0),
                $n($pen['prima'] ?? 0),
                $n($ing['remuneracion_devengada'] ?? 0),
                $n($ing['movilidad'] ?? 0),
                $n($ing['horas_extra'] ?? 0),
                $n($ing['sabado'] ?? 0),
                $n($ing['domingo_feriado'] ?? 0),
                $n($ing['incentivos'] ?? 0),
                $n($ing['gratificacion'] ?? 0),
                $n($ing['vacaciones'] ?? 0),
                $n($d->total_ingresos),
                $asis['dias_trabajados'] ?? ($g['dias_trabajados'] ?? 0),
                $asis['faltas'] ?? 0,
                $asis['minutos_tarde'] ?? 0,
                $n($desc['tardanza'] ?? 0),
                $n($desc['renta_5ta'] ?? 0),
                $n($desc['adelantos'] ?? 0),
                $n($d->total_descuentos),
                $n($g['reintegros'] ?? 0),
                $n($d->neto),
                $n($ap['essalud'] ?? 0),
                $n(($ap['sctr_pension'] ?? 0) + ($ap['sctr_salud'] ?? 0)),
                $n($ap['vida_ley'] ?? 0),
            ];
        }

        $nombre = 'planilla_detallada_'.Str::slug($payroll->empresa->razon_social).'_'.Str::slug($payroll->periodo->descripcion).'.xlsx';

        // Columnas de dinero (1-based) y columna del NETO para el formato/color.
        $moneyCols = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 21, 22, 23, 24, 25, 26, 27, 28, 29];

        return Excel::download(new PlanillaDetalleExport($headings, $rows, $moneyCols, 26), $nombre);
    }

    public function cerrar(Request $request, Payroll $payroll, ValidadorAsistenciaPeriodo $validador)
    {
        if ($mensaje = $validador->mensaje($payroll->periodo)) {
            return back()->with('error', $mensaje);
        }
        $payroll->update(['estado' => 'cerrado', 'cerrado_at' => now()]);
        $payroll->periodo->update(['estado' => 'cerrado']);

        return back()->with('success', 'Planilla cerrada. Ya no se puede recalcular.');
    }

    /** Reabre un periodo cerrado. Solo ADMIN (ver rutas). */
    public function reabrir(Request $request, Payroll $payroll)
    {
        $payroll->update(['estado' => 'calculado', 'cerrado_at' => null]);
        $payroll->periodo->update(['estado' => 'calculado']);

        return back()->with('success', 'Planilla reabierta. Se puede volver a recalcular.');
    }

    private function validarPeriodo(Request $request, bool $conEmpresa = false): array
    {
        $reglas = [
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'fecha_pago' => ['nullable', 'date'],
        ];
        if ($conEmpresa) {
            $reglas['empresa_id'] = ['required', 'exists:empresas,id'];
        }

        return $request->validate($reglas);
    }
}
