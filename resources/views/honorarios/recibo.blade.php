<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10.5px; color: #1f2937; }

    .header { border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 12px; }
    .header td { border: none; vertical-align: top; }
    .empresa-nombre { font-size: 16px; font-weight: bold; color: #047857; }
    .doc-titulo { font-size: 13px; font-weight: bold; color: #047857; }
    .muted { color: #6b7280; }

    table { width: 100%; border-collapse: collapse; }

    /* Datos del trabajador */
    .datos { border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 12px; }
    .datos td { padding: 5px 9px; border-bottom: 1px solid #eef0f3; }
    .datos td.label { color: #6b7280; width: 16%; font-size: 9.5px; text-transform: uppercase; }
    .datos td.val { font-weight: bold; width: 34%; }

    /* Secciones */
    .secc { margin-bottom: 4px; }
    .secc-head { background: #047857; color: #fff; font-size: 10px; font-weight: bold;
                 text-transform: uppercase; letter-spacing: .5px; padding: 5px 9px; }
    .secc table td { padding: 4px 9px; }
    .secc .row td { border-bottom: 1px solid #f0f1f4; }
    .num { text-align: right; }
    .tot td { border-top: 1.5px solid #374151; font-weight: bold; background: #f9fafb; }

    .cols td { border: none; vertical-align: top; padding: 0; }

    /* Resumen / neto */
    .resumen { margin-top: 12px; border: 1px solid #d1d5db; border-radius: 4px; }
    .resumen td { padding: 6px 12px; }
    .resumen .sep td { border-bottom: 1px solid #eef0f3; }
    .neto td { background: #047857; color: #fff; font-size: 14px; font-weight: bold; }

    .nota { margin-top: 10px; font-size: 9px; color: #6b7280; }

    .firma { margin-top: 48px; }
    .firma td { border: none; text-align: center; padding-top: 4px; }
    .firma .linea { border-top: 1px solid #9ca3af; width: 60%; margin: 0 auto; padding-top: 4px; }

    .footer { margin-top: 18px; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

@php
    $ing = $desglose['ingresos'] ?? [];
    $des = $desglose['descuentos'] ?? [];
    $f = fn($v) => 'S/ '.number_format((float)$v, 2);

    $diasTrab = $desglose['dias_trabajados'] ?? ($desglose['asistencia']['dias_trabajados'] ?? null);
    $diasBase = $desglose['dias_base'] ?? 30;

    $asis = $desglose['asistencia'] ?? [];
    $faltas = $asis['faltas'] ?? 0;
    $tardanzaMin = $asis['minutos_tarde'] ?? 0;

    $honorario = (float) ($ing['remuneracion_devengada'] ?? 0);
    $sabado = (float) ($ing['sabado'] ?? 0);
    $domingo = (float) ($ing['domingo_feriado'] ?? 0);
    $incentivo = (float) ($ing['incentivos'] ?? 0);
    $horasExtra = (float) ($ing['horas_extra'] ?? 0);
    $descTardanza = (float) ($des['tardanza'] ?? 0);
    $adelanto = (float) ($des['adelantos'] ?? 0);
    $reintegro = (float) ($desglose['reintegros'] ?? 0);
@endphp

<table class="header">
    <tr>
        <td style="width:62%">
            <div class="empresa-nombre">{{ $empresa->razon_social }}</div>
            <div class="muted">RUC: {{ $empresa->ruc }}</div>
        </td>
        <td style="width:38%; text-align:right;">
            <div class="doc-titulo">RECIBO POR HONORARIOS</div>
            <div class="muted">{{ $periodo->descripcion }}</div>
            <div class="muted">Del {{ \Carbon\Carbon::parse($periodo->fecha_inicio)->format('d/m/Y') }}
                al {{ \Carbon\Carbon::parse($periodo->fecha_fin)->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<table class="datos">
    <tr>
        <td class="label">Prestador de servicio</td><td class="val">{{ $emp->nombre_completo }}</td>
        <td class="label">{{ $emp->tipo_documento }}</td><td class="val">{{ $emp->numero_documento }}</td>
    </tr>
    <tr>
        <td class="label">Cargo</td><td class="val">{{ $contrato?->cargo?->nombre ?? '—' }}</td>
        <td class="label">Área</td><td class="val">{{ $contrato?->area?->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Fecha inicio</td>
        <td class="val">{{ $contrato?->fecha_ingreso ? \Carbon\Carbon::parse($contrato->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
        <td class="label">Días trabajados</td>
        <td class="val">{{ $diasTrab !== null ? $diasTrab.' / '.$diasBase : '—' }}</td>
    </tr>
</table>

<table class="cols">
    <tr>
        <!-- INGRESOS -->
        <td style="width:49%; padding-right:8px;">
            <div class="secc">
                <div class="secc-head">Honorario</div>
                <table>
                    <tr class="row"><td>Honorario{{ $diasTrab !== null ? ' ('.$diasTrab.' días)' : '' }}</td><td class="num">{{ $f($honorario) }}</td></tr>
                    @if($sabado > 0)<tr class="row"><td>Sábados</td><td class="num">{{ $f($sabado) }}</td></tr>@endif
                    @if($domingo > 0)<tr class="row"><td>Domingos / feriados</td><td class="num">{{ $f($domingo) }}</td></tr>@endif
                    @if($horasExtra > 0)<tr class="row"><td>Horas extra</td><td class="num">{{ $f($horasExtra) }}</td></tr>@endif
                    @if($incentivo > 0)<tr class="row"><td>Incentivos / bonos</td><td class="num">{{ $f($incentivo) }}</td></tr>@endif
                </table>
            </div>
        </td>
        <!-- DESCUENTOS -->
        <td style="width:51%; padding-left:8px;">
            <div class="secc">
                <div class="secc-head">Descuentos</div>
                <table>
                    <tr class="row"><td>Descuento por tardanza</td><td class="num">{{ $f($descTardanza) }}</td></tr>
                    @if($adelanto > 0)<tr class="row"><td>Adelantos</td><td class="num">{{ $f($adelanto) }}</td></tr>@endif
                </table>
                @if($faltas > 0)
                <p style="margin-top:4px; font-size:9px; color:#6b7280;">Faltas: {{ $faltas }} {{ $faltas == 1 ? 'día' : 'días' }} (ya reflejadas en los días pagados).</p>
                @endif
                @if($tardanzaMin > 0)
                <p style="margin-top:2px; font-size:9px; color:#6b7280;">Tardanza acumulada: {{ $tardanzaMin }} min.</p>
                @endif
            </div>
        </td>
    </tr>
</table>

<!-- RESUMEN / NETO -->
<table class="resumen">
    @if($reintegro > 0)<tr class="sep"><td>(+) Reintegro</td><td class="num">{{ $f($reintegro) }}</td></tr>@endif
    @if($adelanto > 0)<tr class="sep"><td>(–) Adelanto</td><td class="num">{{ $f($adelanto) }}</td></tr>@endif
    <tr class="neto"><td>NETO A PAGAR</td><td class="num">{{ $f($d->neto) }}</td></tr>
</table>

<p class="nota">Recibo por Honorarios (RxH): sueldo neto, sin descuento de AFP/ONP, EsSalud, gratificación, vacaciones ni CTS.</p>

<!-- FIRMAS -->
<table class="firma">
    <tr>
        <td style="width:50%"><div class="linea">Empresa</div></td>
        <td style="width:50%"><div class="linea">{{ $emp->nombre_completo }}<br><span class="muted">Prestador de servicio</span></div></td>
    </tr>
</table>

<div class="footer">
    Documento generado por MiPlanilla — {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
