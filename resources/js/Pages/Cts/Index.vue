<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    filas: { type: Array, default: () => [] },
    totales: { type: Object, default: () => ({}) },
    filtros: { type: Object, default: () => ({ anio: 2026, periodo: 'mayo', empresa_id: null }) },
});

const empresaId = ref(props.filtros.empresa_id);
const anio = ref(props.filtros.anio);
const periodo = ref(props.filtros.periodo);

function filtrar() {
    router.get(route('cts.index'), { empresa_id: empresaId.value, anio: anio.value, periodo: periodo.value }, { preserveState: true });
}

const genForm = useForm({ empresa_id: null, anio: null, periodo: null });
function generar() {
    if (!empresaId.value) { alert('Elige una empresa primero.'); return; }
    if (!confirm('¿Generar (o recalcular) la CTS de este periodo? Reemplaza el cálculo anterior.')) return;
    genForm.empresa_id = empresaId.value; genForm.anio = anio.value; genForm.periodo = periodo.value;
    genForm.post(route('cts.generar'), { preserveScroll: true });
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const empresaNombre = computed(() => props.empresas.find((e) => e.id === props.filtros.empresa_id)?.razon_social ?? '');
</script>

<template>
    <Head title="CTS" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">CTS — Compensación por Tiempo de Servicios</h2></template>

        <div class="p-6 space-y-6">
            <div class="rounded-lg bg-cyan-50 p-4 text-sm text-cyan-900">
                🏦 La <b>CTS</b> se deposita <b>2 veces al año</b> en la cuenta del trabajador: <b>Mayo</b> (semestre nov–abr) y <b>Noviembre</b> (semestre may–oct), dentro de los <b>primeros 15 días</b> del mes.
                Base = sueldo + asignación familiar + <b>1/6 de la gratificación</b>. No tiene descuentos.
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
                    <label class="text-sm text-gray-700">Depósito</label>
                    <select v-model="periodo" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="mayo">Mayo (nov–abr)</option>
                        <option value="noviembre">Noviembre (may–oct)</option>
                    </select>
                </div>
                <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Ver</button>
                <button @click="generar" :disabled="genForm.processing" class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50">⚡ Generar / Recalcular</button>
            </div>

            <div v-if="filtros.empresa_id && filas.length" class="grid grid-cols-2 gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Trabajadores</div><div class="text-2xl font-bold">{{ totales.empleados }}</div></div>
                <div class="rounded-lg bg-white p-4 shadow-sm md:col-span-2"><div class="text-xs uppercase text-gray-500">Total a depositar</div><div class="text-xl font-bold text-green-700">{{ money(totales.monto) }}</div></div>
            </div>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">DNI</th><th class="px-4 py-3">Trabajador</th>
                            <th class="px-4 py-3 text-center">Meses</th><th class="px-4 py-3 text-center">Días</th>
                            <th class="px-4 py-3 text-right">1/6 gratif.</th><th class="px-4 py-3 text-right">Rem. computable</th>
                            <th class="px-4 py-3 text-right">CTS a depositar</th>
                            <th class="px-4 py-3 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="f in filas" :key="f.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2">{{ f.dni }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ f.trabajador }}</td>
                            <td class="px-4 py-2 text-center">{{ f.meses }}</td>
                            <td class="px-4 py-2 text-center">{{ f.dias }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ money(f.sexto_gratificacion) }}</td>
                            <td class="px-4 py-2 text-right">{{ money(f.rem_computable) }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-green-700">{{ money(f.monto) }}</td>
                            <td class="px-4 py-2 text-right"><a :href="route('cts.pdf', f.id)" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200">📄 PDF</a></td>
                        </tr>
                        <tr v-if="!filtros.empresa_id"><td colspan="8" class="px-4 py-6 text-center text-gray-500">Elige una empresa y presiona “Ver”. Si aún no hay cálculo, usa “Generar”.</td></tr>
                        <tr v-else-if="filas.length === 0"><td colspan="8" class="px-4 py-6 text-center text-gray-500">No hay CTS generada para {{ empresaNombre }} en este periodo. Presiona “Generar”.</td></tr>
                    </tbody>
                    <tfoot v-if="filas.length" class="bg-gray-100 font-bold">
                        <tr><td class="px-4 py-3" colspan="6">TOTAL</td><td class="px-4 py-3 text-right text-green-800">{{ money(totales.monto) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
