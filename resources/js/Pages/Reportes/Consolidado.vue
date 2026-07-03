<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const meses = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
</script>

<template>
    <Head title="Consolidado" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Reporte consolidado (todas las empresas)</h2></template>
        <div class="p-6">
            <div class="space-y-6">
                <div class="rounded-lg bg-indigo-50 p-4 text-sm text-indigo-800">
                    Cada empresa se calcula de forma independiente (SUNAT/SUNAFIL audita por separado). Aquí solo se <b>suman los totales finales</b> de cada una.
                </div>

                <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div><label class="text-sm text-gray-700">Año</label><input v-model="anio" type="number" class="mt-1 block rounded-md border-gray-300 text-sm" /></div>
                    <div><label class="text-sm text-gray-700">Mes</label><select v-model="mes" class="mt-1 block rounded-md border-gray-300 text-sm"><option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option></select></div>
                    <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filtrar</button>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Empleados</th><th class="px-4 py-3">Ingresos</th><th class="px-4 py-3">Descuentos</th><th class="px-4 py-3">Neto</th><th class="px-4 py-3">Aportes empleador</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(e, i) in porEmpresa" :key="i">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ e.empresa }}</td>
                                <td class="px-4 py-3">{{ e.cantidad_empleados }}</td>
                                <td class="px-4 py-3">{{ money(e.total_ingresos) }}</td>
                                <td class="px-4 py-3 text-red-600">{{ money(e.total_descuentos) }}</td>
                                <td class="px-4 py-3 font-semibold text-green-700">{{ money(e.total_neto) }}</td>
                                <td class="px-4 py-3 text-blue-700">{{ money(e.total_aportes_empleador) }}</td>
                            </tr>
                            <tr v-if="porEmpresa.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay planillas generadas en este periodo.</td></tr>
                        </tbody>
                        <tfoot v-if="porEmpresa.length" class="bg-gray-100 font-bold">
                            <tr>
                                <td class="px-4 py-3">TOTAL GENERAL</td>
                                <td class="px-4 py-3">{{ totalGeneral.cantidad_empleados }}</td>
                                <td class="px-4 py-3">{{ money(totalGeneral.total_ingresos) }}</td>
                                <td class="px-4 py-3 text-red-700">{{ money(totalGeneral.total_descuentos) }}</td>
                                <td class="px-4 py-3 text-green-800">{{ money(totalGeneral.total_neto) }}</td>
                                <td class="px-4 py-3 text-blue-800">{{ money(totalGeneral.total_aportes_empleador) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
