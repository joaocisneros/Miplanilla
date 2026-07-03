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
    table.det { width: 100%; border-collapse: collapse; }
    table.det th { background: #1F4E79; color: #fff; padding: 5px 6px; text-align: left; font-size: 9.5px; }
    table.det td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
    .num { text-align: right; }
    .tot { background: #f0f4f8; font-weight: bold; }
    .firma { margin-top: 60px; text-align: center; font-size: 9.5px; }
    .linea { border-top: 1px solid #000; width: 60%; margin: 0 auto 3px; }
</style>
</head>
<body>
@php
    $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
    $money = fn ($v) => 'S/ '.number_format((float) $v, 2);
    $periodoTxt = ($g->tipo === 'diciembre' ? 'Navidad (Diciembre)' : 'Fiestas Patrias (Julio)').' '.$g->anio;
@endphp

<div class="emp">{{ $empresa->razon_social }}</div>
<div class="muted">RUC {{ $empresa->ruc }}@if($empresa->direccion) · {{ $empresa->direccion }}@endif</div>

<h1>CONSTANCIA DE GRATIFICACIÓN</h1>
<div class="sub">{{ $periodoTxt }}</div>

<table class="datos">
    <tr>
        <td style="width:18%;"><b>Trabajador</b></td><td style="width:52%;">{{ $emp->nombre_completo }}</td>
        <td style="width:12%;"><b>DNI</b></td><td>{{ $emp->numero_documento }}</td>
    </tr>
    <tr>
        <td><b>Periodo</b></td><td>{{ $periodoTxt }}</td>
        <td><b>Tiempo</b></td><td>{{ $g->meses_computables }} meses{{ $g->dias_computables ? ' y '.$g->dias_computables.' días' : '' }}</td>
    </tr>
</table>

<table class="det">
    <thead><tr><th>Concepto</th><th class="num">Monto</th></tr></thead>
    <tbody>
        <tr><td>Remuneración computable</td><td class="num">{{ $money($g->rem_computable) }}</td></tr>
        <tr><td>Gratificación ({{ $g->meses_computables }}/6 + {{ $g->dias_computables }}/180)</td><td class="num">{{ $money($g->monto) }}</td></tr>
        <tr><td>Bonificación extraordinaria 9% (Ley 30334)</td><td class="num">{{ $money($g->bonificacion_extraordinaria) }}</td></tr>
        @if($g->renta_5ta > 0)<tr><td>(-) Retención Renta 5ta</td><td class="num">- {{ $money($g->renta_5ta) }}</td></tr>@endif
        <tr class="tot"><td>TOTAL A PAGAR</td><td class="num">{{ $money($g->neto) }}</td></tr>
    </tbody>
</table>

<p class="muted" style="margin-top:14px;">La gratificación no está afecta a aportes ni descuentos previsionales (AFP/ONP), conforme a ley.</p>

<table style="width:100%; margin-top:50px;"><tr>
    <td class="firma" style="width:50%;"><div class="linea"></div>EL EMPLEADOR<br><span class="muted">{{ $empresa->razon_social }}</span></td>
    <td class="firma" style="width:50%;"><div class="linea"></div>EL TRABAJADOR<br><span class="muted">{{ $emp->nombre_completo }} — DNI {{ $emp->numero_documento }}</span></td>
</tr></table>

<p class="muted" style="margin-top:30px; text-align:center;">Emitido el {{ $f(now()) }}</p>
</body>
</html>
