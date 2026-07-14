<?php

namespace App\Http\Controllers;

use App\Imports\AsistenciaImport;
use App\Models\Attendance;
use App\Models\Empresa;
use App\Models\Employee;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AsistenciaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());

        $registros = Attendance::with(['employee:id,apellido_paterno,apellido_materno,nombres', 'empresa:id,razon_social,nombre_comercial'])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->limit(500)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'empleado' => $a->employee?->nombre_completo,
                'empresa' => $a->empresa?->nombre_comercial ?? $a->empresa?->razon_social,
                'fecha' => $a->fecha->toDateString(),
                'estado' => $a->estado,
                'entrada' => $a->hora_entrada_real ? substr((string) $a->hora_entrada_real, 0, 5) : '—',
                'salida' => $a->hora_salida_real ? substr((string) $a->hora_salida_real, 0, 5) : '—',
                'minutos_tarde' => $a->minutos_tarde,
                'horas_extra' => $a->horas_extra,
                'he_aprobadas' => (bool) $a->horas_extra_aprobadas,
                'origen' => $a->origen,
            ]);

        return Inertia::render('Asistencia/Index', [
            'registros' => $registros,
            'filtros' => ['empresa_id' => $empresaId, 'desde' => $desde, 'hasta' => $hasta],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    /**
     * Registro manual día por día. Se elige empresa (y opcionalmente sede) + fecha;
     * lista todos los empleados marcados "Presente" (NORMAL) por defecto. El operador
     * solo cambia las excepciones (falta, tardanza, horas extra) de ese día.
     */
    public function diario(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;
        $sedeId = $request->input('sede_id') ?: null;
        $fecha = $request->input('fecha', now()->toDateString());

        $filas = collect();
        if ($empresaId) {
            $empleados = Employee::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
                ->orderBy('apellido_paterno')
                ->get(['id', 'apellido_paterno', 'apellido_materno', 'nombres', 'numero_documento']);

            $existentes = Attendance::where('empresa_id', $empresaId)
                ->whereDate('fecha', $fecha)
                ->get()
                ->keyBy('employee_id');

            // Turno de cada trabajador (para calcular tardanza y H.E. automáticas en pantalla).
            $turnos = \App\Models\Contract::whereIn('employee_id', $empleados->pluck('id'))
                ->where('activo', true)->with('turno:id,nombre,hora_entrada,hora_salida,hora_salida_sabado,trabaja_sabado,tolerancia_min')
                ->get()->keyBy('employee_id');

            $esSabado = \Carbon\Carbon::parse($fecha)->isSaturday();

            $filas = $empleados->map(function ($e) use ($existentes, $turnos, $esSabado) {
                $a = $existentes->get($e->id);
                $t = $turnos->get($e->id)?->turno;
                // Salida efectiva del día: si es sábado y el turno tiene salida especial, usa esa.
                $salidaTurno = $t
                    ? (($esSabado && $t->hora_salida_sabado) ? $t->hora_salida_sabado : $t->hora_salida)
                    : null;
                return [
                    'employee_id' => $e->id,
                    'documento' => $e->numero_documento,
                    'empleado' => $e->nombre_completo,
                    'estado' => $a->estado ?? 'NORMAL',
                    'entrada' => $a?->hora_entrada_real ? substr((string) $a->hora_entrada_real, 0, 5) : '',
                    'salida' => $a?->hora_salida_real ? substr((string) $a->hora_salida_real, 0, 5) : '',
                    'minutos_tarde' => $a?->minutos_tarde ?? 0,
                    'horas_extra' => $a ? (float) $a->horas_extra : 0,
                    'horas_extra_aprobadas' => (bool) ($a->horas_extra_aprobadas ?? false),
                    'observacion' => $a->observacion ?? null,
                    // Horario del turno para el cálculo automático en pantalla
                    'turno_entrada' => $t ? substr((string) $t->hora_entrada, 0, 5) : null,
                    'turno_salida' => $salidaTurno ? substr((string) $salidaTurno, 0, 5) : null,
                    'turno_tolerancia' => $t ? (int) $t->tolerancia_min : 0,
                    'turno_nombre' => $t?->nombre,
                ];
            });
        }

        return Inertia::render('Asistencia/Diario', [
            'fecha' => $fecha,
            'feriado' => \App\Models\Feriado::whereDate('fecha', $fecha)->value('nombre'),
            'filas' => $filas,
            'filtros' => ['empresa_id' => $empresaId, 'sede_id' => $sedeId],
            'estados' => $this->estadosDisponibles(),
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
        ]);
    }

    /**
     * Resumen mensual por trabajador: una fila por empleado con los totales del mes
     * (días trabajados, faltas, tardanza total, horas extra). Para revisar de un
     * vistazo antes de generar la planilla, sin ir día por día.
     */
    public function resumen(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;
        $anio = (int) ($request->input('anio') ?: now()->year);
        $mes = (int) ($request->input('mes') ?: now()->month);
        $quincena = $request->input('quincena') !== null && $request->input('quincena') !== ''
            ? (int) $request->input('quincena') : null;

        // Rango según quincena (1: 1-15, 2: 16-fin, null: mes completo).
        $base = Carbon::create($anio, $mes, 1);
        if ($quincena === 1) {
            $inicio = (clone $base)->startOfMonth();
            $fin = (clone $base)->day(15);
        } elseif ($quincena === 2) {
            $inicio = (clone $base)->day(16);
            $fin = (clone $base)->endOfMonth();
        } else {
            $inicio = (clone $base)->startOfMonth();
            $fin = (clone $base)->endOfMonth();
        }

        $trabajadoEstados = ['NORMAL', 'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO'];

        $filas = collect();
        if ($empresaId) {
            $empleados = Employee::where('empresa_id', $empresaId)->where('activo', true)
                ->orderBy('apellido_paterno')
                ->get(['id', 'apellido_paterno', 'apellido_materno', 'nombres', 'numero_documento']);

            // Cuadro resumen IMPORTADO (fuente preferida). En "Mes completo" se
            // suman las quincenas; en quincena específica, solo esa.
            $impQuery = \App\Models\AsistenciaResumen::where('empresa_id', $empresaId)
                ->where('anio', $anio)->where('mes', $mes);
            if ($quincena !== null) {
                $impQuery->where('quincena', $quincena);
            }
            $importados = $impQuery->get()->groupBy('employee_id');

            $porEmpleado = Attendance::where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->get()
                ->groupBy('employee_id');

            $filas = $empleados->map(function ($e) use ($porEmpleado, $importados, $trabajadoEstados) {
                // Si hay cuadro resumen importado, ese manda (suma si hay varias quincenas).
                if ($imp = $importados->get($e->id)) {
                    return [
                        'employee_id' => $e->id,
                        'documento' => $e->numero_documento,
                        'empleado' => $e->nombre_completo,
                        'dias_trabajados' => (float) $imp->sum('dias_trabajados'),
                        'faltas' => (int) $imp->sum('faltas'),
                        'tardanza_min' => (int) $imp->sum('tardanza_min'),
                        'horas_extra' => (float) $imp->sum('horas_extra'),
                        'he_minutos' => (int) round($imp->sum('horas_extra') * 60),
                        'he_dias' => 0,
                        'sabado' => (int) $imp->sum('sabado'),
                        'feriados_domingos' => (int) $imp->sum('feriados_domingos'),
                        'vacaciones' => (int) $imp->sum('vacaciones'),
                        'otros' => array_filter([
                            'SABADO' => (int) $imp->sum('sabado'), 'FERIADO/DOMINGO' => (int) $imp->sum('feriados_domingos'),
                            'VACACIONES' => (int) $imp->sum('vacaciones'), 'LICENCIA' => (int) $imp->sum('licencia'),
                        ]),
                        'con_datos' => true,
                        'importado' => true,
                        'dias' => [],
                    ];
                }

                $regs = $porEmpleado->get($e->id, collect());
                $otros = $regs->whereNotIn('estado', array_merge($trabajadoEstados, ['FALTA']))
                    ->groupBy('estado')->map->count();
                $heHoras = round((float) $regs->sum('horas_extra'), 2);

                return [
                    'employee_id' => $e->id,
                    'documento' => $e->numero_documento,
                    'empleado' => $e->nombre_completo,
                    'dias_trabajados' => $regs->whereIn('estado', $trabajadoEstados)->count(),
                    'faltas' => $regs->where('estado', 'FALTA')->count(),
                    'tardanza_min' => (int) $regs->sum('minutos_tarde'),
                    'horas_extra' => $heHoras,
                    'he_minutos' => (int) round($heHoras * 60),
                    'he_dias' => $regs->where('horas_extra', '>', 0)->count(),
                    'sabado' => $regs->where('estado', 'TRABAJO_SABADO')->count(),
                    'feriados_domingos' => $regs->whereIn('estado', ['TRABAJO_DOMINGO', 'TRABAJO_FERIADO'])->count(),
                    'vacaciones' => $regs->where('estado', 'VACACIONES')->count(),
                    'otros' => $otros->all(),
                    'con_datos' => $regs->isNotEmpty(),
                    'importado' => false,
                    'dias' => $regs->sortBy('fecha')->values()->map(fn ($a) => [
                        'fecha' => $a->fecha->toDateString(),
                        'estado' => $a->estado,
                        'minutos_tarde' => (int) $a->minutos_tarde,
                        'horas_extra' => (float) $a->horas_extra,
                        'observacion' => $a->observacion,
                    ]),
                ];
            });
        }

        return Inertia::render('Asistencia/Resumen', [
            'filas' => $filas,
            'filtros' => ['empresa_id' => $empresaId, 'anio' => $anio, 'mes' => $mes, 'quincena' => $quincena],
            'estados' => $this->estadosDisponibles(),
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    /** Guarda los días editados de UN trabajador (justificar tardanzas, corregir faltas…). */
    public function guardarEmpleadoMes(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'filas' => ['required', 'array'],
            'filas.*.fecha' => ['required', 'date'],
            'filas.*.estado' => ['required', 'string', 'in:'.implode(',', array_keys($this->estadosDisponibles()))],
            'filas.*.minutos_tarde' => ['nullable', 'integer', 'min:0', 'max:600'],
            'filas.*.horas_extra' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'filas.*.observacion' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['filas'] as $f) {
                $trabajado = in_array($f['estado'], ['NORMAL', 'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO'], true);
                Attendance::updateOrCreate(
                    ['employee_id' => $data['employee_id'], 'fecha' => $f['fecha']],
                    [
                        'empresa_id' => $data['empresa_id'],
                        'estado' => $f['estado'],
                        'minutos_tarde' => $trabajado ? ($f['minutos_tarde'] ?? 0) : 0,
                        'horas_extra' => $trabajado ? ($f['horas_extra'] ?? 0) : 0,
                        'observacion' => $f['observacion'] ?? null,
                        'origen' => 'manual',
                    ]
                );
            }
        });

        return back()->with('success', 'Asistencia del trabajador actualizada.');
    }

    /** Guarda (upsert) la asistencia de todos los empleados para una fecha. */
    public function guardarDiario(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'fecha' => ['required', 'date'],
            'filas' => ['required', 'array'],
            'filas.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'filas.*.estado' => ['required', 'string', 'in:'.implode(',', array_keys($this->estadosDisponibles()))],
            'filas.*.entrada' => ['nullable', 'date_format:H:i'],
            'filas.*.salida' => ['nullable', 'date_format:H:i'],
            'filas.*.minutos_tarde' => ['nullable', 'integer', 'min:0', 'max:600'],
            'filas.*.horas_extra' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'filas.*.horas_extra_aprobadas' => ['boolean'],
            'filas.*.observacion' => ['nullable', 'string', 'max:255'],
        ]);

        // Turno (hora de entrada/salida + tolerancia) por trabajador, para calcular tardanza y H.E.
        $turnos = \App\Models\Contract::whereIn('employee_id', collect($data['filas'])->pluck('employee_id'))
            ->where('activo', true)->with('turno:id,hora_entrada,hora_salida,hora_salida_sabado,trabaja_sabado,tolerancia_min')
            ->get()->keyBy('employee_id');

        $esSabado = \Carbon\Carbon::parse($data['fecha'])->isSaturday();

        DB::transaction(function () use ($data, $turnos) {
            foreach ($data['filas'] as $f) {
                // Solo guardamos tardanza/HE cuando corresponde a un día efectivamente trabajado.
                $trabajado = in_array($f['estado'], ['NORMAL', 'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO'], true);

                // Si se ingresó la hora de entrada y hay turno, se calcula la tardanza
                // automáticamente (entrada − (hora del turno + tolerancia)).
                $tarde = (int) ($f['minutos_tarde'] ?? 0);
                $turno = $turnos->get($f['employee_id'])?->turno;
                if ($trabajado && ! empty($f['entrada']) && $turno) {
                    $esperada = \Carbon\Carbon::createFromFormat('H:i:s', $turno->hora_entrada)
                        ->addMinutes((int) ($turno->tolerancia_min ?? 0));
                    $llegada = \Carbon\Carbon::createFromFormat('H:i', $f['entrada']);
                    $tarde = $llegada->gt($esperada) ? $esperada->diffInMinutes($llegada) : 0;
                }

                // Horas extra automáticas: hora de salida real − hora de salida del turno.
                // Si es sábado y el turno tiene salida especial (medio día), se usa esa.
                $salidaTurno = ($esSabado && $turno?->hora_salida_sabado) ? $turno->hora_salida_sabado : $turno?->hora_salida;
                $horasExtra = (float) ($f['horas_extra'] ?? 0);
                if ($trabajado && ! empty($f['salida']) && $salidaTurno) {
                    $finTurno = \Carbon\Carbon::createFromFormat('H:i:s', $salidaTurno);
                    $salidaReal = \Carbon\Carbon::createFromFormat('H:i', $f['salida']);
                    $horasExtra = $salidaReal->gt($finTurno)
                        ? round($finTurno->diffInMinutes($salidaReal) / 60, 2)
                        : 0.0;
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $f['employee_id'], 'fecha' => $data['fecha']],
                    [
                        'empresa_id' => $data['empresa_id'],
                        'estado' => $f['estado'],
                        'hora_entrada_real' => $trabajado ? ($f['entrada'] ?? null) : null,
                        'hora_salida_real' => $trabajado ? ($f['salida'] ?? null) : null,
                        'minutos_tarde' => $trabajado ? $tarde : 0,
                        'horas_extra' => $trabajado ? $horasExtra : 0,
                        'horas_extra_aprobadas' => $trabajado ? (bool) ($f['horas_extra_aprobadas'] ?? false) : false,
                        'observacion' => $f['observacion'] ?? null,
                        'origen' => 'manual',
                    ]
                );
            }
        });

        return back()->with('success', 'Asistencia del '.$data['fecha'].' guardada.');
    }

    /** Estados seleccionables en el registro manual (etiqueta en español). */
    private function estadosDisponibles(): array
    {
        // La aclaración entre paréntesis es solo visual: la importación ignora
        // el paréntesis, así los Excel viejos ("Falta") siguen importando.
        return [
            'NORMAL' => 'Presente',
            'FALTA' => 'Falta (descuenta el día)',
            'FALTA_JUSTIFICADA' => 'Falta justificada (pagada)',
            'VACACIONES' => 'Vacaciones',
            'LICENCIA' => 'Licencia con goce (pagada)',
            'LICENCIA_SIN_GOCE' => 'Licencia sin goce (descuenta)',
            'DESCANSO_MEDICO' => 'Descanso médico',
            'SUBSIDIO' => 'Subsidio (EsSalud)',
            'DESCANSO' => 'Descanso (día libre)',
            'LICENCIA_HIJO_ENFERMO' => 'Licencia hijo enfermo',
            'FERIADO' => 'Feriado (descansó)',
            'TRABAJO_SABADO' => 'Trabajó sábado (paga extra)',
            'TRABAJO_DOMINGO' => 'Trabajó domingo (paga extra)',
            'TRABAJO_FERIADO' => 'Trabajó feriado (paga extra)',
        ];
    }

    /** Quita la aclaración "(...)" de una etiqueta de estado, para comparar. */
    private function baseLabel(string $label): string
    {
        return mb_strtoupper(trim(preg_replace('/\s*\(.*\)\s*$/u', '', $label)));
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xlsm,xls,csv,txt'],
        ]);

        $import = new AsistenciaImport($data['empresa_id']);
        Excel::import($import, $request->file('archivo'));

        $msg = "{$import->importadas} registros importados.";
        if ($import->errores) {
            $msg .= ' Errores: '.implode(' | ', array_slice($import->errores, 0, 10));
            return back()->with('error', $msg);
        }

        return back()->with('success', $msg);
    }

    /** Descarga una plantilla Excel con los encabezados esperados. */
    public function plantilla()
    {
        $headers = ['dni', 'fecha', 'estado', 'minutos_tarde', 'horas_extra', 'hora_entrada', 'hora_salida'];
        $ejemplo = [['71246290', Carbon::now()->toDateString(), 'NORMAL', '0', '0', '08:00', '18:00']];

        return Excel::download(new \App\Exports\PlantillaExport($headers, $ejemplo), 'plantilla_asistencia.xlsx');
    }

    /**
     * Importa MARCACIONES de reloj biométrico (entrada/salida crudas) y las
     * convierte a asistencia calculando tardanza y HE contra el turno.
     */
    public function importMarcaciones(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xlsm,xls,csv,txt'],
        ]);

        $import = new \App\Imports\MarcacionesImport($data['empresa_id']);
        Excel::import($import, $request->file('archivo'));

        $msg = "{$import->importadas} días de asistencia generados desde el reloj.";
        if ($import->errores) {
            $msg .= ' Errores: '.implode(' | ', array_slice($import->errores, 0, 10));

            return back()->with('error', $msg);
        }

        return back()->with('success', $msg);
    }

    /** Plantilla Excel de marcaciones de reloj. */
    public function plantillaMarcaciones()
    {
        $headers = ['dni', 'codigo', 'fecha', 'entrada', 'salida'];
        $ejemplo = [['71246290', '', Carbon::now()->toDateString(), '07:58', '17:10']];

        return Excel::download(new \App\Exports\PlantillaExport($headers, $ejemplo), 'plantilla_marcaciones_reloj.xlsx');
    }

    /**
     * Descarga la PLANTILLA MENSUAL de asistencia (formato A: fila por día).
     * Trae los trabajadores (de una empresa o de todas) x todos los días del mes,
     * con menús desplegables de ESTADO y HE APROB. El que llena solo pone entrada/salida.
     */
    public function plantillaMensual(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $anio = (int) ($request->input('anio') ?: now()->year);
        $mes = (int) ($request->input('mes') ?: now()->month);
        $empresaId = $request->input('empresa_id') ?: null;

        $dias = Carbon::create($anio, $mes, 1)->daysInMonth;
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $diaSem = ['Sun' => 'Dom', 'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb'];
        $estados = array_values($this->estadosDisponibles());

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $lst = $ss->createSheet();
        $lst->setTitle('Listas');
        foreach ($estados as $i => $e) {
            $lst->setCellValue('A'.($i + 1), $e);
        }
        $lst->setCellValue('B1', 'SI');
        $lst->setCellValue('B2', 'NO');
        $lst->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $nEstados = count($estados);

        $Fill = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $Align = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $Border = \PhpOffice\PhpSpreadsheet\Style\Border::class;

        $empresas = Empresa::where('activo', true)->when($empresaId, fn ($q) => $q->where('id', $empresaId))->orderBy('id')->get();

        $sh = $ss->getSheet(0);
        $sh->setTitle('Asistencia');

        // Fila 1: título principal (barra azul)
        $sh->setCellValue('A1', 'CONTROL DE ASISTENCIA');
        $sh->mergeCells('A1:J1');
        $sh->getRowDimension(1)->setRowHeight(32);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('A1')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
        $sh->getStyle('A1')->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER)->setVertical($Align::VERTICAL_CENTER);

        // Fila 2: año / mes / empresa (destacado)
        $sub = 'AÑO: '.$anio.'         MES: '.mb_strtoupper($meses[$mes]);
        $sub .= $empresaId ? '         EMPRESA: '.mb_strtoupper((string) ($empresas->first()->nombre_comercial ?? $empresas->first()->razon_social ?? '')) : '         EMPRESA: TODAS';
        $sh->setCellValue('A2', $sub);
        $sh->mergeCells('A2:J2');
        $sh->getRowDimension(2)->setRowHeight(22);
        $sh->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1F4E78');
        $sh->getStyle('A2')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
        $sh->getStyle('A2')->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER)->setVertical($Align::VERTICAL_CENTER);

        // Fila 3: instrucciones
        $sh->setCellValue('A3', 'Escribe la hora COMPLETA con minutos (ej: 07:23, 13:00, 18:30). El sistema calcula la tardanza y las horas extra. Cambia el ESTADO si faltó/vacaciones/trabajó sábado. Filtra por EMPRESA con la flechita ▼.');
        $sh->mergeCells('A3:J3');
        $sh->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('C00000');

        // Fila 4: encabezados (barra celeste, texto blanco)
        $headers = ['EMPRESA', 'DNI', 'NOMBRE', 'FECHA', 'DIA', 'ESTADO', 'ENTRADA (hh:mm)', 'SALIDA (hh:mm)', 'HE APROB', 'OBSERVACION', 'MODALIDAD'];
        $sh->fromArray($headers, null, 'A4');
        $sh->getRowDimension(4)->setRowHeight(20);
        $sh->getStyle('A4:K4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('A4:K4')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sh->getStyle('A4:K4')->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER)->setVertical($Align::VERTICAL_CENTER);

        $turnosEmp = $this->turnosPorEmpleado();
        $feriados = $this->feriadosMap((int) $anio);
        $lblFeriado = $this->estadosDisponibles()['FERIADO'];
        $r = 5;
        foreach ($empresas as $emp) {
            $ne = $emp->nombre_comercial ?: $emp->razon_social;
            foreach (Employee::where('empresa_id', $emp->id)->where('activo', true)->orderBy('apellido_paterno')->get() as $e) {
                $turno = $turnosEmp[$e->id]?->turno;
                for ($d = 1; $d <= $dias; $d++) {
                    $f = Carbon::create($anio, $mes, $d);
                    $esFeriado = isset($feriados[$f->toDateString()]);
                    [$entDef, $salDef] = $esFeriado ? ['', ''] : $this->horarioPorDefecto($turno, $f);
                    $sh->setCellValue('A'.$r, $ne);
                    $sh->setCellValueExplicit('B'.$r, (string) $e->numero_documento, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sh->setCellValue('C'.$r, $e->apellido_paterno.' '.$e->nombres);
                    $sh->setCellValue('D'.$r, $f->format('d/m/Y'));
                    $sh->setCellValue('E'.$r, $diaSem[$f->format('D')]);
                    $sh->setCellValue('F'.$r, $esFeriado ? $lblFeriado : 'Presente');
                    // Pre-llenado con el horario del turno: el cliente solo cambia excepciones.
                    $sh->setCellValueExplicit('G'.$r, $entDef, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sh->setCellValueExplicit('H'.$r, $salDef, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sh->getCell('F'.$r)->getDataValidation()->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)->setAllowBlank(false)->setShowDropDown(true)->setFormula1('Listas!$A$1:$A$'.$nEstados);
                    $sh->getCell('I'.$r)->getDataValidation()->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)->setFormula1('Listas!$B$1:$B$2');
                    $sh->setCellValue('K'.$r, ($e->modalidad ?? 'planilla') === 'honorarios' ? 'RXH' : 'PLANILLA');
                    $r++;
                }
            }
        }
        $ult = $r - 1;
        if ($ult >= 5) {
            $sh->getStyle('B5:B'.$ult)->getNumberFormat()->setFormatCode('@');
            $sh->getStyle('G5:H'.$ult)->getNumberFormat()->setFormatCode('@');
            // Centrar columnas de fecha/día/estado/horas y borde suave
            $sh->getStyle('D5:I'.$ult)->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER);
            $sh->getStyle('K5:K'.$ult)->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER);
            $sh->getStyle('A4:K'.$ult)->getBorders()->getAllBorders()->setBorderStyle($Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
        }
        foreach (['A' => 20, 'B' => 12, 'C' => 26, 'D' => 11, 'E' => 5, 'F' => 17, 'G' => 9, 'H' => 9, 'I' => 10, 'J' => 24, 'K' => 12] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }
        $sh->setAutoFilter('A4:K4');
        $sh->freezePane('A5');

        $nombre = 'asistencia_'.$meses[$mes].'_'.$anio.($empresaId ? '_'.\Illuminate\Support\Str::slug($empresas->first()?->nombre_comercial ?: 'empresa') : '_TODAS').'.xlsx';
        $tmp = storage_path('app/'.uniqid('plan_').'.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($tmp);

        return response()->download($tmp, $nombre)->deleteFileAfterSend(true);
    }

    /**
     * Descarga el EXCEL ANUAL de asistencia: UN archivo con una pestaña por mes
     * (Enero…Diciembre), las 3 empresas y todos los días. El cliente lo llena todo
     * el año (por quincenas) y lo importa cuando quiera.
     */
    public function plantillaAnual(Request $request)
    {
        // El Excel anual (todas las empresas × todo el año) es pesado: evita cortes por tiempo/memoria.
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $anio = (int) ($request->input('anio') ?: now()->year);
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $diaSem = ['Sun' => 'Dom', 'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb'];
        $estados = array_values($this->estadosDisponibles());
        $Fill = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $Align = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $Border = \PhpOffice\PhpSpreadsheet\Style\Border::class;
        $DV = \PhpOffice\PhpSpreadsheet\Cell\DataValidation::class;

        // Si existe una plantilla con MACRO, se usa como molde para que la descarga
        // conserve el macro (horas + filtro). Si falla, cae a la generación sin macro.
        $tplPath = storage_path('app/plantillas/asistencia_macro.xlsm');
        if (file_exists($tplPath)) {
            try {
                return $this->plantillaAnualDesdeTemplate($tplPath, $anio, $meses, $diaSem, $DV);
            } catch (\Throwable $e) {
                // continúa con la generación normal (sin macro) de abajo
            }
        }

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $lst = $ss->createSheet();
        $lst->setTitle('Listas');
        foreach ($estados as $i => $e) {
            $lst->setCellValue('A'.($i + 1), $e);
        }
        $lst->setCellValue('B1', 'SI');
        $lst->setCellValue('B2', 'NO');
        $lst->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $nEst = count($estados);

        $sh = $ss->getSheet(0);
        $sh->setTitle('Asistencia');
        // Título
        $sh->setCellValue('A1', 'CONTROL DE ASISTENCIA '.$anio);
        $sh->mergeCells('A1:K1');
        $sh->getRowDimension(1)->setRowHeight(30);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(17)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('A1')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
        $sh->getStyle('A1')->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER)->setVertical($Align::VERTICAL_CENTER);
        // Fila 2: selector EMPRESA · Fila 3: selector MES (el macro filtra la tabla al cambiarlos).
        $empNombres = Empresa::where('activo', true)->orderBy('id')->get()
            ->map(fn ($e) => $e->nombre_comercial ?: $e->razon_social)->all();
        // --- EMPRESA (fila 2) ---
        $sh->getRowDimension(2)->setRowHeight(20);
        $sh->getStyle('A2:K2')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sh->setCellValue('A2', 'EMPRESA ▶');
        $sh->setCellValue('B2', 'TODAS');
        $sh->getCell('B2')->getDataValidation()->setType($DV::TYPE_LIST)->setShowDropDown(true)->setFormula1('"TODAS,'.implode(',', $empNombres).'"');
        $sh->getStyle('A2')->getFont()->setBold(true);
        $sh->getStyle('B2')->getFont()->setBold(true)->getColor()->setRGB('C00000');
        $sh->getStyle('B2')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sh->getStyle('B2')->getBorders()->getAllBorders()->setBorderStyle($Border::BORDER_THIN);
        // --- MES (fila 3) ---
        $sh->getRowDimension(3)->setRowHeight(20);
        $sh->getStyle('A3:K3')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sh->setCellValue('A3', 'MES ▶');
        $sh->setCellValue('B3', 'TODOS');
        $sh->getCell('B3')->getDataValidation()->setType($DV::TYPE_LIST)->setShowDropDown(true)->setFormula1('"TODOS,'.implode(',', array_slice($meses, 1)).'"');
        $sh->getStyle('A3')->getFont()->setBold(true);
        $sh->getStyle('B3')->getFont()->setBold(true)->getColor()->setRGB('C00000');
        $sh->getStyle('B3')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sh->getStyle('B3')->getBorders()->getAllBorders()->setBorderStyle($Border::BORDER_THIN);
        $sh->setCellValue('D3', 'Elige EMPRESA y MES aquí ▲ (se filtra solo). Escribe la hora: 8 ó 07:23.');
        $sh->getStyle('D3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('808080');
        // Encabezados (fila 4)
        $headers = ['EMPRESA', 'DNI', 'NOMBRE', 'MES', 'FECHA', 'DIA', 'ESTADO', 'ENTRADA', 'SALIDA', 'HE APROB', 'OBSERVACION', 'MODALIDAD'];
        $sh->fromArray($headers, null, 'A4');
        $sh->getStyle('A4:L4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('A4:L4')->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sh->getStyle('A4:L4')->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER);

        $turnosEmp = $this->turnosPorEmpleado();
        $feriados = $this->feriadosMap((int) $anio);
        $lblFeriado = $this->estadosDisponibles()['FERIADO'];
        $data = [];
        foreach (Empresa::where('activo', true)->orderBy('id')->get() as $emp) {
            $ne = $emp->nombre_comercial ?: $emp->razon_social;
            foreach (Employee::where('empresa_id', $emp->id)->where('activo', true)->orderBy('apellido_paterno')->get() as $e) {
                $mod = ($e->modalidad ?? 'planilla') === 'honorarios' ? 'RXH' : 'PLANILLA';
                $turno = $turnosEmp[$e->id]?->turno;
                for ($m = 1; $m <= 12; $m++) {
                    $dias = \Carbon\Carbon::create($anio, $m, 1)->daysInMonth;
                    for ($d = 1; $d <= $dias; $d++) {
                        $f = \Carbon\Carbon::create($anio, $m, $d);
                        $esFeriado = isset($feriados[$f->toDateString()]);
                        [$entDef, $salDef] = $esFeriado ? ['', ''] : $this->horarioPorDefecto($turno, $f);
                        $data[] = [$ne, (string) $e->numero_documento, $e->apellido_paterno.' '.$e->nombres, $meses[$m], $f->format('d/m/Y'), $diaSem[$f->format('D')], $esFeriado ? $lblFeriado : 'Presente', $entDef, $salDef, '', '', $mod];
                    }
                }
            }
        }
        $sh->fromArray($data, null, 'A5');
        $fin = count($data) + 4;
        $sh->getStyle('B5:B'.$fin)->getNumberFormat()->setFormatCode('@');   // DNI texto (ENTRADA/SALIDA General: "8" se completa al importar)
        $sh->getStyle('D5:J'.$fin)->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER);
        $sh->getStyle('L5:L'.$fin)->getAlignment()->setHorizontal($Align::HORIZONTAL_CENTER);
        $sh->getStyle('H5:I'.$fin)->getNumberFormat()->setFormatCode('@');
        $sh->getCell('G5')->getDataValidation()->setType($DV::TYPE_LIST)->setShowDropDown(true)->setFormula1('Listas!$A$1:$A$'.$nEst)->setSqref('G5:G'.$fin);
        $sh->getCell('J5')->getDataValidation()->setType($DV::TYPE_LIST)->setShowDropDown(true)->setAllowBlank(true)->setFormula1('Listas!$B$1:$B$2')->setSqref('J5:J'.$fin);
        foreach (['A' => 18, 'B' => 12, 'C' => 24, 'D' => 11, 'E' => 11, 'F' => 5, 'G' => 16, 'H' => 9, 'I' => 9, 'J' => 9, 'K' => 20, 'L' => 12] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }
        // La columna MES (D) queda oculta: el filtro del selector la usa, pero no estorba en la vista.
        $sh->getColumnDimension('D')->setVisible(false);
        $sh->setAutoFilter('A4:L4');
        $sh->freezePane('A5');

        $tmp = storage_path('app/'.uniqid('anual_').'.xlsx');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
        $writer->setPreCalculateFormulas(false); // no recalcular fórmulas: mucho más rápido
        $writer->save($tmp);
        $ss->disconnectWorksheets();
        unset($ss);

        return response()->download($tmp, 'ASISTENCIA_'.$anio.'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Arma el Excel anual usando la plantilla del cliente (que trae el MACRO) como molde.
     * Refresca datos del año pedido y PRE-LLENA con la asistencia ya registrada en el sistema,
     * para poder continuar donde se quedó. El macro (horas + filtro) se conserva.
     */
    private function plantillaAnualDesdeTemplate(string $tplPath, int $anio, array $meses, array $diaSem, string $DV)
    {
        $ss = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx')->load($tplPath);
        $sh = $ss->getSheetByName('Asistencia');
        $sh->setCellValue('A1', 'CONTROL DE ASISTENCIA '.$anio);

        // Refrescar lista de empresas del selector (por si cambió el roster)
        $empNombres = Empresa::where('activo', true)->orderBy('id')->get()
            ->map(fn ($e) => $e->nombre_comercial ?: $e->razon_social)->all();
        $sh->getCell('B2')->getDataValidation()->setType($DV::TYPE_LIST)->setShowDropDown(true)->setFormula1('"TODAS,'.implode(',', $empNombres).'"');
        $sh->setCellValue('B2', 'TODAS');
        $sh->setCellValue('B3', 'TODOS');

        // Asistencia YA registrada en el sistema (para continuar donde se quedó)
        $labels = $this->estadosDisponibles(); // code => label
        $registrada = Attendance::whereYear('fecha', $anio)->get()
            ->groupBy('employee_id')
            ->map(fn ($g) => $g->keyBy(fn ($a) => $a->fecha->toDateString()));

        $turnosEmp = $this->turnosPorEmpleado();
        $feriados = $this->feriadosMap((int) $anio);
        $data = [];
        foreach (Empresa::where('activo', true)->orderBy('id')->get() as $emp) {
            $ne = $emp->nombre_comercial ?: $emp->razon_social;
            foreach (Employee::where('empresa_id', $emp->id)->where('activo', true)->orderBy('apellido_paterno')->get() as $e) {
                $reg = $registrada[$e->id] ?? collect();
                $mod = ($e->modalidad ?? 'planilla') === 'honorarios' ? 'RXH' : 'PLANILLA';
                $turno = $turnosEmp[$e->id]?->turno;
                for ($m = 1; $m <= 12; $m++) {
                    $dias = \Carbon\Carbon::create($anio, $m, 1)->daysInMonth;
                    for ($d = 1; $d <= $dias; $d++) {
                        $f = \Carbon\Carbon::create($anio, $m, $d);
                        $a = $reg[$f->toDateString()] ?? null;
                        $esFeriado = isset($feriados[$f->toDateString()]);
                        $estado = $a ? ($labels[$a->estado] ?? 'Presente') : ($esFeriado ? ($labels['FERIADO'] ?? 'Feriado') : 'Presente');
                        // Sin registro: se pre-llena con el horario del turno (el cliente
                        // solo cambia las excepciones); si es feriado, sin horas. Con registro: la hora real.
                        [$entDef, $salDef] = $esFeriado ? ['', ''] : $this->horarioPorDefecto($turno, $f);
                        $ent = ($a && $a->hora_entrada_real) ? substr((string) $a->hora_entrada_real, 0, 5) : ($a ? '' : $entDef);
                        $sal = ($a && $a->hora_salida_real) ? substr((string) $a->hora_salida_real, 0, 5) : ($a ? '' : $salDef);
                        $he = ($a && $a->horas_extra_aprobadas) ? 'SI' : '';
                        $obs = $a->observacion ?? '';
                        $data[] = [$ne, (string) $e->numero_documento, $e->apellido_paterno.' '.$e->nombres, $meses[$m], $f->format('d/m/Y'), $diaSem[$f->format('D')], $estado, $ent, $sal, $he, $obs, $mod];
                    }
                }
            }
        }
        $oldFin = $sh->getHighestRow();
        // Si generamos MENOS filas que las que ya trae la plantilla, NO usamos removeRow
        // (borrar miles de filas una por una es lentísimo y llega a colgarse por minutos).
        // En su lugar rellenamos con filas en blanco: limpia los datos viejos (sin fugas de
        // otras empresas) en una sola escritura rápida.
        $filaVacia = array_fill(0, 12, '');
        while (count($data) + 4 < $oldFin) {
            $data[] = $filaVacia;
        }
        $sh->fromArray($data, null, 'A5');
        $newFin = count($data) + 4;
        $sh->getStyle('B5:B'.$newFin)->getNumberFormat()->setFormatCode('@');
        // Columna extra fuera del rango del macro (L): modalidad para diferenciar.
        $sh->setCellValue('L4', 'MODALIDAD');
        $sh->getStyle('L4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('L4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sh->getColumnDimension('L')->setWidth(12);
        // Extender el filtro para incluir MODALIDAD (el macro filtra por valores, no por rango).
        $sh->setAutoFilter('A4:L4');

        $tmp = storage_path('app/'.uniqid('anual_').'.xlsm');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
        $writer->setPreCalculateFormulas(false); // no recalcular fórmulas: mucho más rápido en archivos grandes
        $writer->save($tmp);
        $ss->disconnectWorksheets();
        unset($ss);

        return response()->download($tmp, 'ASISTENCIA_'.$anio.'.xlsm')->deleteFileAfterSend(true);
    }

    /**
     * Importa la PLANTILLA (una fila por trabajador y día). Lee TODAS las hojas
     * (soporta un Excel con una pestaña por mes). Empareja por DNI, calcula tardanza
     * y H.E. con el turno (sábado incluido). Idempotente: reimportar no duplica.
     */
    public function importMensual(Request $request)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xlsm,xls,csv,txt'],
            // Mes a importar (opcional): como la plantilla viene PRE-LLENADA con el
            // horario de todo el año, esto evita registrar meses que no se revisaron.
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $soloMes = (int) ($data['mes'] ?? 0);

        // Lee TODAS las hojas (soporta un Excel con una pestaña por mes).
        // Se lee directo con PhpSpreadsheet en modo "solo datos" (sin estilos/macros):
        // es MUCHO más rápido en archivos grandes (miles de filas) que cargar todo el formato.
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($data['archivo']->getRealPath());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($data['archivo']->getRealPath());
        $hojas = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $hojas[] = $sheet->toArray(null, false, false, false); // valores crudos, sin formato
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // 'PRESENTE' => 'NORMAL' — la clave va normalizada (sin paréntesis, mayúsculas)
        // para aceptar etiquetas viejas ("Falta") y nuevas ("Falta (descuenta el día)").
        $labelACodigo = [];
        foreach ($this->estadosDisponibles() as $codigo => $label) {
            $labelACodigo[$this->baseLabel($label)] = $codigo;
        }
        $want = ['DNI', 'FECHA', 'ESTADO', 'ENTRADA', 'SALIDA', 'HE APROB', 'OBSERVACION'];
        $trabajadoEstados = ['NORMAL', 'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO'];
        $turnos = [];
        $procesados = 0;
        $noEmp = [];
        $hojasLeidas = 0;

        DB::transaction(function () use ($hojas, $want, $labelACodigo, $trabajadoEstados, $soloMes, &$turnos, &$procesados, &$noEmp, &$hojasLeidas) {
            foreach ($hojas as $filas) {
                // Ubicar la fila de encabezados (DNI + FECHA) y mapear columnas (flexible).
                $hIdx = null;
                $cols = [];
                foreach ($filas as $i => $row) {
                    $upper = array_map(fn ($v) => mb_strtoupper(trim((string) $v)), $row);
                    if (in_array('DNI', $upper, true) && in_array('FECHA', $upper, true)) {
                        $hIdx = $i;
                        foreach ($upper as $idx => $h) {
                            foreach ($want as $w) {
                                if (! isset($cols[$w]) && (str_starts_with($h, $w) || $h === $w)) {
                                    $cols[$w] = $idx;
                                }
                            }
                        }
                        break;
                    }
                }
                if ($hIdx === null) {
                    continue; // hoja sin datos de asistencia (ej. "Listas")
                }
                $hojasLeidas++;

                for ($i = $hIdx + 1; $i < count($filas); $i++) {
                    $row = $filas[$i];
                    $dni = trim((string) ($row[$cols['DNI']] ?? ''));
                    $fechaRaw = $row[$cols['FECHA']] ?? '';
                    if ($dni === '' || $fechaRaw === '' || $fechaRaw === null) {
                        continue;
                    }

                    $estadoLabel = trim((string) ($row[$cols['ESTADO']] ?? 'Presente'));
                    $estado = $labelACodigo[$this->baseLabel($estadoLabel)] ?? 'NORMAL';
                    $entrada = $this->normHora($row[$cols['ENTRADA']] ?? '');
                    $salida = $this->normHora($row[$cols['SALIDA']] ?? '');
                    $heAprob = mb_strtoupper(trim((string) ($row[$cols['HE APROB']] ?? ''))) === 'SI';
                    $obs = trim((string) ($row[$cols['OBSERVACION']] ?? '')) ?: null;

                    // Saltar filas vacías: "Presente" sin horas y sin nada = día no marcado.
                    if ($estado === 'NORMAL' && $entrada === null && $salida === null && ! $obs) {
                        continue;
                    }

                    $emp = Employee::where('numero_documento', $dni)->first();
                    // Excel suele comerse el cero inicial del DNI: reintentar con pad a 8.
                    if (! $emp && ctype_digit($dni) && strlen($dni) < 8) {
                        $emp = Employee::where('numero_documento', str_pad($dni, 8, '0', STR_PAD_LEFT))->first();
                    }
                    if (! $emp) {
                        $noEmp[$dni] = true;
                        continue;
                    }
                    $fecha = $this->normFecha($fechaRaw);
                    if (! $fecha) {
                        continue;
                    }
                    // Las plantillas vienen PRE-LLENADAS con el horario del turno:
                    // los dias que aun no llegan no son asistencia real, se ignoran.
                    if ($fecha > now()->toDateString()) {
                        continue;
                    }
                    // Si se eligio un mes, solo entra ese mes (protege los meses no revisados).
                    if ($soloMes && (int) substr($fecha, 5, 2) !== $soloMes) {
                        continue;
                    }

                    if (! array_key_exists($emp->id, $turnos)) {
                        $turnos[$emp->id] = \App\Models\Contract::where('employee_id', $emp->id)->where('activo', true)
                            ->with('turno:id,hora_entrada,hora_salida,hora_salida_sabado,trabaja_sabado,tolerancia_min')->latest('id')->first()?->turno;
                    }
                    $turno = $turnos[$emp->id];
                    $trabajado = in_array($estado, $trabajadoEstados, true);
                    $esSabado = Carbon::parse($fecha)->isSaturday();

                    $tarde = 0;
                    if ($trabajado && $entrada && $turno) {
                        $esperada = Carbon::createFromFormat('H:i:s', $turno->hora_entrada)->addMinutes((int) ($turno->tolerancia_min ?? 0));
                        $lleg = Carbon::createFromFormat('H:i', $entrada);
                        $tarde = $lleg->gt($esperada) ? $esperada->diffInMinutes($lleg) : 0;
                    }
                    $he = 0.0;
                    $salidaTurno = ($esSabado && $turno?->hora_salida_sabado) ? $turno->hora_salida_sabado : $turno?->hora_salida;
                    if ($trabajado && $salida && $salidaTurno) {
                        $fin = Carbon::createFromFormat('H:i:s', $salidaTurno);
                        $sal = Carbon::createFromFormat('H:i', $salida);
                        $he = $sal->gt($fin) ? round($fin->diffInMinutes($sal) / 60, 2) : 0.0;
                    }

                    Attendance::updateOrCreate(
                        ['employee_id' => $emp->id, 'fecha' => $fecha],
                        [
                            'empresa_id' => $emp->empresa_id,
                            'estado' => $estado,
                            'hora_entrada_real' => $trabajado ? $entrada : null,
                            'hora_salida_real' => $trabajado ? $salida : null,
                            'minutos_tarde' => $trabajado ? $tarde : 0,
                            'horas_extra' => $trabajado ? $he : 0,
                            'horas_extra_aprobadas' => $trabajado ? $heAprob : false,
                            'observacion' => $obs,
                            'origen' => 'excel',
                        ]
                    );
                    $procesados++;
                }
            }
        });

        if ($hojasLeidas === 0) {
            return back()->withErrors(['archivo' => 'No se encontró la fila de encabezados (DNI, FECHA...). Usa la plantilla descargada.']);
        }

        $msg = "Asistencia importada: {$procesados} registros (de {$hojasLeidas} ".($hojasLeidas === 1 ? 'hoja' : 'meses').').';
        if ($noEmp) {
            $msg .= ' DNI no encontrados: '.implode(', ', array_keys($noEmp)).'.';
        }

        return back()->with('success', $msg);
    }

    /** Fechas de feriado (Y-m-d => nombre), para plantillas y avisos. */
    private function feriadosMap(?int $anio = null): array
    {
        // Automantenido: si el año pedido aun no tiene feriados, se generan solos.
        \App\Models\Feriado::asegurarAnio($anio ?? (int) now()->year);

        return \App\Models\Feriado::pluck('nombre', 'fecha')
            ->mapWithKeys(fn ($n, $f) => [substr((string) $f, 0, 10) => $n])->all();
    }

    /**
     * Horario por defecto del día según el turno del trabajador, para PRE-LLENAR
     * las plantillas (el cliente solo cambia las excepciones). Domingo va vacío;
     * sábado solo si el turno trabaja sábado.
     */
    private function horarioPorDefecto($turno, \Carbon\Carbon $f): array
    {
        $dow = (int) $f->format('N');
        if ($dow === 7) {
            // Domingo: solo turnos que trabajan domingo (ej. vigilancia con relevos).
            if ($turno && $turno->trabaja_domingo) {
                return [substr((string) $turno->hora_entrada, 0, 5), substr((string) $turno->hora_salida, 0, 5)];
            }

            return ['', ''];
        }
        if ($dow === 6) {
            if (! $turno || ! $turno->trabaja_sabado) {
                return ['', ''];
            }

            return [substr((string) $turno->hora_entrada, 0, 5),
                substr((string) ($turno->hora_salida_sabado ?: $turno->hora_salida), 0, 5)];
        }
        if (! $turno) {
            return ['07:00', '18:00'];
        }

        return [substr((string) $turno->hora_entrada, 0, 5), substr((string) $turno->hora_salida, 0, 5)];
    }

    /** Turno vigente por empleado (para pre-llenar horarios en las plantillas). */
    private function turnosPorEmpleado()
    {
        return \App\Models\Contract::where('activo', true)
            ->with('turno:id,hora_entrada,hora_salida,hora_salida_sabado,trabaja_sabado,trabaja_domingo')
            ->get()->keyBy('employee_id');
    }

    /** Normaliza una hora de celda ("07:23", "8", fracción de Excel, etc.) a "H:i" o null. */
    private function normHora($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        // Formato correcto "HH:MM" (lo recomendado).
        if (preg_match('/^(\d{1,2}):(\d{2})/', $s, $m)) {
            return sprintf('%02d:%02d', min((int) $m[1], 23), min((int) $m[2], 59));
        }
        if (is_numeric($s)) {
            $n = (float) $s;
            if ($n > 0 && $n < 1) { // fracción de día de Excel (00:00–23:59)
                $seg = (int) round($n * 86400);
                return sprintf('%02d:%02d', intdiv($seg, 3600) % 24, intdiv($seg % 3600, 60));
            }
            if ($n >= 1 && $n <= 24) { // hora entera tipeada: "8" -> 08:00, "8.5" -> 08:30
                $h = (int) floor($n);
                $min = (int) round(($n - $h) * 60);
                return sprintf('%02d:%02d', $h % 24, $min);
            }
        }
        return null;
    }

    /** Normaliza una fecha de celda ("d/m/Y", fracción de Excel, etc.) a "Y-m-d" o null. */
    private function normFecha($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, trim((string) $v))->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }
        try {
            return Carbon::parse(trim((string) $v))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Importa el CUADRO RESUMEN de asistencia por trabajador (lo que el cliente
     * ya calcula en su Excel). Es la fuente exacta de la planilla del periodo.
     */
    public function importResumen(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xlsm,xls,csv,txt'],
        ]);

        $import = new \App\Imports\ResumenAsistenciaImport(
            $data['empresa_id'], $data['anio'], $data['mes'],
            $data['quincena'] ?? null, $request->user()?->id
        );
        Excel::import($import, $request->file('archivo'));

        $msg = "{$import->importadas} trabajadores importados al resumen.";
        if ($import->errores) {
            $msg .= ' Errores: '.implode(' | ', array_slice($import->errores, 0, 10));

            return back()->with('error', $msg);
        }

        return back()->with('success', $msg.' Ya puedes generar la planilla de ese periodo.');
    }

    /**
     * Plantilla Excel del cuadro resumen. Si se indica empresa, se pre-llena con
     * los trabajadores (dni + nombre) para que solo completen los valores y puedan
     * corregir los DNI que falten.
     */
    public function plantillaResumen(Request $request)
    {
        $headers = ['dni', 'nombre', 'dias_trabajados', 'faltas', 'tardanza_min', 'horas_extra', 'horas_extra_min', 'he_aprobada', 'sabado_monto', 'domingo_monto', 'incentivo', 'vacaciones', 'licencia'];

        $empresaId = $request->integer('empresa_id') ?: null;
        $filas = [];

        if ($empresaId) {
            $filas = Employee::where('empresa_id', $empresaId)->where('activo', true)
                ->orderBy('apellido_paterno')->orderBy('apellido_materno')->get()
                ->map(fn ($e) => [
                    $e->numero_documento, $e->nombre_completo,
                    '', '0', '0', '0', '0', 'NO', '0', '0', '0', '0', '0',
                ])->all();
        }

        if (empty($filas)) {
            // Sin empresa: fila de ejemplo para que se vea el formato.
            $filas = [['71246290', 'EJEMPLO: APELLIDOS NOMBRES', '8', '7', '0', '6', '30', 'SI', '0', '0', '100', '0', '0']];
        }

        return Excel::download(new \App\Exports\PlantillaExport($headers, $filas), 'plantilla_cuadro_resumen.xlsx');
    }
}
