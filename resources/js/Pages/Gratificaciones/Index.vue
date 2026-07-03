<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    filas: { type: Array, default: () => [] },
    totales: { type: Object, default: () => ({}) },
    filtros: { type: Object, default: () => ({ anio: 2026, tipo: 'julio', empresa_id: null }) },
});

const empresaId = ref(props.filtros.empresa_id);
const anio = ref(props.filtros.anio);
const tipo = ref(props.filtros.tipo);

function filtrar() {
    router.get(route('gratificaciones.index'), { empresa_id: empresaId.value, anio: anio.value, tipo: tipo.value }, { preserveState: true });
}

const genForm = useForm({ empresa_id: null, anio: null, tipo: null });
function generar() {
    if (!empresaId.value) { alert('Elige una empresa primero.'); return; }
    if (!confirm('¿Generar (o recalcular) la gratificación de este periodo? Reemplaza el cálculo anterior.')) return;
    genForm.empresa_id = empresaId.value;
    genForm.anio = anio.value;
    genForm.tipo = tipo.value;
    genForm.post(route('gratificaciones.generar'), { preserveScroll: true });
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const empresaNombre = computed(() => props.empresas.find((e) => e.id === props.filtros.empresa_id)?.razon_social ?? '');
</script>

<template>
    <Head title="Gratificaciones" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Gratificaciones (Fiestas Patrias / Navidad)</h2></template>

        <div class="p-6 space-y-6">
            <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-900">
                🎁 La gratificación es <b>obligatoria por ley</b>: <b>Julio</b> (semestre ene–jun, se paga hasta el 15 de julio) y <b>Diciembre</b> (semestre jul–dic).
                Equivale a <b>1 sueldo</b> si trabajó el semestre completo, <b>no tiene descuentos de AFP/ONP</b>, y se le suma la
                <b>bonificación extraordinaria del 9%</b> (Ley 30334).
            </div>

            <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <label class="text-sm text-gray-700">Empresa</label>
                    <select v-model="empresaId" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
                <div><label class="text-sm text-gray-700">Año</label><input v-model="anio" type="number" class="mt-1 block w-24 rounded-md border-gray-300 text-sm" /></div>
                <div>
                    <label class="text-sm text-gray-700">Periodo</label>
                    <select v-model="tipo" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="julio">Julio (Fiestas Patrias)</option>
                        <option value="diciembre">Diciembre (Navidad)</option>
                    </select>
                </div>
                <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Ver</button>
                <button @click="generar" :disabled="genForm.processing" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50">⚡ Generar / Recalcular</button>
            </div>

            <div v-if="filtros.empresa_id && filas.length" class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Trabajadores</div><div class="text-2xl font-bold">{{ totales.empleados }}</div></div>
                <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Gratificación</div><div class="text-xl font-bold text-gray-800">{{ money(totales.monto) }}</div></div>
                <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Bonif. 9%</div><div class="text-xl font-bold text-amber-700">{{ money(totales.bonif) }}</div></div>
                <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Total a pagar</div><div class="text-xl font-bold text-green-700">{{ money(totales.neto) }}</div></div>
            </div>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">DNI</th><th class="px-4 py-3">Trabajador</th>
                            <th class="px-4 py-3 text-center">Meses</th><th class="px-4 py-3 text-center">Días</th>
                            <th class="px-4 py-3 text-right">Rem. computable</th><th class="px-4 py-3 text-right">Gratificación</th>
                            <th class="px-4 py-3 text-right">Bonif. 9%</th><th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="f in filas" :key="f.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2">{{ f.dni }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ f.trabajador }}</td>
                            <td class="px-4 py-2 text-center">{{ f.meses }}</td>
                            <td class="px-4 py-2 text-center">{{ f.dias }}</td>
                            <td class="px-4 py-2 text-right">{{ money(f.rem_computable) }}</td>
                            <td class="px-4 py-2 text-right">{{ money(f.monto) }}</td>
                            <td class="px-4 py-2 text-right text-amber-700">{{ money(f.bonificacion_extraordinaria) }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-green-700">{{ money(f.neto) }}</td>
                            <td class="px-4 py-2 text-right"><a :href="route('gratificaciones.pdf', f.id)" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200">📄 PDF</a></td>
                        </tr>
                        <tr v-if="!filtros.empresa_id"><td colspan="9" class="px-4 py-6 text-center text-gray-500">Elige una empresa y presiona “Ver”. Si aún no hay cálculo, usa “Generar”.</td></tr>
                        <tr v-else-if="filas.length === 0"><td colspan="9" class="px-4 py-6 text-center text-gray-500">No hay gratificación generada para {{ empresaNombre }} en este periodo. Presiona “Generar”.</td></tr>
                    </tbody>
                    <tfoot v-if="filas.length" class="bg-gray-100 font-bold">
                        <tr>
                            <td class="px-4 py-3" colspan="5">TOTAL</td>
                            <td class="px-4 py-3 text-right">{{ money(totales.monto) }}</td>
                            <td class="px-4 py-3 text-right text-amber-800">{{ money(totales.bonif) }}</td>
                            <td class="px-4 py-3 text-right text-green-800">{{ money(totales.neto) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
