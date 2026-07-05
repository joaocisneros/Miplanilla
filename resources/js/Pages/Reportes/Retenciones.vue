<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    filas: { type: Array, default: () => [] },
    totalesMes: { type: Object, default: () => ({}) },
    totalAnio: { type: Number, default: 0 },
    filtros: { type: Object, default: () => ({ anio: 2026, empresa_id: null }) },
    empresas: { type: Array, default: () => [] },
});

const anio = ref(props.filtros.anio);
const empresaId = ref(props.filtros.empresa_id ?? '');

function filtrar() {
    router.get(route('reportes.retenciones'), { anio: anio.value, empresa_id: empresaId.value || undefined }, { preserveState: true });
}
function exportar() {
    window.location.href = route('reportes.retenciones.export', { anio: anio.value, empresa_id: empresaId.value || undefined });
}

const money = (v) => (Number(v ?? 0) === 0 ? '—' : Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }));
const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
const anios = Array.from({ length: 6 }, (_, i) => 2026 + i);

const hayDatos = computed(() => props.filas.length > 0);
const conRetencion = computed(() => props.filas.filter((f) => f.total > 0).length);
</script>

<template>
    <Head title="Retenciones 5ta" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Retenciones de 5ta categoría (mes a mes)</h2></template>
        <div class="p-6">
            <div class="space-y-4">
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    Muestra el impuesto a la renta (5ta categoría) retenido a cada trabajador, mes por mes. Solo pagan los que ganan más de ~S/ 3,200 al mes; a los demás les sale <b>S/ 0 (—)</b>.
                </div>

                <!-- Filtros + Exportar -->
                <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div><label class="text-sm text-gray-700">Año</label><select v-model="anio" class="mt-1 block rounded-md border-gray-300 text-sm"><option v-for="a in anios" :key="a" :value="a">{{ a }}</option></select></div>
                    <div><label class="text-sm text-gray-700">Empresa</label><select v-model="empresaId" class="mt-1 block rounded-md border-gray-300 text-sm"><option value="">Todas</option><option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option></select></div>
                    <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filtrar</button>
                    <button v-if="hayDatos" @click="exportar" class="ml-auto inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">📥 Exportar Excel</button>
                </div>

                <div v-if="hayDatos" class="rounded-lg bg-white p-4 text-sm shadow-sm">
                    <b>{{ conRetencion }}</b> de {{ filas.length }} trabajadores pagan retención de 5ta en {{ anio }}.
                    Total retenido en el año: <b class="text-indigo-700">S/ {{ Number(totalAnio).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}</b>.
                </div>

                <!-- Tabla persona × mes -->
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="sticky left-0 bg-gray-50 px-3 py-3">Trabajador</th>
                                <th v-for="m in meses" :key="m" class="px-3 py-3 text-right">{{ m }}</th>
                                <th class="px-3 py-3 text-right font-bold">Total año</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 [&_td]:whitespace-nowrap [&_td]:tabular-nums">
                            <tr v-for="(f, i) in filas" :key="i" :class="f.total > 0 ? 'hover:bg-amber-50' : 'text-gray-400 hover:bg-gray-50'">
                                <td class="sticky left-0 bg-white px-3 py-2 font-medium text-gray-900">
                                    {{ f.nombre }}
                                    <span class="block text-xs font-normal text-gray-400">{{ f.dni }} · {{ f.empresa }}</span>
                                </td>
                                <td v-for="m in 12" :key="m" class="px-3 py-2 text-right">{{ money(f.meses[m]) }}</td>
                                <td class="px-3 py-2 text-right font-bold" :class="f.total > 0 ? 'text-indigo-700' : 'text-gray-300'">{{ money(f.total) }}</td>
                            </tr>
                            <tr v-if="!hayDatos"><td :colspan="14" class="px-4 py-6 text-center text-gray-500">No hay planillas generadas en este año.</td></tr>
                        </tbody>
                        <tfoot v-if="hayDatos" class="bg-gray-100 font-bold [&_td]:whitespace-nowrap [&_td]:tabular-nums">
                            <tr>
                                <td class="sticky left-0 bg-gray-100 px-3 py-3">TOTAL</td>
                                <td v-for="m in 12" :key="m" class="px-3 py-3 text-right">{{ money(totalesMes[m]) }}</td>
                                <td class="px-3 py-3 text-right text-indigo-800">{{ money(totalAnio) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
