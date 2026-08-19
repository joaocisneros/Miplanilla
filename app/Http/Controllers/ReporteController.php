<?php

namespace App\Http\Controllers;

use App\Exports\PlanillaDetalleExport;
use App\Exports\PlantillaExport;
use App\Models\Empresa;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte consolidado: suma de los totales FINALES de cada empresa
 * (cada una se calcula independiente; aquí solo se agregan los totales).
 */
class ReporteController extends Controller
{
    public function consolidado(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        [$porEmpresa, $totalGeneral] = $this->datosConsolidado($anio, $mes);

        return Inertia::render('Reportes/Consolidado', [
            'porEmpresa' => $porEmpresa,
            'totalGeneral' => $totalGeneral,
            'filtros' => ['anio' => $anio, 'mes' => $mes],
        ]);
    }

    /** Exporta el consolidado (con gastos y costo total) a Excel. */
    public function consolidadoExport(Request $request)
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        $empresa = trim((string) $request->input('empresa', ''));
        [$porEmpresa, $totalGeneral] = $this->datosConsolidado($anio, $mes);

        // Si se pide una empresa concreta (pestaña activa), exporta solo esa.
        if ($empresa !== '') {
            $porEmpresa = $porEmpresa->filter(fn ($e) => $e['empresa'] === $empresa)->values();
        }

        $headings = ['Empresa', 'RUC', 'Empleados', 'Ingresos', 'Descuentos', 'Neto a pagar',
            'EsSalud', 'SCTR', 'Vida Ley', 'SENATI', 'Aportes empleador', 'COSTO TOTAL'];
        $rows = $porEmpresa->map(fn ($e) => [
            $e['empresa'], $e['ruc'], $e['cantidad_empleados'], $e['total_ingresos'], $e['total_descuentos'],
            $e['total_neto'], $e['essalud'], $e['sctr'], $e['vida_ley'], $e['senati'],
            $e['total_aportes_empleador'], $e['costo_total'],
        ])->values()->all();

        // El TOTAL GENERAL solo cuando se exportan todas las empresas.
        if ($empresa === '') {
            $rows[] = ['TOTAL GENERAL', '', $totalGeneral['cantidad_empleados'], $totalGeneral['total_ingresos'],
                $totalGeneral['total_descuentos'], $totalGeneral['total_neto'], $totalGeneral['essalud'],
                $totalGeneral['sctr'], $totalGeneral['vida_ley'], $totalGeneral['senati'],
                $totalGeneral['total_aportes_empleador'], $totalGeneral['costo_total']];
        }

        $nombre = $empresa !== '' ? 'consolidado_'.\Illuminate\Support\Str::slug($empresa)."_{$anio}_{$mes}.xlsx" : "consolidado_{$anio}_{$mes}.xlsx";

