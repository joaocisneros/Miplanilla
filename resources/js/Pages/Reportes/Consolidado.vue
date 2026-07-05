<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    porEmpresa: { type: Array, default: () => [] },
    totalGeneral: { type: Object, default: () => ({}) },
    filtros: { type: Object, default: () => ({ anio: 2026, mes: 6 }) },
});

const anio = ref(props.filtros.anio);
const mes = ref(props.filtros.mes);

function filtrar() {
    router.get(route('reportes.consolidado'), { anio: anio.value, mes: mes.value }, { preserveState: true });
}
function exportar() {
    // Respeta la pestaña activa: "todas" exporta las 3, o solo la empresa seleccionada.
    window.location.href = route('reportes.consolidado.export', {
        anio: anio.value,
        mes: mes.value,
        empresa: empresaActiva.value !== 'todas' ? empresaActiva.value : undefined,
    });
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const meses = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const hayDatos = computed(() => props.porEmpresa.length > 0);

// Pestañas por empresa (sin desplegable): "todas" o una empresa concreta.
const empresaActiva = ref('todas');
const mostradas = computed(() =>
    empresaActiva.value === 'todas'
        ? props.porEmpresa
        : props.porEmpresa.filter((e) => e.empresa === empresaActiva.value),
);
// Resumen para las tarjetas: total general (todas) o la empresa seleccionada.
const resumen = computed(() =>
    empresaActiva.value === 'todas'
        ? props.totalGeneral
        : (props.porEmpresa.find((e) => e.empresa === empresaActiva.value) ?? {}),
);
</script>

<template>
    <Head title="Consolidado" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Reporte consolidado (todas las empresas)</h2></template>
        <div class="p-6">
            <div class="space-y-6">
                <div class="rounded-lg bg-indigo-50 p-4 text-sm text-indigo-800">
                    Cada empresa se calcula de forma independiente (SUNAT/SUNAFIL audita por separado). Aquí se suman los totales y se muestra el <b>costo real</b> de la planilla (sueldos + aportes del empleador).
                </div>

                <!-- Filtros + Exportar -->
                <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div><label class="text-sm text-gray-700">Año</label><input v-model="anio" type="number" class="mt-1 block w-28 rounded-md border-gray-300 text-sm" /></div>
                    <div><label class="text-sm text-gray-700">Mes</label><select v-model="mes" class="mt-1 block rounded-md border-gray-300 text-sm"><option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option></select></div>
                    <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filtrar</button>
                    <button v-if="hayDatos" @click="exportar" class="ml-auto inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">📥 Exportar Excel</button>
                </div>

                <template v-if="hayDatos">
                    <!-- Pestañas por empresa (sin desplegable) -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            @click="empresaActiva = 'todas'"
                            :class="empresaActiva === 'todas' ? 'bg-slate-900 text-white' : 'bg-white text-gray-600 ring-1 ring-inset ring-gray-200 hover:bg-gray-50'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        >🏢 Todas</button>
                        <button
                            v-for="e in porEmpresa"
                            :key="e.empresa"
                            @click="empresaActiva = e.empresa"
                            :class="empresaActiva === e.empresa ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-inset ring-gray-200 hover:bg-gray-50'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        >{{ e.empresa }}</button>
                    </div>

                    <!-- KPIs (de la pestaña activa) -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Costo total {{ empresaActiva === 'todas' ? '(todas)' : '' }}</p>
                            <p class="mt-1 text-2xl font-bold text-indigo-700">{{ money(resumen.costo_total) }}</p>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Neto a trabajadores</p>
                            <p class="mt-1 text-2xl font-bold text-green-700">{{ money(resumen.total_neto) }}</p>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Aportes del empleador</p>
                            <p class="mt-1 text-2xl font-bold text-blue-700">{{ money(resumen.total_aportes_empleador) }}</p>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Trabajadores</p>
                            <p class="mt-1 text-2xl font-bold text-gray-800">{{ resumen.cantidad_empleados }}</p>
                        </div>
                    </div>

                </template>

                <!-- Tabla detallada -->
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-3">Empresa</th>
                                <th class="px-3 py-3">RUC</th>
                                <th class="px-3 py-3 text-center">Trab.</th>
                                <th class="px-3 py-3 text-right">Ingresos</th>
                                <th class="px-3 py-3 text-right">Descuentos</th>
                                <th class="px-3 py-3 text-right">Neto</th>
                                <th class="px-3 py-3 text-right">EsSalud</th>
                                <th class="px-3 py-3 text-right">SCTR</th>
                                <th class="px-3 py-3 text-right">Vida Ley</th>
                                <th class="px-3 py-3 text-right">SENATI</th>
                                <th class="px-3 py-3 text-right">Aportes</th>
                                <th class="px-3 py-3 text-right font-bold">Costo total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 [&_td]:whitespace-nowrap [&_td]:tabular-nums">
                            <tr v-for="(e, i) in mostradas" :key="i" class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-medium text-gray-900">{{ e.empresa }}</td>
                                <td class="px-3 py-3 text-gray-600">{{ e.ruc || '—' }}</td>
                                <td class="px-3 py-3 text-center">{{ e.cantidad_empleados }}</td>
                                <td class="px-3 py-3 text-right">{{ money(e.total_ingresos) }}</td>
                                <td class="px-3 py-3 text-right text-red-600">{{ money(e.total_descuentos) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-green-700">{{ money(e.total_neto) }}</td>
                                <td class="px-3 py-3 text-right text-gray-600">{{ money(e.essalud) }}</td>
                                <td class="px-3 py-3 text-right text-gray-600">{{ money(e.sctr) }}</td>
                                <td class="px-3 py-3 text-right text-gray-600">{{ money(e.vida_ley) }}</td>
                                <td class="px-3 py-3 text-right text-gray-600">{{ money(e.senati) }}</td>
                                <td class="px-3 py-3 text-right text-blue-700">{{ money(e.total_aportes_empleador) }}</td>
                                <td class="px-3 py-3 text-right font-bold text-indigo-700">{{ money(e.costo_total) }}</td>
                            </tr>
                            <tr v-if="!hayDatos"><td colspan="12" class="px-4 py-6 text-center text-gray-500">No hay planillas generadas en este periodo.</td></tr>
                        </tbody>
                        <tfoot v-if="hayDatos && empresaActiva === 'todas'" class="bg-gray-100 font-bold [&_td]:whitespace-nowrap [&_td]:tabular-nums">
                            <tr>
                                <td class="px-3 py-3">TOTAL GENERAL</td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3 text-center">{{ totalGeneral.cantidad_empleados }}</td>
                                <td class="px-3 py-3 text-right">{{ money(totalGeneral.total_ingresos) }}</td>
                                <td class="px-3 py-3 text-right text-red-700">{{ money(totalGeneral.total_descuentos) }}</td>
                                <td class="px-3 py-3 text-right text-green-800">{{ money(totalGeneral.total_neto) }}</td>
                                <td class="px-3 py-3 text-right">{{ money(totalGeneral.essalud) }}</td>
                                <td class="px-3 py-3 text-right">{{ money(totalGeneral.sctr) }}</td>
                                <td class="px-3 py-3 text-right">{{ money(totalGeneral.vida_ley) }}</td>
                                <td class="px-3 py-3 text-right">{{ money(totalGeneral.senati) }}</td>
                                <td class="px-3 py-3 text-right text-blue-800">{{ money(totalGeneral.total_aportes_empleador) }}</td>
                                <td class="px-3 py-3 text-right text-indigo-800">{{ money(totalGeneral.costo_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
