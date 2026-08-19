<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    payroll: { type: Object, required: true },
    detalles: { type: Array, default: () => [] },
});
const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });

// Movilidad sola (se junta con Sueldo base) vs. "extras" (sábados/domingos/HE/bonos) aparte.
const extras = (d) => {
    const ing = d?.desglose?.ingresos ?? {};
    return Number(ing.sabado || 0) + Number(ing.domingo_feriado || 0) + Number(ing.horas_extra || 0) + Number(ing.incentivos || 0);
};
const desgloseExtras = (d) => {
    const ing = d?.desglose?.ingresos ?? {};
    const partes = [
        ['Sábados', ing.sabado], ['Dom/Fer', ing.domingo_feriado],
        ['H. extra', ing.horas_extra], ['Bono/Comisión', ing.incentivos],
    ].filter(([, v]) => Number(v || 0) !== 0)
        .map(([n, v]) => `${n}: S/ ${Number(v).toFixed(2)}`);
    return partes.length ? partes.join('  ·  ') : 'Sin extras este periodo';
};

const aportesTotal = (d) => Object.values(d?.desglose?.aportes_empleador ?? {}).reduce((s, v) => s + Number(v || 0), 0);
const costoEmpresa = (d) => Number(d?.total_ingresos || 0) + aportesTotal(d);

// Presentación: remuneración del periodo completa + faltas como descuento.
const descFaltas = (d) => Number(d?.desglose?.asistencia?.descuento_faltas ?? 0);
const remPeriodo = (d) => Number(d?.desglose?.asistencia?.remuneracion_periodo ?? d?.desglose?.ingresos?.remuneracion_devengada ?? 0);
const totalIngresosDisp = (d) => Number(d?.total_ingresos || 0) + descFaltas(d);
const totalDescuentosDisp = (d) => Number(d?.total_descuentos || 0) + descFaltas(d);

const mostrar = ref(false);
const sel = ref(null);

// Modal de "Extras": muestra de donde sale cada sol de esa columna.
const mostrarExtras = ref(false);
const selExtras = ref(null);
function verExtras(d) {
    selExtras.value = d;
    mostrarExtras.value = true;
}
// Modal de "Neto a pagar". Sigue la formula que usa el cliente:
//   neto base + movilidad - 5ta categoria + reintegro - descuento prestamo
// Verificado contra el motor: cuadra en el 100% de los trabajadores.
const mostrarNeto = ref(false);
const selNeto = ref(null);
function verNeto(d) {
    selNeto.value = d;
    mostrarNeto.value = true;
}
const lineasNeto = (d) => {
    const de = d?.desglose?.descuentos ?? {};
    const rein = d?.desglose?.reintegros;
    const reintegro = typeof rein === 'object' && rein !== null
        ? Object.values(rein).reduce((a, b) => a + Number(b || 0), 0) : Number(rein || 0);
    return [
        { nombre: 'Neto base', monto: Number(d?.rem_neta_quincenal || 0), resta: false },
        { nombre: 'Movilidad', monto: Number(d?.total_movilidad || 0), resta: false },
        { nombre: '5ta categoria', monto: Number(de.renta_5ta ?? d?.renta_5ta ?? 0), resta: true },
        { nombre: 'Reintegro', monto: reintegro, resta: false },
        { nombre: 'Descuento prestamo', monto: Number(de.adelantos || 0), resta: true },
    ];
};

