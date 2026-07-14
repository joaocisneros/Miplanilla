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

// Desglose de "Movilidad y extras" para el tooltip de la celda.
const desgloseMov = (d) => {
    const ing = d?.desglose?.ingresos ?? {};
    const partes = [
        ['Movilidad', ing.movilidad], ['Sábados', ing.sabado], ['Dom/Fer', ing.domingo_feriado],
        ['H. extra', ing.horas_extra], ['Bono/Comisión', ing.incentivos],
    ].filter(([, v]) => Number(v || 0) !== 0)
        .map(([n, v]) => `${n}: S/ ${Number(v).toFixed(2)}`);
    return partes.length ? partes.join('  ·  ') : 'Sin movilidad ni extras este periodo';
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
                    <a :href="route('boletas.zip', payroll.id)" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">⬇ Boletas (ZIP)</a>
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
                            <tr><th class="px-4 py-3">Trabajador</th><th class="px-4 py-3">Sistema</th><th class="px-4 py-3">Pensión</th><th class="px-4 py-3">Base afecta</th><th class="px-4 py-3">Rem. neta quincenal</th><th class="px-4 py-3" title="Movilidad + sábados + domingos + horas extra + bonos">Movilidad y extras</th><th class="px-4 py-3">Renta 5ta</th><th class="px-4 py-3">Neto</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="d in detalles" :key="d.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ d.empleado }}</td>
                                <td class="px-4 py-2"><span :class="d.sistema?.startsWith('AFP') ? 'bg-purple-100 text-purple-800' : 'bg-sky-100 text-sky-800'" class="rounded-full px-2 py-1 text-xs">{{ d.sistema }}</span></td>
                                <td class="px-4 py-2 text-red-600">{{ money(d.pension_total) }}</td>
                                <td class="px-4 py-2">{{ money(d.base_afecta) }}</td>
                                <td class="px-4 py-2 font-medium">{{ money(d.rem_neta_quincenal) }}</td>
                                <td class="px-4 py-2 text-amber-700 cursor-help" :title="desgloseMov(d)">{{ money(d.total_movilidad) }}</td>
                                <td class="px-4 py-2 text-red-600">{{ money(d.renta_5ta) }}</td>
                                <td class="px-4 py-2 font-semibold text-green-700">{{ money(d.neto) }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="verDetalle(d)" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">👁 Ver</button>
                                        <a :href="route('boletas.pdf', d.id)" class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">📄 PDF</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal vista rápida por trabajador -->
        <CrudModal :show="mostrar" max-width="2xl" :titulo="sel?.empleado ?? 'Detalle'" @close="mostrar = false">
            <div v-if="sel" class="space-y-4 text-sm">
                <div class="flex items-center justify-between rounded-md bg-gray-50 p-3">
                    <span class="text-gray-600">Sistema de pensión</span>
                    <span :class="sel.sistema?.startsWith('AFP') ? 'bg-purple-100 text-purple-800' : 'bg-sky-100 text-sky-800'" class="rounded-full px-2 py-1 text-xs">{{ sel.sistema }}</span>
                </div>

                <!-- Asistencia del periodo (de dónde sale el monto) -->
                <div v-if="sel.desglose?.asistencia" class="grid grid-cols-2 gap-2 rounded-md border border-gray-200 bg-white p-3 text-center sm:grid-cols-4">
                    <div><div class="text-lg font-bold text-gray-800">{{ sel.desglose.asistencia.dias_trabajados }}<span class="text-xs font-normal text-gray-400">/{{ sel.desglose.asistencia.dias_periodo }}</span></div><div class="text-xs uppercase text-gray-500">Días trab.</div></div>
                    <div><div class="text-lg font-bold" :class="sel.desglose.asistencia.faltas > 0 ? 'text-red-600' : 'text-gray-800'">{{ sel.desglose.asistencia.faltas }}</div><div class="text-xs uppercase text-gray-500">Faltas</div></div>
                    <div><div class="text-lg font-bold" :class="sel.desglose.asistencia.minutos_tarde > 0 ? 'text-amber-600' : 'text-gray-800'">{{ sel.desglose.asistencia.minutos_tarde }}'</div><div class="text-xs uppercase text-gray-500">Tardanza</div></div>
                    <div><div class="text-lg font-bold text-gray-800">{{ sel.desglose.asistencia.horas_extra }}</div><div class="text-xs uppercase text-gray-500">H. Extra</div></div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Ingresos -->
                    <div>
                        <h4 class="mb-1 font-semibold text-gray-700">Ingresos</h4>
                        <table class="w-full">
                            <tr><td class="py-1 text-gray-600">Remuneración ({{ sel.desglose?.asistencia?.dias_periodo ?? 30 }} días)</td><td class="py-1 text-right">{{ money(remPeriodo(sel)) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Horas extra</td><td class="py-1 text-right">{{ money(sel.desglose?.ingresos?.horas_extra) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Otros (afectos)</td><td class="py-1 text-right">{{ money(sel.desglose?.ingresos?.otros_afectos) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Movilidad</td><td class="py-1 text-right">{{ money(sel.desglose?.ingresos?.movilidad) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Subsidio</td><td class="py-1 text-right">{{ money(sel.desglose?.ingresos?.subsidio) }}</td></tr>
                            <tr class="border-t font-semibold"><td class="py-1">Total ingresos</td><td class="py-1 text-right">{{ money(totalIngresosDisp(sel)) }}</td></tr>
                        </table>
                    </div>
                    <!-- Descuentos -->
                    <div>
                        <h4 class="mb-1 font-semibold text-gray-700">Descuentos</h4>
                        <table class="w-full">
                            <tr><td class="py-1 text-gray-600">Faltas <span v-if="sel.desglose?.asistencia?.faltas" class="text-xs text-gray-400">({{ sel.desglose.asistencia.faltas }} días)</span></td><td class="py-1 text-right text-red-600">{{ money(descFaltas(sel)) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Tardanza</td><td class="py-1 text-right text-red-600">{{ money(sel.desglose?.descuentos?.tardanza) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Pensión</td><td class="py-1 text-right text-red-600">{{ money(sel.pension_total) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Renta 5ta</td><td class="py-1 text-right text-red-600">{{ money(sel.renta_5ta) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">Adelantos</td><td class="py-1 text-right text-red-600">{{ money(sel.desglose?.descuentos?.adelantos) }}</td></tr>
                            <tr class="border-t font-semibold"><td class="py-1">Total descuentos</td><td class="py-1 text-right text-red-600">{{ money(totalDescuentosDisp(sel)) }}</td></tr>
                        </table>
                    </div>
                </div>

                <!-- Neto (lo que recibe el trabajador) -->
                <div class="rounded-md bg-green-600 p-3 text-white">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">👤 NETO A PAGAR</span>
                        <span class="text-lg font-bold">{{ money(sel.neto) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-green-100">Lo que recibe el trabajador en mano (ingresos {{ money(totalIngresosDisp(sel)) }} − descuentos {{ money(totalDescuentosDisp(sel)) }}).</p>
                </div>

                <!-- Costo para la empresa (cálculo aparte, NO se le quita al trabajador) -->
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <h4 class="mb-2 font-semibold text-blue-900">🏢 Costo para la empresa <span class="font-normal text-blue-700">(aparte — no se descuenta al trabajador)</span></h4>
                    <table class="w-full">
                        <tr><td class="py-1 text-gray-600">Sueldo bruto</td><td class="py-1 text-right">{{ money(sel.total_ingresos) }}</td></tr>
                        <tr><td class="py-1 text-gray-600">(+) EsSalud</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.essalud) }}</td></tr>
                        <tr v-if="Number(sel.desglose?.aportes_empleador?.sctr_pension)"><td class="py-1 text-gray-600">(+) SCTR Pensión</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.sctr_pension) }}</td></tr>
                        <tr v-if="Number(sel.desglose?.aportes_empleador?.sctr_salud)"><td class="py-1 text-gray-600">(+) SCTR Salud</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.sctr_salud) }}</td></tr>
                        <tr v-if="Number(sel.desglose?.aportes_empleador?.vida_ley)"><td class="py-1 text-gray-600">(+) Seguro Vida Ley</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.vida_ley) }}</td></tr>
                        <tr v-if="Number(sel.desglose?.aportes_empleador?.senati)"><td class="py-1 text-gray-600">(+) Senati</td><td class="py-1 text-right text-blue-700">{{ money(sel.desglose?.aportes_empleador?.senati) }}</td></tr>
                        <tr class="border-t border-blue-200 font-bold text-blue-900"><td class="py-1">= Costo total para la empresa</td><td class="py-1 text-right">{{ money(costoEmpresa(sel)) }}</td></tr>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-3 border-t pt-3">
                    <a :href="route('boletas.pdf', sel.id)" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Descargar boleta PDF</a>
                    <button @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
