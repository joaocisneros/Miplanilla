<?php

namespace App\Http\Controllers;

use App\Exports\PlanillaDetalleExport;
use App\Models\CodigoOt;
use App\Models\Contratista;
use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use App\Models\OtAvance;
use App\Models\ProductoOt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Contratistas: pago por avance de obra (destajo). Separado de Planilla/RxH:
 * sin asistencia, sin AFP, sin beneficios. El supervisor registra el % de
 * avance y el corte calcula pago = precio × % del periodo (+ IGV a facturar).
 */
class ContratistaController extends Controller
{
    private const IGV = 0.18;

    public function index(): Response
    {
        $contratistas = Contratista::with(['ordenes.avances', 'ordenes.empresa:id,razon_social,nombre_comercial'])
            ->orderBy('nombre')
            ->get()
            ->map(function ($c) {
                $ordenes = $c->ordenes->map(function ($ot) {
                    $avanceTotal = round((float) $ot->avances->sum('porcentaje'), 2);
                    $montoAvanzado = round((float) $ot->precio * $avanceTotal / 100, 2);
                    $montoPagado = round($ot->avances->where('pagado', true)
                        ->sum(fn ($a) => (float) $ot->precio * (float) $a->porcentaje / 100), 2);

                    return [
                        'id' => $ot->id,
                        'codigo' => $ot->codigo,
                        'producto' => $ot->producto,
                        'descripcion' => $ot->descripcion,
                        'empresa_id' => $ot->empresa_id,
                        'empresa' => $ot->empresa?->nombre_comercial ?? $ot->empresa?->razon_social,
                        'precio' => (float) $ot->precio,
                        'estado' => $ot->estado,
                        'avance_total' => $avanceTotal,
                        'monto_avanzado' => $montoAvanzado,
                        'monto_pagado' => $montoPagado,
                        'saldo_por_pagar' => round($montoAvanzado - $montoPagado, 2),
                        'avances' => $ot->avances->sortBy('fecha')->values()->map(fn ($a) => [
                            'id' => $a->id,
                            'fecha' => $a->fecha->toDateString(),
                            'porcentaje' => (float) $a->porcentaje,
                            'monto' => round((float) $ot->precio * (float) $a->porcentaje / 100, 2),
                            'pagado' => $a->pagado,
                            'fecha_pago' => $a->fecha_pago?->toDateString(),
                            'nota' => $a->nota,
                        ]),
                    ];
                })->values();

                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'ruc' => $c->ruc,
                    'telefono' => $c->telefono,
                    'cuenta' => $c->cuenta,
                    'activo' => $c->activo,
                    'ordenes' => $ordenes,
                    'saldo_por_pagar' => round($ordenes->sum('saldo_por_pagar'), 2),
                ];
            });

