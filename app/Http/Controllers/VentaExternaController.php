<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\PagoVentaExterna;
use App\Models\PeriodoVentaExterna;
use App\Models\VendedorExterno;
use App\Models\VendedorExternoTarifa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VentaExternaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresasConfiguradas = VendedorExternoTarifa::where('activo', true)
            ->whereHas('vendedor', fn ($q) => $q->where('activo', true))
            ->distinct()->pluck('empresa_id');
        $periodos = PeriodoVentaExterna::with('empresa:id,razon_social,nombre_comercial')->withCount('pagos')
            ->withSum('pagos', 'pago_fijo')->withSum('pagos', 'comision')->withSum('pagos', 'total')
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('id')->get()->map(fn ($p) => [
                'id' => $p->id, 'empresa_id' => $p->empresa_id,
                'empresa' => $p->empresa?->nombre_comercial ?: $p->empresa?->razon_social,
                'anio' => $p->anio, 'mes' => $p->mes, 'estado' => $p->estado,
                'fecha_pago' => $p->fecha_pago?->toDateString(), 'pagos_count' => $p->pagos_count,
                'pago_fijo' => (float) ($p->pagos_sum_pago_fijo ?? 0),
                'comisiones' => (float) ($p->pagos_sum_comision ?? 0),
                'total' => (float) ($p->pagos_sum_total ?? 0),
            ]);
        $periodoId = $request->integer('periodo_id') ?: ($periodos->first()['id'] ?? null);
        $periodoSeleccionado = $periodoId
            ? PeriodoVentaExterna::with(['empresa:id,razon_social,nombre_comercial', 'pagos.vendedor'])->find($periodoId)
            : null;

        return Inertia::render('VentasExternas/Index', [
            'empresas' => Empresa::where('activo', true)->whereIn('id', $empresasConfiguradas)
                ->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'periodos' => $periodos,
            'periodoSeleccionado' => $periodoSeleccionado ? $this->periodoData($periodoSeleccionado) : null,
            'resumen' => [
                'vendedores_activos' => VendedorExterno::where('activo', true)->count(),
                'periodos_abiertos' => PeriodoVentaExterna::where('estado', 'abierto')->count(),
                'total_abierto' => (float) PagoVentaExterna::whereHas('periodo', fn ($q) => $q->where('estado', 'abierto'))->sum('total'),
            ],
        ]);
    }

    /** Reutiliza la consulta RENIEC ya validada para empleados. */
    public function consultaDni(string $dni)
    {
        return app(EmployeeController::class)->consultaDni($dni);
    }

    public function vendedores(): Response
    {
        $vendedores = VendedorExterno::with(['tarifas.empresa:id,razon_social,nombre_comercial'])
            ->orderBy('nombre')->get()->map(fn ($v) => [
                'id' => $v->id, 'tipo_documento' => $v->tipo_documento, 'numero_documento' => $v->numero_documento,
                'nombre' => $v->nombre, 'telefono' => $v->telefono, 'correo' => $v->correo, 'activo' => $v->activo,
                'tarifas' => $v->tarifas->map(fn ($t) => ['id' => $t->id, 'empresa_id' => $t->empresa_id,
                    'empresa' => $t->empresa?->nombre_comercial ?: $t->empresa?->razon_social, 'tipo_pago' => $t->tipo_pago,
                    'pago_fijo' => (float) $t->pago_fijo, 'tipo_comision' => $t->tipo_comision,
                    'porcentaje_comision' => (float) $t->porcentaje_comision, 'activo' => $t->activo]),
            ]);
        return Inertia::render('VentasExternas/Vendedores', [
            'vendedores' => $vendedores,
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    public function show(PeriodoVentaExterna $periodo): Response
    {
        $periodo->load(['empresa:id,razon_social,nombre_comercial', 'pagos.vendedor']);
        return Inertia::render('VentasExternas/Show', ['periodo' => $this->periodoData($periodo)]);
    }

    public function storeVendedor(Request $request)
    {
        $data = $this->validarVendedor($request);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));
        VendedorExterno::create($data + ['activo' => true]);
        return back()->with('success', 'Vendedor externo registrado.');
    }

    public function updateVendedor(Request $request, VendedorExterno $vendedor)
    {
        $data = $this->validarVendedor($request, $vendedor);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));
        $vendedor->update($data);
        return back()->with('success', 'Vendedor externo actualizado.');
    }

    private function validarVendedor(Request $request, ?VendedorExterno $vendedor = null): array
    {
        return $request->validate([
            'tipo_documento' => ['required', 'in:DNI,RUC,CE'],
            'numero_documento' => ['required', 'string', 'max:15', Rule::unique('vendedores_externos')->ignore($vendedor?->id)],
            'nombre' => ['required', 'string', 'max:255'], 'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'], 'activo' => ['sometimes', 'boolean'],
        ]);
    }

    public function storeTarifa(Request $request, VendedorExterno $vendedor)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'], 'tipo_pago' => ['required', 'in:fijo,comision,mixto'],
            'pago_fijo' => ['required', 'numeric', 'min:0'], 'tipo_comision' => ['required', 'in:porcentaje,manual'],
            'porcentaje_comision' => ['required_if:tipo_comision,porcentaje', 'numeric', 'min:0', 'max:100'],
        ]);
        VendedorExternoTarifa::updateOrCreate(
            ['vendedor_externo_id' => $vendedor->id, 'empresa_id' => $data['empresa_id']],
            $data + ['activo' => true]
        );
        return back()->with('success', 'Condición de pago guardada para la empresa.');
    }

    public function generar(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'], 'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'between:1,12'], 'fecha_pago' => ['nullable', 'date'],
        ]);
        $tarifas = VendedorExternoTarifa::with('vendedor')->where('empresa_id', $data['empresa_id'])
            ->where('activo', true)->whereHas('vendedor', fn ($q) => $q->where('activo', true))->get();
        if ($tarifas->isEmpty()) return back()->with('error', 'No hay vendedores activos con condiciones configuradas para esta empresa.');

        $periodo = DB::transaction(function () use ($data, $tarifas, $request) {
            $periodo = PeriodoVentaExterna::firstOrCreate(
                ['empresa_id' => $data['empresa_id'], 'anio' => $data['anio'], 'mes' => $data['mes']],
                ['estado' => 'abierto', 'fecha_pago' => $data['fecha_pago'] ?? null, 'creado_por' => $request->user()?->id]
            );
            if ($periodo->estado === 'cerrado') abort(422, 'El periodo ya está cerrado.');
            foreach ($tarifas as $t) {
                $fijo = in_array($t->tipo_pago, ['fijo', 'mixto']) ? (float) $t->pago_fijo : 0;
                PagoVentaExterna::firstOrCreate(
                    ['periodo_venta_externa_id' => $periodo->id, 'vendedor_externo_id' => $t->vendedor_externo_id],
                    ['tipo_pago' => $t->tipo_pago, 'pago_fijo' => $fijo, 'ventas' => 0,
                     'tipo_comision' => $t->tipo_comision, 'porcentaje_comision' => $t->porcentaje_comision,
                     'comision' => 0, 'bonos' => 0, 'adelantos' => 0, 'total' => $fijo]
                );
            }
            return $periodo;
        });
        return redirect()->route('ventas-externas.index', ['periodo_id' => $periodo->id])->with('success', 'Periodo generado. Completa las ventas y conceptos de pago.');
    }

    public function updatePago(Request $request, PagoVentaExterna $pago)
    {
        $pago->load('periodo');
        if ($pago->periodo->estado === 'cerrado') return back()->with('error', 'No se puede editar un periodo cerrado.');
        $data = $request->validate([
            'pago_fijo' => ['required', 'numeric', 'min:0'], 'ventas' => ['required', 'numeric', 'min:0'],
            'tipo_comision' => ['required', 'in:porcentaje,manual'], 'porcentaje_comision' => ['required', 'numeric', 'min:0', 'max:100'],
            'comision' => ['required', 'numeric', 'min:0'], 'bonos' => ['required', 'numeric', 'min:0'],
            'adelantos' => ['required', 'numeric', 'min:0'], 'observacion' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['comision'] = $data['tipo_comision'] === 'porcentaje'
            ? round((float) $data['ventas'] * (float) $data['porcentaje_comision'] / 100, 2)
            : round((float) $data['comision'], 2);
        $data['total'] = max(0, round((float) $data['pago_fijo'] + $data['comision'] + (float) $data['bonos'] - (float) $data['adelantos'], 2));
        $pago->update($data);
        return back()->with('success', 'Pago actualizado y recalculado.');
    }

    public function cerrar(Request $request, PeriodoVentaExterna $periodo)
    {
        $periodo->update(['estado' => 'cerrado', 'cerrado_por' => $request->user()?->id, 'cerrado_en' => now()]);
        return back()->with('success', 'Periodo de ventas externas cerrado.');
    }

    public function reabrir(PeriodoVentaExterna $periodo)
    {
        $periodo->update(['estado' => 'abierto', 'cerrado_por' => null, 'cerrado_en' => null]);
        return back()->with('success', 'Periodo reabierto.');
    }

    public function export(PeriodoVentaExterna $periodo)
    {
        $periodo->load(['empresa', 'pagos.vendedor']);
        $nombre = "ventas-externas-{$periodo->anio}-{$periodo->mes}.csv";
        return response()->streamDownload(function () use ($periodo) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['VENDEDOR', 'DNI/RUC', 'PAGO FIJO', 'VENTAS', '% COMISIÓN', 'COMISIÓN', 'BONOS', 'ADELANTOS', 'TOTAL'], ';');
            foreach ($periodo->pagos as $p) fputcsv($out, [$p->vendedor->nombre, $p->vendedor->numero_documento, $p->pago_fijo, $p->ventas, $p->porcentaje_comision, $p->comision, $p->bonos, $p->adelantos, $p->total], ';');
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function periodoData(PeriodoVentaExterna $p): array
    {
        return ['id' => $p->id, 'empresa' => $p->empresa?->nombre_comercial ?: $p->empresa?->razon_social,
            'empresa_id' => $p->empresa_id, 'anio' => $p->anio, 'mes' => $p->mes, 'estado' => $p->estado,
            'pagos' => $p->pagos->map(fn ($x) => ['id' => $x->id, 'vendedor' => $x->vendedor->nombre,
                'documento' => $x->vendedor->numero_documento, 'tipo_pago' => $x->tipo_pago,
                'pago_fijo' => (float) $x->pago_fijo, 'ventas' => (float) $x->ventas,
                'tipo_comision' => $x->tipo_comision, 'porcentaje_comision' => (float) $x->porcentaje_comision,
                'comision' => (float) $x->comision, 'bonos' => (float) $x->bonos,
                'adelantos' => (float) $x->adelantos, 'total' => (float) $x->total, 'observacion' => $x->observacion]),
        ];
    }
}