// Resumen en una linea de que compone cada total del modal "Ver".
// Solo se listan conceptos que realmente suman al total (verificado contra el motor:
// total_descuentos = pension + renta 5ta; la tardanza ya va dentro de la base afecta).
const ETIQ_ING = {
    remuneracion_devengada: 'Remuneracion', movilidad: 'Movilidad', incentivos: 'Bonos',
    sabado: 'Sabados', domingo_feriado: 'Dom/Fer', horas_extra: 'H. extra',
    otros_afectos: 'Otros afectos', subsidio: 'Subsidio', licencia: 'Licencia',
    vacaciones: 'Vacaciones', gratificacion: 'Gratificacion', hijo_enfermo: 'Hijo enfermo',
};
const ORDEN_ETIQ = Object.keys(ETIQ_ING);
const resumenIngresos = (d) => Object.entries(d?.desglose?.ingresos ?? {})
    .filter(([, v]) => Number(v || 0) !== 0)
    .sort(([a], [b]) => ORDEN_ETIQ.indexOf(a) - ORDEN_ETIQ.indexOf(b))
    .map(([k, v]) => `${ETIQ_ING[k] ?? k} ${money(v)}`).join('  ·  ');
const resumenDescuentos = (d) => {
    const de = d?.desglose?.descuentos ?? {};
    return [
        ['Pension', Number(de?.pension?.total || 0)],
        ['Renta 5ta', Number(de.renta_5ta || 0)],
    ].filter(([, v]) => v !== 0).map(([n, v]) => `${n} ${money(v)}`).join('  ·  ');
};

// Modal de "Neto base". Formula completa del motor (CalculadoraPlanilla):
//   (basico + asig.familiar)/30 x dias  - tardanza + otros afectos = base afecta
//   base afecta - AFP/ONP = neto base
const mostrarBase = ref(false);
const selBase = ref(null);
function verBase(d) {
    selBase.value = d;
    mostrarBase.value = true;
}
const nombrePension = (d) => {
    const det = d?.desglose?.descuentos?.pension?.detalle ?? {};
    return det.sistema === 'AFP' ? ('AFP ' + (det.afp ?? '')).trim() : (det.sistema ?? 'Pension');
};
const lineasBase = (d) => {
    const i = d?.desglose?.ingresos ?? {};
    const de = d?.desglose?.descuentos ?? {};
    const a = d?.desglose?.asistencia ?? {};
    const diasBase = Number(d?.desglose?.dias_base || 30);
    // Base prorrateable: basico + asignacion familiar (la movilidad va aparte).
    const base = Number(a.sueldo_basico || 0) + Number(a.asignacion_familiar || 0);
    const min = Number(a.minutos_tarde || 0);
    return [
        {
            nombre: 'Remuneracion devengada',
            monto: Number(i.remuneracion_devengada || 0),
            resta: false,
            calculo: `${money(base)} ÷ ${diasBase} × ${Number(a.dias_trabajados || 0)} día(s) trabajado(s)`,
        },
        {
            nombre: 'Tardanza',
            monto: Number(de.tardanza || 0),
            resta: true,
            calculo: min ? `${min} min acumulados en el periodo` : '',
        },
        { nombre: 'Otros afectos', monto: Number(i.otros_afectos || 0), resta: false, calculo: '' },
    ];
};

// Conceptos que suman exactamente lo que muestra la columna EXTRAS.
// Se muestran SIEMPRE los cuatro, tengan monto o no, para poder ver de un
// vistazo que concepto no tiene nada registrado.
const conceptosExtras = (d) => {
    const ing = d?.desglose?.ingresos ?? {};
    const ads = d?.adicionales ?? [];
    const horas = ads.reduce((s, a) => s + Number(a.horas || 0), 0);
    const minutos = ads.reduce((s, a) => s + Number(a.minutos || 0), 0);
    const sinAprobar = ads.filter((a) => Number(a.monto_horas || 0) > 0 && !a.aprobado);
    const montoSinAprobar = sinAprobar.reduce((s, a) => s + Number(a.monto_horas || 0), 0);
    return [
        { nombre: 'Sabados', monto: Number(ing.sabado || 0), detalle: '', alerta: '' },
        { nombre: 'Domingos / Feriados', monto: Number(ing.domingo_feriado || 0), detalle: '', alerta: '' },
        {
            nombre: 'Horas extra',
            monto: Number(ing.horas_extra || 0),
            detalle: horas || minutos ? `${horas}h ${minutos}min registrados` : '',
            alerta: montoSinAprobar > 0 ? `S/ ${montoSinAprobar.toFixed(2)} sin aprobar — no se paga` : '',
        },
        { nombre: 'Bonos / Comisiones', monto: Number(ing.incentivos || 0), detalle: '', alerta: '' },
    ];
};

