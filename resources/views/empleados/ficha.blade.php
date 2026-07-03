<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 18px 22px; }
    body { font-size: 8.5px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    td { border: 1px solid #555; padding: 3px 4px; vertical-align: top; }
    .nb td, td.nb { border: none; }
    .lbl { font-size: 6.8px; color: #444; text-transform: uppercase; display: block; line-height: 1.1; }
    .val { font-weight: bold; font-size: 9px; }
    .sec { background: #1F4E79; color: #fff; font-weight: bold; font-size: 8.5px; padding: 3px 5px; }
    .titulo { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: .5px; }
    .emp { text-align: center; font-size: 11px; font-weight: bold; color: #1F4E79; }
    .muted { color: #555; }
    .foto { width: 80px; height: 90px; border: 1px solid #555; text-align: center; font-size: 6.5px; color: #777; }
    .chk { font-family: DejaVu Sans; font-size: 9px; }
    .sub { margin-top: 6px; }
    .firma td { border: none; text-align: center; padding-top: 4px; font-size: 8px; }
    .linea { border-top: 1px solid #555; width: 80%; margin: 22px auto 2px; }
</style>
</head>
<body>
@php
    $c = $contrato;
    $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
    $edad = $emp->fecha_nacimiento ? \Carbon\Carbon::parse($emp->fecha_nacimiento)->age : '';
    $chk = fn ($cond) => $cond ? '[ X ]' : '[&nbsp;&nbsp;&nbsp;]';
    $sis = $c?->sistema_pensiones; $afp = strtoupper((string) $c?->afp);
    $conyuge = $derechohabientes->whereIn('tipo', ['conyuge', 'concubino'])->first();
    $hijos = $derechohabientes->where('tipo', 'hijo');
@endphp

<!-- Encabezado -->
<table class="nb">
    <tr>
        <td class="nb" style="width:18%; font-weight:bold; font-size:9px;">{{ $empresa->nombre_comercial ?? $empresa->razon_social }}</td>
        <td class="nb" style="width:64%; text-align:center;">
            <div class="emp">{{ $empresa->razon_social }}</div>
            <div class="titulo">FICHA DE REGISTRO DE PERSONAL</div>
            <div class="muted" style="font-size:7px;">(LLENAR A MANO CON LETRA IMPRENTA)</div>
            <div style="margin-top:3px;">Fecha de ingreso: <b>{{ $f($c?->fecha_ingreso) }}</b></div>
        </td>
        <td class="nb" style="width:18%; text-align:right;"><div class="foto">FOTOGRAFÍA<br>ACTUAL</div></td>
    </tr>
</table>

<!-- 1) DATOS PERSONALES -->
<div class="sec sub">1) DATOS PERSONALES DEL CONTRATADO</div>
<table>
    <tr>
        <td style="width:33%"><span class="lbl">Apellido paterno</span><span class="val">{{ $emp->apellido_paterno }}</span></td>
        <td style="width:33%"><span class="lbl">Apellido materno</span><span class="val">{{ $emp->apellido_materno }}</span></td>
        <td style="width:34%"><span class="lbl">Nombres</span><span class="val">{{ $emp->nombres }}</span></td>
    </tr>
    <tr>
        <td><span class="lbl">Sexo</span><span class="val">{{ $emp->genero }}</span></td>
        <td><span class="lbl">Edad</span><span class="val">{{ $edad }}</span></td>
        <td><span class="lbl">Estado civil</span><span class="val">{{ $emp->estado_civil }}</span></td>
    </tr>
    <tr>
        <td><span class="lbl">Lugar de nacimiento</span><span class="val">{{ $emp->lugar_nacimiento }}</span></td>
        <td><span class="lbl">Fecha de nacimiento</span><span class="val">{{ $f($emp->fecha_nacimiento) }}</span></td>
        <td><span class="lbl">Profesión u ocupación</span><span class="val">{{ $emp->profesion }}</span></td>
    </tr>
    <tr>
        <td><span class="lbl">DNI / CE / CI</span><span class="val">{{ $emp->numero_documento }}</span></td>
        <td><span class="lbl">RUC</span><span class="val">{{ $emp->ruc }}</span></td>
        <td><span class="lbl">Teléfono / Celular</span><span class="val">{{ $emp->telefono }}</span></td>
    </tr>
</table>

<!-- 2) DOMICILIO -->
<div class="sec sub">2) DOMICILIO</div>
<table>
    <tr>
        <td colspan="2" style="width:50%"><span class="lbl">Nombre de la vía (Av./Jr./Calle/Psje./Carretera)</span><span class="val">{{ $emp->direccion }}</span></td>
        <td style="width:25%"><span class="lbl">Correo electrónico</span><span class="val">{{ $emp->correo }}</span></td>
        <td style="width:25%"><span class="lbl">Tipo de vivienda</span>
            <span class="chk">{!! $chk($emp->tipo_vivienda === 'propia') !!} Propia &nbsp; {!! $chk($emp->tipo_vivienda === 'alquilada') !!} Alquilada &nbsp; {!! $chk($emp->tipo_vivienda && ! in_array($emp->tipo_vivienda, ['propia','alquilada'])) !!} Otros</span>
        </td>
    </tr>
    <tr>
        <td><span class="lbl">Distrito</span><span class="val">{{ $emp->distrito }}</span></td>
        <td><span class="lbl">Provincia</span><span class="val">{{ $emp->provincia }}</span></td>
        <td><span class="lbl">Departamento</span><span class="val">{{ $emp->departamento }}</span></td>
        <td><span class="lbl">Nivel educativo</span><span class="val">{{ $emp->nivel_educativo }}</span></td>
    </tr>
</table>

<!-- SISTEMA PENSIONARIO -->
<div class="sec sub">SISTEMA PENSIONARIO</div>
<table>
    <tr>
        <td style="width:62%">
            <span class="chk">
                {!! $chk($sis === 'AFP' && $afp === 'INTEGRA') !!} SPP Integra &nbsp;&nbsp;
                {!! $chk($sis === 'AFP' && $afp === 'HABITAT') !!} SPP Habitat &nbsp;&nbsp;
                {!! $chk($sis === 'AFP' && $afp === 'PROFUTURO') !!} SPP Profuturo &nbsp;&nbsp;
                {!! $chk($sis === 'AFP' && $afp === 'PRIMA') !!} SPP Prima &nbsp;&nbsp;
                {!! $chk($sis === 'ONP') !!} SNP - ONP
            </span>
        </td>
        <td style="width:23%"><span class="lbl">Fecha ingreso a AFP/ONP</span><span class="val">{{ $f($c?->fecha_afiliacion_pension) }}</span></td>
        <td style="width:15%"><span class="lbl">Código AFP</span><span class="val">{{ $c?->codigo_afp }}</span></td>
    </tr>
</table>

<!-- AREA LABORAL -->
<div class="sec sub">ÁREA LABORAL</div>
<table>
    <tr>
        <td style="width:25%"><span class="lbl">Planta / Sede</span><span class="val">{{ $emp->sede?->nombre }}</span></td>
        <td style="width:25%"><span class="lbl">Área</span><span class="val">{{ $c?->area?->nombre }}</span></td>
        <td style="width:25%"><span class="lbl">Cargo</span><span class="val">{{ $c?->cargo?->nombre }}</span></td>
        <td style="width:25%"><span class="lbl">Fecha de inicio</span><span class="val">{{ $f($c?->fecha_ingreso) }}</span></td>
    </tr>
    <tr>
        <td><span class="lbl">Tipo de contrato</span><span class="val">{{ $c?->tipo_contrato }}</span></td>
        <td><span class="lbl">Aporta al SCTR</span><span class="chk">{!! $chk($c?->aporta_sctr) !!} Sí &nbsp; {!! $chk($c && ! $c->aporta_sctr) !!} No</span></td>
        <td><span class="lbl">Seguro de vida</span><span class="chk">{!! $chk($c?->tiene_vida_ley) !!} Sí</span></td>
        <td><span class="lbl">Categoría / Actividad</span>
            <span class="chk">{!! $chk(str_contains(strtolower((string) $c?->cargo?->nombre), 'maestro')) !!} Maestro &nbsp; {!! $chk(str_contains(strtolower((string) $c?->cargo?->nombre), 'oficial')) !!} Oficial &nbsp; {!! $chk(str_contains(strtolower((string) $c?->cargo?->nombre), 'ayudante')) !!} Ayudante</span>
        </td>
    </tr>
    <tr>
        <td><span class="lbl">Sueldo básico</span><span class="val">{{ $c && $c->sueldo_basico ? 'S/ '.number_format($c->sueldo_basico, 2) : '' }}</span></td>
        <td><span class="lbl">Movilidad</span><span class="val">{{ $c && $c->movilidad ? 'S/ '.number_format($c->movilidad, 2) : '' }}</span></td>
        <td colspan="2"><span class="lbl">Otros</span><span class="val">{{ $c && $c->otros ? 'S/ '.number_format($c->otros, 2) : '' }}</span></td>
    </tr>
    {{-- Datos bancarios omitidos por privacidad: solo se ven/editan en el formulario del sistema. --}}
</table>

<!-- 3) CONYUGE -->
<div class="sec sub">3) DATOS DEL CÓNYUGE O CONCUBINO</div>
<table>
    <tr>
        <td style="width:34%"><span class="lbl">Apellidos y nombres</span><span class="val">{{ $conyuge?->nombres }}</span></td>
        <td style="width:11%"><span class="lbl">Edad</span><span class="val">{{ $conyuge?->fecha_nacimiento ? \Carbon\Carbon::parse($conyuge->fecha_nacimiento)->age : '' }}</span></td>
        <td style="width:18%"><span class="lbl">DNI</span><span class="val">{{ $conyuge?->numero_documento }}</span></td>
        <td style="width:18%"><span class="lbl">Teléfono</span><span class="val"></span></td>
        <td style="width:19%"><span class="lbl">Parentesco</span><span class="val">{{ $conyuge ? ucfirst($conyuge->tipo) : '' }}</span></td>
    </tr>
</table>

<!-- 4) HIJOS -->
<div class="sec sub">4) DATOS DE HIJOS</div>
<table>
    <tr style="background:#eef2f7;">
        <td class="lbl" style="width:40%">Apellidos y nombres</td>
        <td class="lbl" style="width:18%">N° de doc. (DNI)</td>
        <td class="lbl" style="width:10%">Edad</td>
        <td class="lbl" style="width:10%">Sexo</td>
        <td class="lbl" style="width:22%">Fecha de nacimiento</td>
    </tr>
    @forelse($hijos as $h)
    <tr>
        <td class="val">{{ $h->nombres }}</td>
        <td>{{ $h->numero_documento }}</td>
        <td>{{ $h->fecha_nacimiento ? \Carbon\Carbon::parse($h->fecha_nacimiento)->age : '' }}</td>
        <td></td>
        <td>{{ $f($h->fecha_nacimiento) }}</td>
    </tr>
    @empty
    <tr><td colspan="5" class="muted">&nbsp;</td></tr>
    @endforelse
