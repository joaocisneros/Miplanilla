<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    payroll: { type: Object, required: true },
    filas: { type: Array, default: () => [] },
});

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const hayDatos = computed(() => props.filas.length > 0);

function descargarRecibo(f) {
    window.location.href = route('honorarios.recibo', f.id);
}

const mostrar = ref(false);
const sel = ref(null);
function verDetalle(f) {
    sel.value = f;
    mostrar.value = true;
}

const totalIngresos = (f) => f.honorario + f.sabado + f.domingo + Number(f.desglose?.ingresos?.incentivos || 0) + Number(f.desglose?.ingresos?.horas_extra || 0);
const totalDescuentos = (f) => Number(f.desglose?.descuentos?.tardanza || 0) + Number(f.desglose?.descuentos?.adelantos || 0);
</script>

<template>
    <Head title="Honorarios (RxH)" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('honorarios.index')" class="text-sm text-indigo-600">&larr; Honorarios</Link>
                    <h2 class="text-xl font-semibold text-gray-800">{{ payroll.descripcion }} — {{ payroll.empresa }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="route('honorarios.excel', payroll.id)" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">📥 Exportar Excel</a>
                    <a :href="route('honorarios.recibos-zip', payroll.id)" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">⬇ Recibos (ZIP)</a>
                </div>
            </div>
        </template>
        <div class="p-6">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase text-gray-500">Estado</div>
                        <div class="mt-1 text-lg font-semibold text-gray-800">{{ payroll.estado }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase text-gray-500">Trabajadores</div>
                        <div class="mt-1 text-lg font-semibold text-gray-800">{{ payroll.cantidad_empleados }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase text-gray-500">Total neto a pagar</div>
                        <div class="mt-1 text-lg font-semibold text-emerald-700">{{ money(payroll.total_neto) }}</div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-3">Trabajador</th>
                                <th class="px-3 py-3 text-center">Días</th>
                                <th class="px-3 py-3 text-center">Faltas</th>
                                <th class="px-3 py-3 text-center">Tard. (min)</th>
                                <th class="px-3 py-3 text-right">Honorario</th>
                                <th class="px-3 py-3 text-right">Sábados</th>
                                <th class="px-3 py-3 text-right">Dom/Fer</th>
                                <th class="px-3 py-3 text-right">H. extra</th>
                                <th class="px-3 py-3 text-right">Bonos</th>
                                <th class="px-3 py-3 text-right font-bold">Neto a pagar</th>
                                <th class="px-3 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 [&_td]:whitespace-nowrap [&_td]:tabular-nums">
                            <tr v-for="(f, i) in filas" :key="i" class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-900">{{ f.nombre }}<span class="block text-xs font-normal text-gray-400">{{ f.dni }}</span></td>
                                <td class="px-3 py-2 text-center">{{ f.dias }}</td>
                                <td class="px-3 py-2 text-center">{{ f.faltas }}</td>
                                <td class="px-3 py-2 text-center">{{ f.tardanza_min }}</td>
                                <td class="px-3 py-2 text-right">{{ money(f.honorario) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ money(f.sabado) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ money(f.domingo) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ money(f.horas_extra) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ money(f.bono) }}</td>
                                <td class="px-3 py-2 text-right font-bold text-emerald-700">{{ money(f.neto) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="verDetalle(f)" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">👁 Ver</button>
                                        <button @click="descargarRecibo(f)" class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">📄 PDF</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!hayDatos"><td colspan="11" class="px-4 py-6 text-center text-gray-500">No hay trabajadores por honorarios en esta planilla.</td></tr>
                        </tbody>
                        <tfoot v-if="hayDatos" class="bg-gray-100 font-bold [&_td]:whitespace-nowrap [&_td]:tabular-nums">
                            <tr>
                                <td class="px-3 py-3" colspan="9">TOTAL NETO</td>
                                <td class="px-3 py-3 text-right text-emerald-800">{{ money(payroll.total_neto) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal vista rápida por trabajador -->
        <CrudModal :show="mostrar" max-width="lg" :titulo="sel?.nombre ?? 'Detalle'" @close="mostrar = false">
            <div v-if="sel" class="space-y-4 text-sm">
                <!-- Asistencia -->
                <div class="grid grid-cols-3 gap-2 rounded-md border border-gray-200 bg-white p-3 text-center">
                    <div><div class="text-lg font-bold text-gray-800">{{ sel.dias }}</div><div class="text-xs uppercase text-gray-500">Días trab.</div></div>
                    <div><div class="text-lg font-bold" :class="sel.faltas > 0 ? 'text-red-600' : 'text-gray-800'">{{ sel.faltas }}</div><div class="text-xs uppercase text-gray-500">Faltas</div></div>
                    <div><div class="text-lg font-bold" :class="sel.tardanza_min > 0 ? 'text-amber-600' : 'text-gray-800'">{{ sel.tardanza_min }}'</div><div class="text-xs uppercase text-gray-500">Tardanza</div></div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Ingresos -->
                    <div>
                        <h4 class="mb-1 font-semibold text-gray-700">Ingresos</h4>
                        <table class="w-full">
                            <tr><td class="py-1 text-gray-600">Honorario ({{ sel.dias }} días)</td><td class="py-1 text-right">{{ money(sel.honorario) }}</td></tr>
                            <tr v-if="sel.sabado > 0"><td class="py-1 text-gray-600">Sábados</td><td class="py-1 text-right">{{ money(sel.sabado) }}</td></tr>
                            <tr v-if="sel.domingo > 0"><td class="py-1 text-gray-600">Domingos / feriados</td><td class="py-1 text-right">{{ money(sel.domingo) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.ingresos?.horas_extra) > 0"><td class="py-1 text-gray-600">Horas extra</td><td class="py-1 text-right">{{ money(sel.desglose.ingresos.horas_extra) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.ingresos?.incentivos) > 0"><td class="py-1 text-gray-600">Incentivos / bonos</td><td class="py-1 text-right">{{ money(sel.desglose.ingresos.incentivos) }}</td></tr>
                            <tr class="border-t font-semibold"><td class="py-1">Total ingresos</td><td class="py-1 text-right">{{ money(totalIngresos(sel)) }}</td></tr>
                        </table>
                    </div>
                    <!-- Descuentos -->
                    <div>
                        <h4 class="mb-1 font-semibold text-gray-700">Descuentos</h4>
                        <table class="w-full">
                            <tr><td class="py-1 text-gray-600">Descuento por tardanza</td><td class="py-1 text-right text-red-600">{{ money(sel.desglose?.descuentos?.tardanza) }}</td></tr>
                            <tr v-if="Number(sel.desglose?.descuentos?.adelantos) > 0"><td class="py-1 text-gray-600">Adelantos</td><td class="py-1 text-right text-red-600">{{ money(sel.desglose.descuentos.adelantos) }}</td></tr>
                            <tr class="border-t font-semibold"><td class="py-1">Total descuentos</td><td class="py-1 text-right text-red-600">{{ money(totalDescuentos(sel)) }}</td></tr>
                        </table>
                        <p v-if="sel.faltas > 0" class="mt-2 text-xs text-gray-500">Faltas: {{ sel.faltas }} {{ sel.faltas == 1 ? 'día' : 'días' }} (ya reflejadas en los días pagados).</p>
                    </div>
                </div>

                <!-- Neto -->
                <div class="rounded-md bg-emerald-600 p-3 text-white">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">👤 NETO A PAGAR</span>
                        <span class="text-lg font-bold">{{ money(sel.neto) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-emerald-100">Sueldo neto: sin AFP/ONP, EsSalud, gratificación, vacaciones ni CTS.</p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t pt-3">
                    <button @click="descargarRecibo(sel)" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Descargar recibo PDF</button>
                    <button @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