function verDetalle(d) {
    sel.value = d;
    mostrar.value = true;
}
</script>

<template>
    <Head title="Detalle de planilla" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('planilla.index')" class="text-sm text-indigo-600">&larr; Planilla</Link>
                    <h2 class="text-xl font-semibold text-gray-800">{{ payroll.descripcion }} — {{ payroll.empresa }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="route('planilla.detalle-excel', payroll.id)" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">📥 Planilla detallada (Excel)</a>
                    <a v-if="payroll.es_mensual" :href="route('boletas.zip', payroll.id)" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">⬇ Boletas mensuales (ZIP)</a>
                </div>
            </div>
        </template>
        <div class="p-6">
            <div class="space-y-6">

                <!-- Resumen -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Empleados</div><div class="text-2xl font-bold">{{ payroll.cantidad_empleados }}</div></div>
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Total ingresos</div><div class="text-xl font-bold text-gray-800">{{ money(payroll.total_ingresos) }}</div></div>
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Total neto</div><div class="text-xl font-bold text-green-700">{{ money(payroll.total_neto) }}</div></div>
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Aportes empleador</div><div class="text-xl font-bold text-blue-700">{{ money(payroll.total_aportes_empleador) }}</div></div>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Trabajador</th>
                                <th class="px-4 py-3">Sistema / Pensión</th>
                                <th class="px-4 py-3" title="Sueldo base + Movilidad y extras (sábados, domingos, horas extra, bonos)">Sueldo base / Movilidad</th>
                                <th class="px-4 py-3">Asig. familiar</th>
                                <th class="px-4 py-3">Sueldo mensual</th>
                                <th class="px-4 py-3" title="(Sueldo base + Asig. familiar) ÷ 30 × días trabajados">Días trab. / Pago</th>
                                <th class="px-4 py-3">Desc. falta</th>
                                <th class="px-4 py-3">Desc. tardanza</th>
                                <th class="px-4 py-3" title="Sábados + domingos/feriados + horas extra + bonos">Extras</th>
                                <th class="px-4 py-3">Renta 5ta</th>
                                <th class="px-4 py-3" title="Base afecta − Pensión (sin movilidad/extras ni renta 5ta todavía)">Neto base</th>
                                <th class="px-4 py-3">Neto a pagar</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="d in detalles" :key="d.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ d.empleado }}</td>
                                <td class="px-4 py-2">
                                    <span :class="d.sistema?.startsWith('AFP') ? 'bg-purple-100 text-purple-800' : 'bg-sky-100 text-sky-800'" class="rounded-full px-2 py-1 text-xs">{{ d.sistema }}</span>
                                    <span class="block text-xs text-red-600">{{ money(d.pension_total) }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    {{ money(d.desglose?.asistencia?.sueldo_basico) }}
                                    <span class="block text-xs text-amber-700">+ {{ money(d.desglose?.asistencia?.movilidad_mensual) }} mov.</span>
                                </td>
                                <td class="px-4 py-2">{{ money(d.desglose?.asistencia?.asignacion_familiar) }}</td>
                                <td class="px-4 py-2">{{ money(d.desglose?.asistencia?.sueldo_mensual) }}</td>
                                <td class="px-4 py-2 font-medium text-emerald-700">
                                    {{ money(d.desglose?.ingresos?.remuneracion_devengada) }}
                                    <span class="block text-xs text-gray-400">{{ d.desglose?.asistencia?.dias_trabajados ?? 0 }} día(s) trab.</span>
                                </td>
                                <td class="px-4 py-2 text-red-600">
                                    {{ money(d.desglose?.asistencia?.descuento_faltas) }}
                                    <span class="block text-xs text-gray-400">{{ d.desglose?.asistencia?.faltas ?? 0 }} día(s)</span>
                                </td>
                                <td class="px-4 py-2 text-red-600">
                                    {{ money(d.desglose?.descuentos?.tardanza) }}
                                    <span class="block text-xs text-gray-400">{{ d.desglose?.asistencia?.minutos_tarde ?? 0 }} min</span>
                                </td>
                                <td class="px-4 py-2">
                                    <button type="button" @click="verExtras(d)" :title="desgloseExtras(d) + ' — clic para ver el detalle'"
                                        class="rounded px-2 py-1 text-left font-medium text-amber-700 underline decoration-amber-300 decoration-dotted underline-offset-2 hover:bg-amber-50 hover:decoration-solid">
                                        {{ money(extras(d)) }}
                                    </button>
                                </td>
                                <td class="px-4 py-2 text-red-600">{{ money(d.renta_5ta) }}</td>
                                <td class="px-4 py-2">
                                    <button type="button" @click="verBase(d)" title="Clic para ver como se compone el neto base"
                                        class="rounded px-2 py-1 text-left text-gray-600 underline decoration-gray-300 decoration-dotted underline-offset-2 hover:bg-gray-50 hover:decoration-solid">
                                        {{ money(d.rem_neta_quincenal) }}
                                    </button>
                                </td>
                                <td class="px-4 py-2">
                                    <button type="button" @click="verNeto(d)" title="Clic para ver como se compone este neto"
                                        class="rounded px-2 py-1 text-left font-semibold text-green-700 underline decoration-green-300 decoration-dotted underline-offset-2 hover:bg-green-50 hover:decoration-solid">
                                        {{ money(d.neto) }}
                                    </button>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="verDetalle(d)" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">👁 Ver</button>
                                        <a v-if="payroll.es_mensual" :href="route('boletas.pdf', d.id)" class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">📄 Boleta PDF</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal vista rápida por trabajador -->
        <!-- Resumen del trabajador. El desglose fino vive en los modales de
             Extras / Neto base / Neto a pagar, asi que aqui solo va lo que no
             esta en ningun otro lado: asistencia, totales y costo empresa. -->
        <CrudModal :show="mostrar" max-width="2xl" :titulo="sel?.empleado ?? 'Detalle'" @close="mostrar = false">
            <div v-if="sel" class="space-y-5">
                <p class="text-xs text-gray-500">{{ payroll.descripcion }} — {{ payroll.empresa }} · {{ sel.sistema }}</p>

                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="rounded-lg bg-gray-50 py-2">
                        <p class="text-lg font-bold text-gray-800">{{ sel.desglose?.asistencia?.dias_trabajados ?? 0 }}</p>
                        <p class="text-[11px] uppercase text-gray-500">Días trab.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 py-2">
                        <p class="text-lg font-bold" :class="Number(sel.desglose?.asistencia?.faltas) ? 'text-red-600' : 'text-gray-800'">{{ sel.desglose?.asistencia?.faltas ?? 0 }}</p>
                        <p class="text-[11px] uppercase text-gray-500">Faltas</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 py-2">
                        <p class="text-lg font-bold" :class="Number(sel.desglose?.asistencia?.minutos_tarde) ? 'text-red-600' : 'text-gray-800'">{{ sel.desglose?.asistencia?.minutos_tarde ?? 0 }}'</p>
                        <p class="text-[11px] uppercase text-gray-500">Tardanza</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 py-2">
                        <p class="text-lg font-bold text-gray-800">{{ money(extras(sel)) }}</p>
                        <p class="text-[11px] uppercase text-gray-500">Extras</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            <tr>
                                <td class="px-4 py-2 text-gray-700">
                                    <span class="mr-1 text-gray-400">+</span>Total ingresos
                                    <span class="mt-0.5 block pl-4 text-xs text-gray-500">{{ resumenIngresos(sel) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right align-top font-medium text-gray-800">{{ money(sel.total_ingresos) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-gray-700">
                                    <span class="mr-1 text-gray-400">−</span>Total descuentos
                                    <span v-if="resumenDescuentos(sel)" class="mt-0.5 block pl-4 text-xs text-gray-500">{{ resumenDescuentos(sel) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right align-top font-medium text-red-600">− {{ money(sel.total_descuentos) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 bg-green-50">
                            <tr>
                                <td class="px-4 py-2 font-semibold text-gray-800">Neto a pagar</td>
                                <td class="px-4 py-2 text-right text-base font-bold text-green-700">{{ money(sel.neto) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Costo empresa: se mantiene con su detalle porque son aportes que
                     NO se le descuentan al trabajador y conviene que quede explicito. -->
                <div class="rounded-lg bg-blue-50 p-4 ring-1 ring-blue-100">
                    <p class="text-sm font-semibold text-blue-900">📊 Lo que desembolsa la EMPRESA</p>
                    <p class="mb-2 text-xs text-blue-700">
                        Estos aportes los paga la empresa aparte. No salen del sueldo del trabajador.
                    </p>
                    <table class="min-w-full text-sm">
                        <tbody>
                            <tr><td class="py-1 text-gray-600">Sueldo bruto del trabajador</td><td class="py-1 text-right text-gray-800">{{ money(sel.total_ingresos) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.aportes_empleador?.essalud)"><td class="py-1 text-gray-600">(+) EsSalud</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.essalud) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.aportes_empleador?.sctr_pension)"><td class="py-1 text-gray-600">(+) SCTR Pensión</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.sctr_pension) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.aportes_empleador?.sctr_salud)"><td class="py-1 text-gray-600">(+) SCTR Salud</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.sctr_salud) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.aportes_empleador?.vida_ley)"><td class="py-1 text-gray-600">(+) Seguro Vida Ley</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.vida_ley) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.aportes_empleador?.senati)"><td class="py-1 text-gray-600">(+) Senati</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.senati) }}</td></tr>
                            <tr class="border-t border-blue-200 font-bold text-blue-900"><td class="py-1">= Total que paga la empresa</td><td class="py-1 text-right">{{ money(costoEmpresa(sel)) }}</td></tr>
                        </tbody>
                    </table>
                    <p class="mt-3 border-t border-blue-200 pt-2 text-xs text-blue-800">
                        El trabajador recibe <b>{{ money(sel.neto) }}</b> en mano: del bruto se le descuenta
                        su AFP/ONP y renta 5ta. La empresa paga <b>{{ money(costoEmpresa(sel)) }}</b>.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t pt-3">
                    <a :href="route('boletas.pdf', sel.id)" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Boleta PDF</a>
                    <button @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>

        <!-- Detalle de la columna EXTRAS: en que se compone y de que registro salio. -->
        <CrudModal :show="mostrarExtras" max-width="2xl" :titulo="'Extras — ' + (selExtras?.empleado ?? '')" @close="mostrarExtras = false">
            <div v-if="selExtras" class="space-y-5">
                <p class="text-xs text-gray-500">{{ payroll.descripcion }} — {{ payroll.empresa }}</p>

                <!-- 1. Composicion: suma exactamente lo que muestra la columna -->
                <div class="overflow-hidden rounded-lg border">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            <tr v-for="c in conceptosExtras(selExtras)" :key="c.nombre" :class="c.monto === 0 && 'bg-gray-50/60'">
                                <td class="px-4 py-2" :class="c.monto === 0 ? 'text-gray-400' : 'text-gray-700'">
                                    {{ c.nombre }}
                                    <span v-if="c.detalle" class="block text-xs text-gray-500">{{ c.detalle }}</span>
                                    <span v-if="c.alerta" class="block text-xs font-semibold text-red-600">{{ c.alerta }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <span v-if="c.monto === 0" class="text-gray-400">{{ money(0) }}</span>
                                    <span v-else class="font-medium text-amber-700">{{ money(c.monto) }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 bg-amber-50">
                            <tr>
                                <td class="px-4 py-2 font-semibold text-gray-800">Total extras</td>
                                <td class="px-4 py-2 text-right text-base font-bold text-amber-700">{{ money(extras(selExtras)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex justify-end border-t pt-3">
                    <button @click="mostrarExtras = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>

        <!-- Neto a pagar: mismas 5 lineas que usa el cliente, mismo estilo sobrio que Extras. -->
        <CrudModal :show="mostrarNeto" max-width="2xl" :titulo="'Neto a pagar — ' + (selNeto?.empleado ?? '')" @close="mostrarNeto = false">
            <div v-if="selNeto" class="space-y-5">
                <p class="text-xs text-gray-500">{{ payroll.descripcion }} — {{ payroll.empresa }}</p>

                <div class="overflow-hidden rounded-lg border">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            <tr v-for="l in lineasNeto(selNeto)" :key="l.nombre" :class="l.monto === 0 && 'bg-gray-50/60'">
                                <td class="px-4 py-2" :class="l.monto === 0 ? 'text-gray-400' : 'text-gray-700'">
                                    <span class="mr-1 text-gray-400">{{ l.resta ? '−' : '+' }}</span>{{ l.nombre }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <span v-if="l.monto === 0" class="text-gray-400">{{ money(0) }}</span>
                                    <span v-else :class="l.resta ? 'font-medium text-red-600' : 'font-medium text-gray-800'">
                                        {{ l.resta ? '− ' : '' }}{{ money(l.monto) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 bg-green-50">
                            <tr>
                                <td class="px-4 py-2 font-semibold text-gray-800">Neto a pagar</td>
                                <td class="px-4 py-2 text-right text-base font-bold text-green-700">{{ money(selNeto.neto) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex justify-end border-t pt-3">
                    <button @click="mostrarNeto = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>

        <!-- Neto base: mismo estilo sobrio, con el subtotal de base afecta. -->
        <CrudModal :show="mostrarBase" max-width="2xl" :titulo="'Neto base — ' + (selBase?.empleado ?? '')" @close="mostrarBase = false">
            <div v-if="selBase" class="space-y-5">
                <p class="text-xs text-gray-500">{{ payroll.descripcion }} — {{ payroll.empresa }}</p>

                <div class="overflow-hidden rounded-lg border">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            <tr v-for="l in lineasBase(selBase)" :key="l.nombre" :class="l.monto === 0 && 'bg-gray-50/60'">
                                <td class="px-4 py-2" :class="l.monto === 0 ? 'text-gray-400' : 'text-gray-700'">
                                    <span class="mr-1 text-gray-400">{{ l.resta ? '−' : '+' }}</span>{{ l.nombre }}
                                    <span v-if="l.calculo" class="mt-0.5 block pl-4 text-xs text-gray-500">{{ l.calculo }}</span>
                                </td>
                                <td class="px-4 py-2 text-right align-top">
                                    <span v-if="l.monto === 0" class="text-gray-400">{{ money(0) }}</span>
                                    <span v-else :class="l.resta ? 'font-medium text-red-600' : 'font-medium text-gray-800'">
                                        {{ l.resta ? '− ' : '' }}{{ money(l.monto) }}
                                    </span>
                                </td>
                            </tr>
                            <tr class="bg-gray-100">
                                <td class="px-4 py-2 font-semibold text-gray-700">= Base afecta</td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-800">{{ money(selBase.base_afecta) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-gray-700"><span class="mr-1 text-gray-400">−</span>{{ nombrePension(selBase) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">− {{ money(selBase.pension_total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 bg-slate-100">
                            <tr>
                                <td class="px-4 py-2 font-semibold text-gray-800">Neto base</td>
                                <td class="px-4 py-2 text-right text-base font-bold text-gray-800">{{ money(selBase.rem_neta_quincenal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex justify-end border-t pt-3">
                    <button @click="mostrarBase = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
