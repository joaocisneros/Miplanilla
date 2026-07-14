<?php

namespace App\Http\Controllers;

use App\Domain\Planilla\PlanillaService;
use App\Exports\PlanillaDetalleExport;
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

    public function storePeriodo(Request $request)
    {
        $data = $this->validarPeriodo($request, conEmpresa: true);

        if ($error = $this->conflictoQuincenaMensual($data['empresa_id'], (int) $data['anio'], (int) $data['mes'], $data['quincena'] ?? null)) {
            return back()->with('error', $error);
        }

        Periodo::create($data);

        return back()->with('success', 'Periodo creado.');
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
    public function generarTodas(Request $request, PlanillaService $service)
    {
        $data = $this->validarPeriodo($request);

        $empresas = Empresa::where('activo', true)->get();
        $generadas = 0;

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

    public function generar(Request $request, Periodo $periodo, PlanillaService $service)
    {
        abort_if($periodo->estado === 'cerrado', 403, 'El periodo está cerrado.');

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

        return Inertia::render('Planilla/Show', [
            'payroll' => [
                'id' => $payroll->id,
                'estado' => $payroll->estado,
                'descripcion' => $payroll->periodo->descripcion,
                'empresa' => $payroll->empresa->razon_social,
                'total_ingresos' => round($dets->sum('total_ingresos'), 2),
                'total_descuentos' => round($dets->sum('total_descuentos'), 2),
                'total_neto' => round($dets->sum('neto'), 2),
                'total_aportes_empleador' => round($dets->sum(fn ($d) => (float) $d->essalud + (float) $d->sctr_pension + (float) $d->sctr_salud + (float) $d->vida_ley + (float) $d->senati), 2),
                'cantidad_empleados' => $dets->count(),
            ],
            'detalles' => $dets->map(function ($d) {
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

    public function cerrar(Request $request, Payroll $payroll)
    {
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