        return Inertia::render('Contratistas/Index', [
            'contratistas' => $contratistas,
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial']),
            'productos' => ProductoOt::orderBy('nombre')->get(['id', 'nombre', 'activo']),
            'codigos' => CodigoOt::orderBy('codigo')->get(['id', 'codigo', 'producto', 'activo']),
            'igv' => self::IGV,
        ]);
    }

    public function storeContratista(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:15'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'cuenta' => ['nullable', 'string', 'max:30'],
        ]);
        Contratista::create($data + ['activo' => true]);

        return back()->with('success', 'Contratista registrado.');
    }

    public function updateContratista(Request $request, Contratista $contratista)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:15'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'cuenta' => ['nullable', 'string', 'max:30'],
            'activo' => ['boolean'],
        ]);
        $contratista->update($data);

        return back()->with('success', 'Contratista actualizado.');
    }

    /** Elimina un contratista SOLO si no tiene OTs (protege el historial de pagos). */
    public function destroyContratista(Contratista $contratista)
    {
        if ($contratista->ordenes()->exists()) {
            return back()->with('error', 'No se puede eliminar: tiene órdenes de trabajo registradas. Márcalo como inactivo.');
        }
        $contratista->delete();

        return back()->with('success', 'Contratista eliminado.');
    }

    public function storeOt(Request $request)
    {
        $data = $request->validate([
            'contratista_id' => ['required', 'exists:contratistas,id'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'codigo' => ['required', 'string', 'max:30',
                Rule::unique('ordenes_trabajo')->where('contratista_id', $request->input('contratista_id'))],
            'producto' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
        ]);
        OrdenTrabajo::create($data);

        return back()->with('success', 'Orden de trabajo creada.');
    }

    public function updateOt(Request $request, OrdenTrabajo $ot)
    {
        $data = $request->validate([
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'codigo' => ['required', 'string', 'max:30',
                Rule::unique('ordenes_trabajo')->where('contratista_id', $ot->contratista_id)->ignore($ot->id)],
            'producto' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:en_curso,terminada,anulada'],
        ]);
        $ot->update($data);

        return back()->with('success', 'Orden de trabajo actualizada.');
    }

    // ---- Catálogo de códigos de OT (unidades del taller) ----

    public function storeCodigo(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'unique:codigos_ot,codigo'],
            'producto' => ['nullable', 'string', 'max:255'],
        ]);
        if (! empty($data['producto'])) {
            $data['producto'] = mb_strtoupper(trim($data['producto']));
            // El catalogo de productos se alimenta solo desde aqui.
            ProductoOt::firstOrCreate(['nombre' => $data['producto']], ['activo' => true]);
        }
        CodigoOt::create($data + ['activo' => true]);

        return back()->with('success', 'Código OT registrado.');
    }

    public function updateCodigo(Request $request, CodigoOt $codigo)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'unique:codigos_ot,codigo,'.$codigo->id],
            'producto' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ]);
        if (! empty($data['producto'])) {
            $data['producto'] = mb_strtoupper(trim($data['producto']));
            ProductoOt::firstOrCreate(['nombre' => $data['producto']], ['activo' => true]);
        }
        $codigo->update($data);

        return back()->with('success', 'Código OT actualizado.');
    }

    public function destroyCodigo(CodigoOt $codigo)
    {
        if (OrdenTrabajo::where('codigo', $codigo->codigo)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay OTs de contratistas usando este código. Márcalo como inactivo.');
        }
        $codigo->delete();

        return back()->with('success', 'Código OT eliminado.');
    }

    // ---- Catálogo de productos (se jalan como caja de opciones en la OT) ----

    public function storeProducto(Request $request)
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:255', 'unique:productos_ot,nombre']]);
        ProductoOt::create(['nombre' => mb_strtoupper(trim($data['nombre'])), 'activo' => true]);

        return back()->with('success', 'Producto registrado.');
    }

    public function updateProducto(Request $request, ProductoOt $producto)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:productos_ot,nombre,'.$producto->id],
            'activo' => ['boolean'],
        ]);
        $producto->update(['nombre' => mb_strtoupper(trim($data['nombre'])), 'activo' => $data['activo'] ?? true]);

        return back()->with('success', 'Producto actualizado.');
    }

    public function destroyProducto(ProductoOt $producto)
    {
        if (OrdenTrabajo::where('producto', $producto->nombre)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay OTs usando este producto. Márcalo como inactivo.');
        }
        $producto->delete();

        return back()->with('success', 'Producto eliminado.');
    }

    /** Elimina una OT solo si no tiene avances pagados (deshacer errores). */
    public function destroyOt(OrdenTrabajo $ot)
    {
        if ($ot->avances()->where('pagado', true)->exists()) {
            return back()->with('error', 'No se puede eliminar: la OT tiene avances ya pagados. Márcala como anulada.');
        }
        $ot->avances()->delete();
        $ot->delete();

        return back()->with('success', 'Orden de trabajo eliminada.');
    }

    /** Registra un avance (%). Valida que el acumulado no pase de 100%. */
    public function storeAvance(Request $request)
    {
        $data = $request->validate([
            'orden_trabajo_id' => ['required', 'exists:ordenes_trabajo,id'],
            'fecha' => ['required', 'date'],
            'porcentaje' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        $ot = OrdenTrabajo::findOrFail($data['orden_trabajo_id']);
        abort_if($ot->estado === 'anulada', 422, 'La OT está anulada.');

        $acumulado = (float) $ot->avances()->sum('porcentaje');
        if ($acumulado + (float) $data['porcentaje'] > 100.005) {
            return back()->withErrors([
                'porcentaje' => 'La OT ya tiene '.round($acumulado, 2).'% avanzado; con este avance pasaría el 100%.',
            ]);
        }

        OtAvance::create($data + ['registrado_por' => $request->user()->id]);

        // Al llegar al 100% se marca terminada automáticamente.
        if ($acumulado + (float) $data['porcentaje'] >= 99.995 && $ot->estado === 'en_curso') {
            $ot->update(['estado' => 'terminada']);
        }

        return back()->with('success', 'Avance registrado.');
    }

    /** Elimina un avance NO pagado (correcciones). */
    public function destroyAvance(OtAvance $avance)
    {
        abort_if($avance->pagado, 422, 'No se puede eliminar un avance ya pagado.');
        $orden = $avance->orden;
        $avance->delete();
        if ($orden->estado === 'terminada' && (float) $orden->avances()->sum('porcentaje') < 99.995) {
            $orden->update(['estado' => 'en_curso']);
        }

        return back()->with('success', 'Avance eliminado.');
    }

    /**
     * Marca como pagados los avances pendientes del rango. Si viene
     * contratista_id se paga solo a ese contratista (pago individual).
     */
    public function pagarCorte(Request $request)
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'contratista_id' => ['nullable', 'exists:contratistas,id'],
        ]);

        $n = OtAvance::where('pagado', false)
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->when($data['contratista_id'] ?? null, fn ($q, $cid) => $q->whereHas('orden', fn ($qq) => $qq->where('contratista_id', $cid)))
            ->update(['pagado' => true, 'fecha_pago' => now()->toDateString()]);

        return back()->with('success', "Pago registrado: {$n} avance(s) marcados como pagados.");
    }

    /**
     * Exporta el estado GENERAL detallado: todas las OTs con su historial de
     * avances (una fila por avance), montos y saldos por contratista.
     */
    public function exportGeneral()
    {
        $headings = ['Contratista', 'OT', 'Producto', 'Trabajo', 'Precio pactado',
            'Fecha avance', '% avance', 'Monto', 'Pagado', 'Fecha pago',
            'Avance total OT', 'Saldo OT'];
        $rows = [];

        $contratistas = Contratista::with(['ordenes.avances'])->orderBy('nombre')->get();
        foreach ($contratistas as $c) {
            $saldoContratista = 0;
            foreach ($c->ordenes as $ot) {
                $avanceTotal = round((float) $ot->avances->sum('porcentaje'), 2);
                $saldoOt = round((float) $ot->precio * $avanceTotal / 100
                    - $ot->avances->where('pagado', true)->sum(fn ($a) => (float) $ot->precio * (float) $a->porcentaje / 100), 2);
                $saldoContratista += $saldoOt;

                if ($ot->avances->isEmpty()) {
                    $rows[] = [$c->nombre, $ot->codigo, $ot->producto, $ot->descripcion,
                        (float) $ot->precio, '(sin avances)', 0, 0, '', '', '0%', 0];

                    continue;
                }
                foreach ($ot->avances->sortBy('fecha') as $a) {
                    $rows[] = [$c->nombre, $ot->codigo, $ot->producto, $ot->descripcion,
                        (float) $ot->precio, $a->fecha->format('d/m/Y'), (float) $a->porcentaje,
                        round((float) $ot->precio * (float) $a->porcentaje / 100, 2),
                        $a->pagado ? 'SI' : 'NO', $a->fecha_pago?->format('d/m/Y') ?? '',
                        $avanceTotal.'%', $saldoOt];
                }
            }
            $rows[] = ['TOTAL '.$c->nombre.' (saldo por pagar)', '', '', '', '', '', '', '', '', '', '',
                round($saldoContratista, 2)];
        }

        return Excel::download(new PlanillaDetalleExport($headings, $rows, [5, 8, 12], 12, 'C2'),
            'contratistas_detallado_'.now()->format('Y-m-d').'.xlsx');
    }

    /** Exporta el corte (rango de fechas) a Excel, agrupado por contratista. */
    public function exportCorte(Request $request)
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'contratista_id' => ['nullable', 'exists:contratistas,id'],
        ]);

        $avances = OtAvance::with('orden.contratista')
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->when($data['contratista_id'] ?? null, fn ($q, $cid) => $q->whereHas('orden', fn ($qq) => $qq->where('contratista_id', $cid)))
            ->get()
            ->sortBy(fn ($a) => $a->orden->contratista->nombre.'|'.$a->orden->codigo);

        $headings = ['Contratista', 'OT', 'Producto', 'Descripción', 'Precio OT',
            '% periodo', 'A pagar', 'A facturar (IGV)', 'Pagado'];
        $rows = [];
        foreach ($avances->groupBy(fn ($a) => $a->orden->contratista->nombre) as $nombre => $grupo) {
            $subtotal = 0;
            foreach ($grupo as $a) {
                $monto = round((float) $a->orden->precio * (float) $a->porcentaje / 100, 2);
                $subtotal += $monto;
                $rows[] = [$nombre, $a->orden->codigo, $a->orden->producto, $a->orden->descripcion,
                    (float) $a->orden->precio, (float) $a->porcentaje, $monto,
                    round($monto * (1 + self::IGV), 2), $a->pagado ? 'SI' : 'NO'];
            }
            $rows[] = ['TOTAL '.$nombre, '', '', '', '', '', round($subtotal, 2),
                round($subtotal * (1 + self::IGV), 2), ''];
        }

        $nombre = 'corte_contratistas_'.$data['desde'].'_'.$data['hasta'].'.xlsx';

        return Excel::download(new PlanillaDetalleExport($headings, $rows, [5, 7, 8], 7, 'C2'), $nombre);
    }
}
