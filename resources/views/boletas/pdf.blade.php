<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10.5px; color: #1f2937; }

    .header { border-bottom: 2px solid #1F4E79; padding-bottom: 8px; margin-bottom: 12px; }
    .header td { border: none; vertical-align: top; }
    .empresa-nombre { font-size: 16px; font-weight: bold; color: #1F4E79; }
    .doc-titulo { font-size: 13px; font-weight: bold; color: #1F4E79; }
    .muted { color: #6b7280; }

    table { width: 100%; border-collapse: collapse; }

    /* Datos del trabajador */
    .datos { border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 12px; }
    .datos td { padding: 5px 9px; border-bottom: 1px solid #eef0f3; }
    .datos td.label { color: #6b7280; width: 16%; font-size: 9.5px; text-transform: uppercase; }
    .datos td.val { font-weight: bold; width: 34%; }

    /* Secciones */
    .secc { margin-bottom: 4px; }
    .secc-head { background: #1F4E79; color: #fff; font-size: 10px; font-weight: bold;
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
    .neto td { background: #1F4E79; color: #fff; font-size: 14px; font-weight: bold; }
    .costo td { background: #eef4fb; color: #1F4E79; font-weight: bold; }

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
    $apo = $desglose['aportes_empleador'] ?? [];
    $f = fn($v) => 'S/ '.number_format((float)$v, 2);

    $totalAportes = array_sum($apo);
    $costoEmpresa = (float) $d->total_ingresos + (float) $totalAportes;
    $regimen = $contrato?->sistema_pensiones === 'AFP'
        ? 'AFP '.($contrato->afp ?? '').($contrato->tipo_afp ? ' ('.$contrato->tipo_afp.')' : '')
        : ($contrato?->sistema_pensiones ?? '—');
    $cuenta = trim(($emp->banco ? $emp->banco.' ' : '').($emp->cuenta_ahorros ?? $emp->cuenta_corriente ?? ''));
    $diasTrab = $desglose['dias_trabajados'] ?? null;

    // Asistencia (informativo). Las faltas YA están reflejadas en los días trabajados,
    // por eso la remuneración se calcula sobre días trabajados (no se restan aparte).
    $asis = $desglose['asistencia'] ?? [];
    $faltas = $asis['faltas'] ?? 0;
    $remMostrar = (float) ($ing['remuneracion_devengada'] ?? 0);

    // Estructura del neto tal como la pidió el cliente (viene del motor en 'bloques'):
    //   Bloque 1 (Rem. neta quincenal) + Bloque 2 (Total movilidad) − Renta 5ta = SUMA NETO
    //   (+ Reintegro − Adelanto) = A PAGAR
    $bloques = $desglose['bloques'] ?? [];
    $pensionTot = (float) ($des['pension']['total'] ?? 0);
    $renta5 = (float) $d->renta_5ta;
    $remNetaQuinc = (float) ($bloques['remuneracion_neta_quincenal'] ?? ((float) $d->base_afecta - $pensionTot));
    $movilidadTot = (float) ($bloques['total_movilidad_quincenal'] ?? ($ing['movilidad'] ?? 0));
    $sumaNeto = (float) ($bloques['suma_neto'] ?? round($remNetaQuinc + $movilidadTot - $renta5, 2));
    $adelantoMonto = (float) ($des['adelantos'] ?? 0);
    $reintegroMonto = (float) ($desglose['reintegros'] ?? 0);
    $aPagarFinal = round($sumaNeto + $reintegroMonto - $adelantoMonto, 2);

    // Componentes de la bolsa de movilidad (para mostrarlos en campos).
    $movProrr = (float) ($ing['movilidad'] ?? 0);
    $movSab = (float) ($ing['sabado'] ?? 0);
    $movDom = (float) ($ing['domingo_feriado'] ?? 0);
    $movHE = (float) ($ing['horas_extra'] ?? 0);
    $movInc = (float) ($ing['incentivos'] ?? 0);
@endphp

<table class="header">
    <tr>
        <td style="width:62%">
            <div class="empresa-nombre">{{ $empresa->razon_social }}</div>
            <div class="muted">RUC: {{ $empresa->ruc }}</div>
            @if($emp->sede)<div class="muted">Sede: {{ $emp->sede->nombre }}</div>@endif
        </td>
        <td style="width:38%; text-align:right;">
            <div class="doc-titulo">BOLETA DE PAGO</div>
            <div class="muted">{{ $periodo->descripcion }}</div>
            <div class="muted">Del {{ \Carbon\Carbon::parse($periodo->fecha_inicio)->format('d/m/Y') }}
                al {{ \Carbon\Carbon::parse($periodo->fecha_fin)->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<table class="datos">
    <tr>
        <td class="label">Trabajador</td><td class="val">{{ $emp->nombre_completo }}</td>
        <td class="label">{{ $emp->tipo_documento }}</td><td class="val">{{ $emp->numero_documento }}</td>
    </tr>
    <tr>
        <td class="label">Cargo</td><td class="val">{{ $contrato?->cargo?->nombre ?? '—' }}</td>
        <td class="label">Área</td><td class="val">{{ $contrato?->area?->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Fecha ingreso</td>
        <td class="val">{{ $contrato?->fecha_ingreso ? \Carbon\Carbon::parse($contrato->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
        <td class="label">Régimen pensión</td><td class="val">{{ $regimen }}</td>
    </tr>
    <tr>
        <td class="label">Días trabajados</td>
        <td class="val">{{ $diasTrab !== null ? $diasTrab.' / '.($desglose['dias_base'] ?? 30) : '—' }}</td>
        {{-- Datos bancarios omitidos por privacidad: no se muestran en la boleta. --}}
        <td class="label"></td><td class="val"></td>
    </tr>
</table>

<table class="cols">
    <tr>
        <!-- INGRESOS -->
        <td style="width:49%; padding-right:8px;">
            <div class="secc">
                <div class="secc-head">Remuneración (afecta)</div>
                <table>
                    <tr class="row"><td>Remuneración{{ $diasTrab !== null ? ' ('.$diasTrab.' días)' : ' devengada' }}</td><td class="num">{{ $f($remMostrar) }}</td></tr>
                    @if(($ing['gratificacion'] ?? 0) > 0)<tr class="row"><td>Gratificación</td><td class="num">{{ $f($ing['gratificacion']) }}</td></tr>@endif
                    @if(($ing['vacaciones'] ?? 0) > 0)<tr class="row"><td>Vacaciones</td><td class="num">{{ $f($ing['vacaciones']) }}</td></tr>@endif
                    @if(($ing['subsidio'] ?? 0) > 0)<tr class="row"><td>Subsidio</td><td class="num">{{ $f($ing['subsidio']) }}</td></tr>@endif
                </table>
            </div>
            <div class="secc" style="margin-top:6px;">
                <div class="secc-head">Movilidad y adicionales</div>
                <table>
                    <tr class="row"><td>Movilidad</td><td class="num">{{ $f($movProrr) }}</td></tr>
                    <tr class="row"><td>Sábados</td><td class="num">{{ $f($movSab) }}</td></tr>
                    <tr class="row"><td>Domingos / feriados</td><td class="num">{{ $f($movDom) }}</td></tr>
                    <tr class="row"><td>Horas extra</td><td class="num">{{ $f($movHE) }}</td></tr>
                    <tr class="row"><td>Incentivos / bonos</td><td class="num">{{ $f($movInc) }}</td></tr>
                    <tr class="tot"><td>Total movilidad quincenal</td><td class="num">{{ $f($movilidadTot) }}</td></tr>
                </table>
            </div>
        </td>
        <!-- DESCUENTOS -->
        <td style="width:51%; padding-left:8px;">
            <div class="secc">
                <div class="secc-head">Descuentos</div>
                <table>
                    <tr class="row"><td>Descuento por tardanza</td><td class="num">{{ $f($des['tardanza'] ?? 0) }}</td></tr>
                    <tr class="row"><td>Pensión ({{ $des['pension']['detalle']['sistema'] ?? ($contrato?->sistema_pensiones ?? '') }})</td><td class="num">{{ $f($des['pension']['total'] ?? 0) }}</td></tr>
                    <tr class="row"><td>Renta 5ta categoría</td><td class="num">{{ $f($d->renta_5ta) }}</td></tr>
                    <tr class="row"><td>Adelantos</td><td class="num">{{ $f($des['adelantos'] ?? 0) }}</td></tr>
                    <tr class="tot"><td>Total descuentos</td><td class="num">{{ $f(($des['tardanza'] ?? 0) + ($des['pension']['total'] ?? 0) + (float)$d->renta_5ta + ($des['adelantos'] ?? 0)) }}</td></tr>
                </table>
                @if($faltas > 0)
                <p style="margin-top:4px; font-size:9px; color:#6b7280;">Faltas: {{ $faltas }} {{ $faltas == 1 ? 'día' : 'días' }} (ya reflejadas en los días pagados).</p>
                @endif
            </div>
        </td>
    </tr>
</table>

<!-- RESUMEN / NETO (estructura solicitada por el cliente) -->
<table class="resumen">
    <tr class="sep"><td>Remuneración neta quincenal</td><td class="num">{{ $f($remNetaQuinc) }}</td></tr>
    <tr class="sep"><td>(+) Total movilidad quincenal</td><td class="num">{{ $f($movilidadTot) }}</td></tr>
    <tr class="sep"><td>(–) Retención 5ta categoría</td><td class="num">{{ $f($renta5) }}</td></tr>
    <tr class="costo"><td>SUMA NETO A PAGAR</td><td class="num">{{ $f($sumaNeto) }}</td></tr>
    @if($reintegroMonto > 0)<tr class="sep"><td>(+) Reintegro</td><td class="num">{{ $f($reintegroMonto) }}</td></tr>@endif
    @if($adelantoMonto > 0)<tr class="sep"><td>(–) Adelanto / préstamo</td><td class="num">{{ $f($adelantoMonto) }}</td></tr>@endif
    <tr class="neto"><td>NETO A PAGAR</td><td class="num">{{ $f($aPagarFinal) }}</td></tr>
</table>

<!-- APORTES EMPLEADOR -->
<div class="secc" style="margin-top:12px;">
    <div class="secc-head">Aportes del empleador (no se descuentan al trabajador)</div>
    <table>
        <tr class="row"><td>EsSalud</td><td class="num">{{ $f($apo['essalud'] ?? 0) }}</td></tr>
        <tr class="row"><td>SCTR Pensión</td><td class="num">{{ $f($apo['sctr_pension'] ?? 0) }}</td></tr>
        <tr class="row"><td>SCTR Salud</td><td class="num">{{ $f($apo['sctr_salud'] ?? 0) }}</td></tr>
        <tr class="row"><td>Seguro Vida Ley</td><td class="num">{{ $f($apo['vida_ley'] ?? 0) }}</td></tr>
        <tr class="row"><td>Senati</td><td class="num">{{ $f($apo['senati'] ?? 0) }}</td></tr>
        <tr class="tot"><td>Total aportes empleador</td><td class="num">{{ $f($totalAportes) }}</td></tr>
    </table>
</div>

<!-- FIRMAS -->
<table class="firma">
    <tr>
        <td style="width:50%"><div class="linea">Empleador</div></td>
        <td style="width:50%"><div class="linea">{{ $emp->nombre_completo }}<br><span class="muted">Trabajador</span></div></td>
    </tr>
</table>

<div class="footer">
    Documento generado por MiPlanilla — {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