        return Excel::download(new PlantillaExport($headings, $rows), $nombre);
    }

    /**
     * Calcula el consolidado por empresa con el desglose de gastos del empleador
     * (EsSalud, SCTR, Vida Ley, SENATI) y el COSTO TOTAL real de la planilla.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function datosConsolidado(int $anio, int $mes): array
    {
        $payrolls = Payroll::with(['empresa:id,razon_social,ruc', 'periodo', 'detalles'])
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->get();

        $porEmpresa = $payrolls->groupBy('empresa_id')->map(function ($grupo) {
            $emp = $grupo->first()->empresa;
            // Honorarios (RxH) tiene su propio módulo y reportes; no se mezcla aquí.
            // Usa la modalidad congelada en el detalle, no la actual del empleado.
            $det = $grupo->flatMap->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios');
            $sum = fn ($col) => round($det->sum(fn ($d) => (float) $d->$col), 2);

            $ingresos = $sum('total_ingresos');
            $essalud = $sum('essalud');
            $sctr = round($sum('sctr_pension') + $sum('sctr_salud'), 2);
            $vida = $sum('vida_ley');
            $senati = $sum('senati');
            $aportes = round($essalud + $sctr + $vida + $senati, 2);

            return [
                'empresa' => $emp->razon_social,
                'ruc' => $emp->ruc,
                // Trabajadores ÚNICOS (un trabajador cuenta una vez, no por quincena).
                'cantidad_empleados' => $det->pluck('employee_id')->unique()->count(),
                'total_ingresos' => $ingresos,
                'total_descuentos' => $sum('total_descuentos'),
                'total_neto' => $sum('neto'),
                'essalud' => $essalud,
                'sctr' => $sctr,
                'vida_ley' => $vida,
                'senati' => $senati,
                'total_aportes_empleador' => $aportes,
                // Lo que REALMENTE le cuesta la planilla a la empresa: bruto + aportes.
                'costo_total' => round($ingresos + $aportes, 2),
            ];
        })->values();

        $suma = fn ($c) => round($porEmpresa->sum($c), 2);
        $totalGeneral = [
            'cantidad_empleados' => $porEmpresa->sum('cantidad_empleados'),
            'total_ingresos' => $suma('total_ingresos'),
            'total_descuentos' => $suma('total_descuentos'),
            'total_neto' => $suma('total_neto'),
            'essalud' => $suma('essalud'),
            'sctr' => $suma('sctr'),
            'vida_ley' => $suma('vida_ley'),
            'senati' => $suma('senati'),
            'total_aportes_empleador' => $suma('total_aportes_empleador'),
            'costo_total' => $suma('costo_total'),
        ];

        return [$porEmpresa, $totalGeneral];
    }

    /**
     * Reporte de tributos y aportes por empresa y mes: cuánto se declara/paga
     * a SUNAT (PLAME), a la AFP (AFPnet) y a las aseguradoras (SCTR / Vida Ley).
     * Cada empresa se presenta por separado (SUNAT/SUNAFIL audita independiente).
     */
    public function tributos(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);

        $payrolls = Payroll::with(['empresa:id,razon_social,ruc', 'detalles', 'periodo'])
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->get();

        // Honorarios (RxH) no va en PLAME/AFPnet: no cuentan aquí (declaración distinta).
        // Usa la modalidad congelada en el detalle, no la actual del empleado.
        $porEmpresa = $payrolls->groupBy('empresa_id')->map(function ($grupo) {
            $det = $grupo->flatMap->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios');

            return $this->resumirEmpresa($grupo->first()->empresa, $det);
        })->values();

        $totalGeneral = [
            'base_imponible' => round($porEmpresa->sum('base_imponible'), 2),
            'essalud' => round($porEmpresa->sum('essalud'), 2),
            'onp' => round($porEmpresa->sum('onp'), 2),
            'renta_5ta' => round($porEmpresa->sum('renta_5ta'), 2),
            'senati' => round($porEmpresa->sum('senati'), 2),
            'total_sunat' => round($porEmpresa->sum('total_sunat'), 2),
            'afp_total' => round($porEmpresa->sum('afp_total'), 2),
            'sctr_total' => round($porEmpresa->sum('sctr_total'), 2),
            'vida_ley' => round($porEmpresa->sum('vida_ley'), 2),
            'total_seguros' => round($porEmpresa->sum('total_seguros'), 2),
        ];

        return Inertia::render('Reportes/Tributos', [
            'porEmpresa' => $porEmpresa,
            'totalGeneral' => $totalGeneral,
            'filtros' => ['anio' => $anio, 'mes' => $mes],
        ]);
    }

    /**
     * Reporte de Retenciones de 5ta categoría (renta): tabla persona × mes,
     * para ver cuánto se le retiene a cada trabajador a lo largo del año.
     */
    public function retenciones(Request $request): Response
    {
        $anio = (int) $request->input('anio', now()->year);
        $empresaId = $request->input('empresa_id') ?: null;
        [$filas, $totalesMes, $totalAnio] = $this->datosRetenciones($anio, $empresaId);

        return Inertia::render('Reportes/Retenciones', [
            'filas' => $filas,
            'totalesMes' => $totalesMes,
            'totalAnio' => $totalAnio,
            'filtros' => ['anio' => $anio, 'empresa_id' => $empresaId],
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    /** Exporta las retenciones de 5ta (persona × mes) a Excel. */
    public function retencionesExport(Request $request)
    {
        $anio = (int) $request->input('anio', now()->year);
        $empresaId = $request->input('empresa_id') ?: null;
        [$filas, $totalesMes, $totalAnio] = $this->datosRetenciones($anio, $empresaId);

        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
        $headings = array_merge(['DNI', 'Apellidos y Nombres', 'Empresa'], $meses, ['TOTAL AÑO']);
        $rows = $filas->map(function ($f) {
            $r = [$f['dni'], $f['nombre'], $f['empresa']];
            for ($m = 1; $m <= 12; $m++) {
                $r[] = $f['meses'][$m];
            }
            $r[] = $f['total'];
            return $r;
        })->all();

        // Meses (4-15) y TOTAL (16) como dinero; TOTAL resaltado. Solo 3 columnas
        // de identificación (DNI, Nombre, Empresa) → inmovilizar en D2.
        $moneyCols = range(4, 16);

        return Excel::download(new PlanillaDetalleExport($headings, $rows, $moneyCols, 16, 'D2'), "retenciones_5ta_{$anio}.xlsx");
    }

    /** Arma la matriz persona × mes de la renta de 5ta del año. */
    private function datosRetenciones(int $anio, ?int $empresaId): array
    {
        $payrolls = Payroll::with([
            'detalles:id,payroll_id,employee_id,modalidad,renta_5ta',
            'detalles.employee:id,numero_documento,apellido_paterno,apellido_materno,nombres',
            'periodo:id,mes',
            'empresa:id,razon_social',
        ])->whereHas('periodo', fn ($q) => $q->where('anio', $anio))
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->get();

        $trab = [];
        $totalesMes = array_fill(1, 12, 0.0);
        foreach ($payrolls as $p) {
            $mes = (int) $p->periodo->mes;
            foreach ($p->detalles as $d) {
                $e = $d->employee;
                // Honorarios (RxH) no tiene renta 5ta (es otra categoría); no va en este reporte.
                // Usa la modalidad congelada en el detalle, no la actual del empleado.
                if (! $e || ($d->modalidad ?? 'planilla') === 'honorarios') {
                    continue;
                }
                $trab[$e->id] ??= [
                    'dni' => (string) $e->numero_documento,
                    'nombre' => $e->nombre_completo,
                    'empresa' => $p->empresa->razon_social,
                    'meses' => array_fill(1, 12, 0.0),
                    'total' => 0.0,
                ];
                $r = (float) $d->renta_5ta;
                $trab[$e->id]['meses'][$mes] += $r;
                $trab[$e->id]['total'] += $r;
                $totalesMes[$mes] += $r;
            }
        }

        foreach ($trab as &$t) {
            foreach ($t['meses'] as $m => $v) {
                $t['meses'][$m] = round($v, 2);
            }
            $t['total'] = round($t['total'], 2);
        }
        unset($t);

        // Primero los que SÍ pagan (mayor total arriba), luego los de 0 por nombre.
        $filas = collect($trab)->sort(function ($a, $b) {
            return ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']);
        })->values();
        $totalesMes = array_map(fn ($v) => round($v, 2), $totalesMes);
        $totalAnio = round(array_sum($totalesMes), 2);

        return [$filas, $totalesMes, $totalAnio];
    }

    /** Agrega los detalles de una empresa en los buckets de tributos/aportes. */
    private function resumirEmpresa(Empresa $empresa, $detalles): array
    {
        $base = $essalud = $onp = $afp = $renta = $sctrP = $sctrS = $vida = $senati = 0.0;
        $nOnp = $nAfp = 0;
        $afpPorAfp = [];

        foreach ($detalles as $d) {
            $base += (float) $d->base_afecta;
            $essalud += (float) $d->essalud;
            $renta += (float) $d->renta_5ta;
            $sctrP += (float) $d->sctr_pension;
            $sctrS += (float) $d->sctr_salud;
            $vida += (float) $d->vida_ley;
            $senati += (float) $d->senati;

            $pen = $d->desglose['descuentos']['pension'] ?? [];
            $sistema = $pen['detalle']['sistema'] ?? null;

            if ($sistema === 'AFP') {
                $afp += (float) $d->pension_total;
                $nAfp++;
                $nombre = $pen['detalle']['afp'] ?? 'AFP';
                $afpPorAfp[$nombre] ??= ['afp' => $nombre, 'aporte' => 0.0, 'comision' => 0.0, 'prima' => 0.0, 'total' => 0.0, 'empleados' => 0];
                $afpPorAfp[$nombre]['aporte'] += (float) ($pen['aporte'] ?? 0);
                $afpPorAfp[$nombre]['comision'] += (float) ($pen['comision'] ?? 0);
                $afpPorAfp[$nombre]['prima'] += (float) ($pen['prima'] ?? 0);
                $afpPorAfp[$nombre]['total'] += (float) $d->pension_total;
                $afpPorAfp[$nombre]['empleados']++;
            } else {
                $onp += (float) $d->pension_total;
                $nOnp++;
            }
        }

        $r = fn ($v) => round($v, 2);
        $sctrTotal = $sctrP + $sctrS;
        $totalSunat = $essalud + $onp + $renta + $senati;

        return [
            'empresa_id' => $empresa->id,
            'empresa' => $empresa->razon_social,
            'ruc' => $empresa->ruc,
            'empleados' => $detalles->pluck('employee_id')->unique()->count(),
            'empleados_onp' => $nOnp,
            'empleados_afp' => $nAfp,
            'base_imponible' => $r($base),
            // --- SUNAT (PLAME) ---
            'essalud' => $r($essalud),
            'onp' => $r($onp),
            'renta_5ta' => $r($renta),
            'senati' => $r($senati),
            'total_sunat' => $r($totalSunat),
            // --- AFP (AFPnet) ---
            'afp_total' => $r($afp),
            'afp_detalle' => array_map(fn ($a) => array_merge($a, [
                'aporte' => $r($a['aporte']), 'comision' => $r($a['comision']),
                'prima' => $r($a['prima']), 'total' => $r($a['total']),
            ]), array_values($afpPorAfp)),
            // --- Seguros (pago a aseguradora) ---
            'sctr_pension' => $r($sctrP),
            'sctr_salud' => $r($sctrS),
            'sctr_total' => $r($sctrTotal),
            'vida_ley' => $r($vida),
            'total_seguros' => $r($sctrTotal + $vida),
        ];
    }

    /**
     * Exporta un resumen mensual de apoyo para revisar la declaración PLAME.
     * No reemplaza los archivos de importación ni la validación oficial SUNAT.
     */
    public function plame(Request $request): StreamedResponse
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        $empresaId = (int) $request->input('empresa_id');

        $empresa = Empresa::findOrFail($empresaId);

        $detalles = Payroll::where('empresa_id', $empresaId)
            ->whereHas('periodo', fn ($q) => $q->where('anio', $anio)->where('mes', $mes))
            ->with(['detalles.employee:id,nombres,apellido_paterno,apellido_materno,numero_documento'])
            ->get()
            ->flatMap->detalles
            // Los recibos por honorarios se declaran por otra vía y no deben
            // mezclarse con el resumen mensual de trabajadores en planilla.
            ->filter(fn ($detalle) => ($detalle->modalidad ?? 'planilla') !== 'honorarios');

        $headers = [
            'DNI', 'Trabajador', 'Sistema pensión', 'AFP', 'Base imponible',
            'EsSalud (9%)', 'ONP', 'AFP aporte', 'AFP comisión', 'AFP prima',
            'Renta 5ta', 'SCTR pensión', 'SCTR salud', 'Vida Ley', 'SENATI', 'Neto',
        ];

        $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($empresa->razon_social));
        $nombre = "resumen_para_plame_{$slug}_{$anio}_".str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.xlsx';

        // Una fila por trabajador por mes (PLAME es mensual): si hay varias
        // quincenas en el mes, se suman los montos del mismo trabajador.
        $porTrabajador = $detalles->groupBy('employee_id')->map(function ($filas) {
            $emp = $filas->first()->employee;
            $pen0 = $filas->first()->desglose['descuentos']['pension'] ?? [];
            $sistema = $pen0['detalle']['sistema'] ?? 'ONP';
            $afp = $sistema === 'AFP' ? ($pen0['detalle']['afp'] ?? '') : '';

            $sum = fn ($cb) => $filas->sum($cb);
            $sumPen = fn ($k) => $filas->sum(fn ($d) => (float) ($d->desglose['descuentos']['pension'][$k] ?? 0));

            return [
                'dni' => $emp?->numero_documento,
                'nombre' => $emp?->nombre_completo,
                'sistema' => $sistema,
                'afp' => $afp,
                'base' => $sum(fn ($d) => (float) $d->base_afecta),
                'essalud' => $sum(fn ($d) => (float) $d->essalud),
                'onp' => $sistema === 'ONP' ? $sum(fn ($d) => (float) $d->pension_total) : 0.0,
                'afp_aporte' => $sumPen('aporte'),
                'afp_comision' => $sumPen('comision'),
                'afp_prima' => $sumPen('prima'),
                'renta_5ta' => $sum(fn ($d) => (float) $d->renta_5ta),
                'sctr_pension' => $sum(fn ($d) => (float) $d->sctr_pension),
                'sctr_salud' => $sum(fn ($d) => (float) $d->sctr_salud),
                'vida_ley' => $sum(fn ($d) => (float) $d->vida_ley),
                'senati' => $sum(fn ($d) => (float) $d->senati),
                'neto' => $sum(fn ($d) => (float) $d->neto),
            ];
        })->sortBy('nombre')->values();

        return response()->streamDownload(function () use ($porTrabajador, $headers, $empresa, $anio, $mes) {
            $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $hoja = $libro->getActiveSheet();
            $hoja->setTitle('Resumen PLAME');
            $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            $hoja->mergeCells('A1:P1')->setCellValue('A1', 'RESUMEN MENSUAL PARA PLAME');
            $hoja->mergeCells('A2:P2')->setCellValue('A2', $empresa->razon_social.'  |  RUC '.$empresa->ruc);
            $hoja->mergeCells('A3:P3')->setCellValue('A3', 'Período: '.($meses[$mes] ?? $mes).' '.$anio.'  |  Trabajadores: '.$porTrabajador->count());
            $hoja->mergeCells('A4:P4')->setCellValue('A4', 'Documento de control previo. Verifique la información en el aplicativo oficial PLAME antes de presentar la declaración.');
            $hoja->fromArray($headers, null, 'A6');

            $fila = 7;
            foreach ($porTrabajador as $t) {
                $hoja->setCellValueExplicit('A'.$fila, (string) $t['dni'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $hoja->fromArray([
                    $t['nombre'], $t['sistema'], $t['afp'], (float) $t['base'], (float) $t['essalud'],
                    (float) $t['onp'], (float) $t['afp_aporte'], (float) $t['afp_comision'], (float) $t['afp_prima'],
                    (float) $t['renta_5ta'], (float) $t['sctr_pension'], (float) $t['sctr_salud'],
                    (float) $t['vida_ley'], (float) $t['senati'], (float) $t['neto'],
                ], null, 'B'.$fila);
                $fila++;
            }

            $filaTotal = $fila;
            $hoja->mergeCells('A'.$filaTotal.':D'.$filaTotal)->setCellValue('A'.$filaTotal, 'TOTALES');
            for ($col = 5; $col <= 16; $col++) {
                $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $hoja->setCellValue($letra.$filaTotal, $filaTotal > 7 ? '=SUM('.$letra.'7:'.$letra.($filaTotal - 1).')' : 0);
            }

            $borde = ['borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D7DEE8']]]];
            $hoja->getStyle('A1:P1')->applyFromArray(['font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']], 'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]]);
            $hoja->getStyle('A2:P3')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '17365D']], 'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]]);
            $hoja->getStyle('A4:P4')->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '7F6000']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']], 'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]]);
            $hoja->getStyle('A6:P6')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']], 'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true]]);
            $hoja->getStyle('A6:P'.$filaTotal)->applyFromArray($borde);
            $hoja->getStyle('A'.$filaTotal.':P'.$filaTotal)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '17365D']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2F0D9']]]);
            // El símbolo se encierra como literal: Excel puede intentar reparar
            // el libro si interpreta la barra de "S/" como parte del formato.
            $hoja->getStyle('E7:P'.$filaTotal)->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
            $hoja->getStyle('A7:D'.($filaTotal - 1))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');

            foreach (['A' => 15, 'B' => 38, 'C' => 18, 'D' => 16] as $col => $ancho) $hoja->getColumnDimension($col)->setWidth($ancho);
            foreach (range('E', 'P') as $col) $hoja->getColumnDimension($col)->setWidth(17);
            $hoja->getRowDimension(1)->setRowHeight(30);
            $hoja->getRowDimension(4)->setRowHeight(28);
            $hoja->getRowDimension(6)->setRowHeight(36);
            $hoja->freezePane('E7');
            $hoja->setAutoFilter('A6:P'.($filaTotal - 1));
            $hoja->setShowGridlines(false);
            $hoja->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
            $hoja->getPageMargins()->setTop(0.4)->setRight(0.3)->setLeft(0.3)->setBottom(0.4);

            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