</table>

<!-- 5) EMERGENCIA -->
<div class="sec sub">5) EN CASO DE EMERGENCIA LLAMAR A</div>
<table>
    <tr>
        <td style="width:50%"><span class="lbl">Apellidos y nombres</span><span class="val">{{ $emp->emergencia_nombre }}</span></td>
        <td style="width:25%"><span class="lbl">Teléfono</span><span class="val">{{ $emp->emergencia_telefono }}</span></td>
        <td style="width:25%"><span class="lbl">Parentesco</span><span class="val">{{ $emp->emergencia_parentesco }}</span></td>
    </tr>
</table>

<p class="muted" style="margin-top:6px; font-size:7.5px;">
    La información proporcionada será considerada como <b>declaración jurada</b> sobre la veracidad de los datos señalados.
    En caso de ocultar o proporcionar información falsa constituirá causa suficiente para la rescisión del contrato.
    Es obligación del empleado comunicar a Gerencia de Personal cualquier modificación.
</p>

<!-- Firma -->
<table class="firma">
    <tr>
        <td style="width:50%"><div class="linea"></div>FIRMA DEL TRABAJADOR<br><span class="muted">DNI: {{ $emp->numero_documento }}</span></td>
        <td style="width:50%"><div class="linea"></div>EMPLEADOR / RRHH</td>
    </tr>
</table>

<!-- Area usuaria -->
<div class="sec sub">DATOS DEL ÁREA USUARIA</div>
<table>
    <tr>
        <td style="width:25%"><span class="lbl">Gerencia</span><span class="val">&nbsp;</span></td>
        <td style="width:25%"><span class="lbl">RRHH</span><span class="val">&nbsp;</span></td>
        <td style="width:25%"><span class="lbl">Tipo de remuneración (S/)</span><span class="val">&nbsp;</span></td>
        <td style="width:25%"><span class="lbl">Seleccionado por</span><span class="val">&nbsp;</span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="chk">Puesto nuevo [&nbsp;&nbsp;&nbsp;] &nbsp;&nbsp; Reemplazo [&nbsp;&nbsp;&nbsp;]</span></td>
        <td colspan="2"><span class="lbl">Observaciones</span><span class="val">&nbsp;</span></td>
    </tr>
</table>

<div style="text-align:center; margin-top:8px; font-size:7px;" class="muted">Generado por MiPlanilla — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
