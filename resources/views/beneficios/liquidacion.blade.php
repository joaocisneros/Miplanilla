<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 40px 45px; }
    body { font-size: 10.5px; color: #111; }
    .emp { font-size: 12px; font-weight: bold; color: #1F4E79; }
    .muted { color: #555; font-size: 9px; }
    h1 { text-align: center; font-size: 13px; margin: 18px 0 2px; }
    .sub { text-align: center; color: #555; font-size: 9.5px; margin-bottom: 16px; }
    table.datos { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.datos td { padding: 4px 6px; border: 1px solid #ccc; }
    table.det { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.det th { background: #C2410C; color: #fff; padding: 5px 6px; text-align: left; font-size: 9.5px; }
    table.det td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
    .num { text-align: right; }
    .grp { background: #f8f4f0; font-weight: bold; }
    .tot td { background: #1F4E79; color: #fff; font-weight: bold; font-size: 11px; padding: 7px 6px; }
    .firma { margin-top: 60px; text-align: center; font-size: 9.5px; }
    .linea { border-top: 1px solid #000; width: 60%; margin: 0 auto 3px; }
</style>
</head>
<body>
@php
    $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
    $money = fn ($v) => 'S/ '.number_format((float) $v, 2);
    $r = $resultado;
@endphp

<div class="emp">{{ $empresa->razon_social }}</div>
<div class="muted">RUC {{ $empresa->ruc }}@if($empresa->direccion) · {{ $empresa->direccion }}@endif</div>

<h1>LIQUIDACIÓN DE BENEFICIOS SOCIALES</h1>
<div class="sub">Por cese del trabajador</div>

<table class="datos">
    <tr>
        <td style="width:18%;"><b>Trabajador</b></td><td style="width:52%;">{{ $emp->nombre_completo }}</td>
        <td style="width:12%;"><b>DNI</b></td><td>{{ $emp->numero_documento }}</td>
    </tr>
    <tr>
        <td><b>Ingreso</b></td><td>{{ $f($r['fecha_ingreso']) }}</td>
        <td><b>Cese</b></td><td>{{ $f($r['fecha_cese']) }}</td>
    </tr>
    <tr>
        <td><b>Remuneración</b></td><td>{{ $money($r['rem_mensual']) }}</td>
        <td></td><td></td>
    </tr>
</table>

<table class="det">
    <thead><tr><th>Concepto</th><th class="num">Monto</th></tr></thead>
    <tbody>
        <tr class="grp"><td colspan="2">Gratificación trunca ({{ $r['gratificacion']['meses'] }} meses, {{ $r['gratificacion']['dias'] }} días)</td></tr>
        <tr><td>Gratificación</td><td class="num">{{ $money($r['gratificacion']['monto']) }}</td></tr>
        <tr><td>Bonificación extraordinaria 9%</td><td class="num">{{ $money($r['gratificacion']['bonificacion']) }}</td></tr>

        <tr class="grp"><td colspan="2">CTS trunca ({{ $r['cts']['meses'] }} meses, {{ $r['cts']['dias'] }} días)</td></tr>
        <tr><td>Remuneración computable ({{ $money($r['cts']['rem_computable']) }})</td><td class="num">{{ $money($r['cts']['monto']) }}</td></tr>

        <tr class="grp"><td colspan="2">Vacaciones no gozadas</td></tr>
        <tr><td>{{ $r['vacaciones']['dias_pendientes'] }} días pendientes ({{ $r['vacaciones']['dias_ganados'] }} ganados − {{ $r['vacaciones']['dias_gozados'] }} gozados)</td><td class="num">{{ $money($r['vacaciones']['monto']) }}</td></tr>

        <tr class="tot"><td>TOTAL LIQUIDACIÓN A PAGAR</td><td class="num">{{ $money($r['total']) }}</td></tr>
    </tbody>
</table>

<table style="width:100%; margin-top:50px;"><tr>
    <td class="firma" style="width:50%;"><div class="linea"></div>EL EMPLEADOR<br><span class="muted">{{ $empresa->razon_social }}</span></td>
    <td class="firma" style="width:50%;"><div class="linea"></div>EL TRABAJADOR<br><span class="muted">{{ $emp->nombre_completo }} — DNI {{ $emp->numero_documento }}</span></td>
</tr></table>

<p class="muted" style="margin-top:30px; text-align:center;">Emitido el {{ $f(now()) }}</p>
</body>
</html>
