<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #222; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .muted { color: #666; }
    .box { border: 1px solid #999; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 4px 6px; }
    .secc th { background: #1F4E79; color: #fff; text-align: left; font-size: 11px; }
    .row td { border-bottom: 1px solid #eee; }
    .num { text-align: right; }
    .tot td { border-top: 2px solid #333; font-weight: bold; }
    .neto { background: #e8f5e9; font-size: 13px; font-weight: bold; }
    .header-tbl td { border: none; vertical-align: top; }
</style>
</head>
<body>

<table class="header-tbl">
    <tr>
        <td style="width:60%">
            <h1>{{ $empresa->razon_social }}</h1>
            <div class="muted">RUC: {{ $empresa->ruc }}</div>
        </td>
        <td style="width:40%; text-align:right;">
            <strong>BOLETA DE PAGO</strong><br>
            <span class="muted">{{ $periodo->descripcion }}</span>
        </td>
    </tr>
</table>

<div class="box">
    <strong>{{ $emp->nombre_completo }}</strong> &nbsp; | &nbsp; {{ $emp->tipo_documento }}: {{ $emp->numero_documento }}<br>
    <span class="muted">
        Base afecta: S/ {{ number_format($d->base_afecta, 2) }}
    </span>
</div>

@php
    $ing = $desglose['ingresos'] ?? [];
    $des = $desglose['descuentos'] ?? [];
    $apo = $desglose['aportes_empleador'] ?? [];
    $f = fn($v) => 'S/ '.number_format((float)$v, 2);
@endphp

<table class="secc">
    <tr><th colspan="2">INGRESOS</th></tr>
    <tr class="row"><td>Remuneración devengada</td><td class="num">{{ $f($ing['remuneracion_devengada'] ?? 0) }}</td></tr>
    <tr class="row"><td>Horas extra</td><td class="num">{{ $f($ing['horas_extra'] ?? 0) }}</td></tr>
    <tr class="row"><td>Otros (afectos)</td><td class="num">{{ $f($ing['otros_afectos'] ?? 0) }}</td></tr>
    <tr class="row"><td>Movilidad</td><td class="num">{{ $f($ing['movilidad'] ?? 0) }}</td></tr>
    <tr class="row"><td>Subsidio</td><td class="num">{{ $f($ing['subsidio'] ?? 0) }}</td></tr>
    <tr class="tot"><td>Total ingresos</td><td class="num">{{ $f($d->total_ingresos) }}</td></tr>
</table>

<br>

<table class="secc">
    <tr><th colspan="2">DESCUENTOS</th></tr>
    <tr class="row"><td>Descuento por tardanza</td><td class="num">{{ $f($des['tardanza'] ?? 0) }}</td></tr>
    <tr class="row"><td>Pensión ({{ $des['pension']['detalle']['sistema'] ?? '' }})</td><td class="num">{{ $f($des['pension']['total'] ?? 0) }}</td></tr>
    <tr class="row"><td>Renta 5ta categoría</td><td class="num">{{ $f($d->renta_5ta) }}</td></tr>
    <tr class="row"><td>Adelantos</td><td class="num">{{ $f($des['adelantos'] ?? 0) }}</td></tr>
    <tr class="tot"><td>Total descuentos</td><td class="num">{{ $f($d->total_descuentos) }}</td></tr>
</table>

<br>

<table>
    <tr class="neto"><td>NETO A PAGAR</td><td class="num">{{ $f($d->neto) }}</td></tr>
</table>

<br>

<table class="secc">
    <tr><th colspan="2">APORTES DEL EMPLEADOR (no se descuentan al trabajador)</th></tr>
    <tr class="row"><td>EsSalud</td><td class="num">{{ $f($apo['essalud'] ?? 0) }}</td></tr>
    <tr class="row"><td>SCTR Pensión</td><td class="num">{{ $f($apo['sctr_pension'] ?? 0) }}</td></tr>
    <tr class="row"><td>SCTR Salud</td><td class="num">{{ $f($apo['sctr_salud'] ?? 0) }}</td></tr>
    <tr class="row"><td>Seguro Vida Ley</td><td class="num">{{ $f($apo['vida_ley'] ?? 0) }}</td></tr>
    <tr class="row"><td>Senati</td><td class="num">{{ $f($apo['senati'] ?? 0) }}</td></tr>
</table>

<br><br>
<div class="muted" style="text-align:center; font-size:10px;">
    Documento generado por MiPlanilla — {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
