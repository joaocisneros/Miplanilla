<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 70px 60px 60px; }
    body { font-size: 10.5px; color: #111; line-height: 1.5; text-align: justify; }
    h1 { text-align: center; font-size: 13px; margin: 0 0 2px; }
    .sub { text-align: center; font-size: 9.5px; color: #555; margin-bottom: 16px; }
    .clausula { margin-top: 10px; }
    .clausula b.tit { display: block; margin-bottom: 1px; }
    .firmas { margin-top: 55px; width: 100%; }
    .firmas td { text-align: center; font-size: 9.5px; padding-top: 4px; }
    .linea { border-top: 1px solid #000; width: 80%; margin: 0 auto 3px; }
    .muted { color: #555; }
</style>
</head>
<body>
@php
    $c = $contrato;
    $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '____________';
    $tipo = \App\Support\TiposContrato::get($c?->tipo_contrato);
    $plazoFijo = $tipo['plazo_fijo'];
    $modalidad = $tipo['titulo'];
    $domicilioTrab = trim(($emp->direccion ?? '').' '.($emp->distrito ?? '').' '.($emp->provincia ?? '').' '.($emp->departamento ?? ''));
    $sueldo = number_format((float) ($c?->sueldo_basico ?? 0), 2);
    $sis = $c?->sistema_pensiones === 'AFP' ? ('AFP '.strtoupper((string) $c?->afp)) : 'ONP (Sistema Nacional de Pensiones)';
    $jornada = $c?->turno ? ($c->turno->nombre.' ('.substr((string)$c->turno->hora_entrada,0,5).' a '.substr((string)$c->turno->hora_salida,0,5).')') : '48 horas semanales';
    $seguros = ['EsSalud'];
    if ($c?->aporta_sctr) { $seguros[] = 'SCTR'; }
    if ($c?->tiene_vida_ley) { $seguros[] = 'Seguro de Vida Ley'; }
    $segurosTxt = implode(', ', $seguros);

    // Datos de empresa para causa objetiva, giro y régimen laboral.
    $giro = $empresa->giro ?: 'su actividad económica';
    $regimen = $empresa->regimen_laboral ?? 'general';
    $esMype = in_array($regimen, ['microempresa', 'pequena'], true);
    $regimenLabel = $regimen === 'microempresa' ? 'Microempresa' : ($regimen === 'pequena' ? 'Pequeña Empresa' : 'General');
    $remypeTxt = $empresa->remype_numero
        ? ('inscrita en el Registro Nacional de la Micro y Pequeña Empresa (REMYPE) con N° de acogimiento '.$empresa->remype_numero.($empresa->remype_fecha ? ', desde el '.\Carbon\Carbon::parse($empresa->remype_fecha)->format('d/m/Y') : ''))
        : null;
    $esRenovacion = ! empty($c?->es_renovacion);
    $tituloDoc = ($esRenovacion ? 'RENOVACIÓN DE CONTRATO DE TRABAJO ' : 'CONTRATO DE TRABAJO ').$modalidad;
    $lugarFirma = $empresa->direccion ? \Illuminate\Support\Str::after($empresa->direccion, ', ') : '____________';

    // Numeración automática de cláusulas (algunas son condicionales).
    $ordinales = ['PRIMERA','SEGUNDA','TERCERA','CUARTA','QUINTA','SEXTA','SÉPTIMA','OCTAVA','NOVENA','DÉCIMA','DÉCIMO PRIMERA','DÉCIMO SEGUNDA','DÉCIMO TERCERA','DÉCIMO CUARTA'];
    $nCl = 0;
    $cl = function () use (&$nCl, $ordinales) { return $ordinales[$nCl++] ?? ('CLÁUSULA '.($nCl + 1)); };
@endphp

<h1>{{ $tituloDoc }}</h1>
<div class="sub">{{ $empresa->razon_social }} — RUC {{ $empresa->ruc }}</div>

<p>Conste por el presente documento, el contrato de trabajo que celebran de una parte
<b>{{ $empresa->razon_social }}</b>, con RUC N° <b>{{ $empresa->ruc }}</b>, con domicilio en
{{ $empresa->direccion ?? ($sede->direccion ?? '____________') }},@if($remypeTxt) {{ $remypeTxt }},@endif debidamente representada por
@if($empresa->representante_legal)<b>{{ $empresa->representante_legal }}</b>, identificado(a) con DNI N° <b>{{ $empresa->representante_dni ?? '____________' }}</b>{{ $empresa->representante_cargo ? ', en su calidad de '.$empresa->representante_cargo : '' }},@else su representante legal,@endif
a quien en adelante se le denominará <b>EL EMPLEADOR</b>; y de la otra parte
<b>{{ $emp->nombre_completo }}</b>, identificado(a) con {{ $emp->tipo_documento ?? 'DNI' }} N°
<b>{{ $emp->numero_documento }}</b>, con domicilio en {{ $domicilioTrab ?: '____________' }}, a quien en
adelante se le denominará <b>EL TRABAJADOR</b>; en los términos y condiciones siguientes:</p>

<div class="clausula"><b class="tit">{{ $cl() }}: GIRO Y OBJETO.</b>
EL EMPLEADOR se dedica a <b>{{ $giro }}</b>. En tal contexto, contrata a EL TRABAJADOR para que preste sus servicios
desempeñando el cargo de <b>{{ $c?->cargo?->nombre ?? '____________' }}</b>{{ $c?->area?->nombre ? ', en el área de '.$c->area->nombre : '' }},
desarrollando las funciones propias del cargo y aquellas que le sean asignadas conforme a las necesidades del servicio.</div>

@if ($plazoFijo)
<div class="clausula"><b class="tit">{{ $cl() }}: CAUSA OBJETIVA.</b>
EL EMPLEADOR requiere cubrir necesidades vinculadas a {{ $giro }}, {{ $tipo['causa'] }}. Por dicha causa objetiva,
de naturaleza temporal, se celebra el presente contrato sujeto a modalidad al amparo del Texto Único Ordenado del
Decreto Legislativo N° 728 (D.S. N° 003-97-TR).</div>
@endif

<div class="clausula"><b class="tit">{{ $cl() }}: LUGAR DE TRABAJO.</b>
EL TRABAJADOR prestará sus servicios en {{ $sede?->nombre ? ('la sede '.$sede->nombre) : 'las instalaciones de EL EMPLEADOR' }}{{ $sede?->direccion ? (', sito en '.$sede->direccion) : '' }}, sin perjuicio de que pueda ser destacado a otros centros por razones operativas.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: PLAZO.</b>
@if ($plazoFijo)
El presente contrato se celebra bajo la modalidad <b>{{ $tipo['label'] }}</b>,
iniciando el <b>{{ $f($c?->fecha_ingreso) }}</b> y venciendo el <b>{{ $f($c?->fecha_cese) }}</b>, fecha en
la cual quedará extinguido sin necesidad de aviso previo, salvo renovación expresa por escrito
@if($tipo['max']) (plazo máximo legal: {{ $tipo['max'] }})@endif.
@else
El presente contrato es <b>{{ $tipo['label'] }}</b>, iniciando la relación laboral el
<b>{{ $f($c?->fecha_ingreso) }}</b>.
@endif
@if($tipo['nota']) {{ $tipo['nota'] }} @endif
Las partes se sujetan al periodo de prueba de ley (3 meses), salvo pacto distinto permitido por la normativa.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: JORNADA Y HORARIO.</b>
La jornada de trabajo se sujeta a lo dispuesto por la ley: {{ $jornada }}, con el refrigerio de ley,
pudiendo ser modificada por EL EMPLEADOR dentro de los límites legales.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: REMUNERACIÓN.</b>
EL TRABAJADOR percibirá una remuneración mensual de <b>S/ {{ $sueldo }}</b>,
{{ $c?->percibe_asignacion_familiar ? 'incluyendo el derecho a asignación familiar y ' : '' }}sujeta a los
descuentos y aportes de ley, pagadera según la oportunidad establecida por EL EMPLEADOR.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: BENEFICIOS SOCIALES.</b>
EL TRABAJADOR gozará de los beneficios sociales que le correspondan de acuerdo a ley según el régimen laboral aplicable,
así como de los seguros y aportes que correspondan ({{ $segurosTxt }}).</div>

@if ($esMype)
<div class="clausula"><b class="tit">{{ $cl() }}: RÉGIMEN LABORAL ESPECIAL.</b>
EL TRABAJADOR declara conocer que EL EMPLEADOR se encuentra acogido al <b>Régimen Laboral Especial de la {{ $regimenLabel }}</b>
(Ley MYPE — D.S. N° 007-2008-TR y su Reglamento D.S. N° 008-2008-TR){{ $empresa->remype_numero ? ', '.$remypeTxt : '' }};
en consecuencia, los beneficios sociales se rigen por dicho régimen.</div>
@endif

<div class="clausula"><b class="tit">{{ $cl() }}: SISTEMA PENSIONARIO.</b>
EL TRABAJADOR se encuentra afiliado a: <b>{{ $sis }}</b>, autorizando los descuentos correspondientes.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: SEGURIDAD Y SALUD EN EL TRABAJO.</b>
EL TRABAJADOR se obliga a cumplir las normas, reglamentos e instrucciones de seguridad y salud en el trabajo
(Ley N° 29783 y su Reglamento), a usar adecuada y permanentemente los equipos de protección personal que se le
entreguen, a no operar equipos o maquinaria para los que no haya sido capacitado, y a reportar de inmediato
cualquier incidente, accidente de trabajo o enfermedad ocupacional.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: OBLIGACIONES Y CONFIDENCIALIDAD.</b>
EL TRABAJADOR se obliga a cumplir el Reglamento Interno de Trabajo, a desempeñar sus funciones con diligencia y
lealtad, y a mantener reserva sobre la información, técnicas y procesos de EL EMPLEADOR; obligación que subsiste
aún después de concluida la relación laboral.</div>

<div class="clausula"><b class="tit">{{ $cl() }}: REGISTRO Y NORMATIVA APLICABLE.</b>
EL EMPLEADOR registrará a EL TRABAJADOR en el T-Registro y, de corresponder, comunicará el presente contrato a la
Autoridad Administrativa de Trabajo. En todo lo no previsto, las partes se someten al Texto Único Ordenado del
D.Leg. N° 728, su reglamento y demás normas laborales vigentes.</div>

<p style="margin-top:14px;">En señal de conformidad, las partes firman el presente contrato por duplicado, en
{{ $lugarFirma }}, a los _____ días del mes de ________________ de 20____.</p>

<table class="firmas">
    <tr>
        <td><div class="linea"></div>EL EMPLEADOR<br><span class="muted">{{ $empresa->razon_social }}@if($empresa->representante_legal)<br>{{ $empresa->representante_legal }}{{ $empresa->representante_dni ? ' — DNI '.$empresa->representante_dni : '' }}@endif</span></td>
        <td><div class="linea"></div>EL TRABAJADOR<br><span class="muted">{{ $emp->nombre_completo }}<br>{{ $emp->tipo_documento ?? 'DNI' }} {{ $emp->numero_documento }}</span></td>
    </tr>
</table>
</body>
</html>
