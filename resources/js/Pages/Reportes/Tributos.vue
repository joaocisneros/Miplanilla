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
    router.get(route('reportes.tributos'), { anio: anio.value, mes: mes.value }, { preserveState: true });
}

function descargarPlame(empresaId) {
    const url = route('reportes.plame', { empresa_id: empresaId, anio: anio.value, mes: mes.value });
    window.open(url, '_blank');
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const periodoLabel = computed(() => `${meses[props.filtros.mes]} ${props.filtros.anio}`);
</script>

<template>
    <Head title="Tributos SUNAT" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Tributos y aportes — {{ periodoLabel }}</h2></template>

        <div class="p-6 space-y-6">
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
                Resumen de <b>cuánto declarar y pagar</b> por empresa y mes. El sistema calcula los montos exactos;
                la <b>declaración</b> se presenta en las plataformas oficiales: <b>PLAME</b> (SUNAT, con tu Clave SOL),
                <b>AFPnet</b> (AFP) y el pago de pólizas (SCTR / Vida Ley). Usa <b>“Descargar PLAME”</b> para llevar el
                detalle por trabajador a SUNAT.
            </div>

            <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                <div><label class="text-sm text-gray-700">Año</label><input v-model="anio" type="number" class="mt-1 block rounded-md border-gray-300 text-sm" /></div>
                <div><label class="text-sm text-gray-700">Mes</label><select v-model="mes" class="mt-1 block rounded-md border-gray-300 text-sm"><option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option></select></div>
                <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filtrar</button>
            </div>

            <div v-if="porEmpresa.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                No hay planillas generadas en {{ periodoLabel }}. Genera la planilla del periodo para ver sus tributos.
            </div>

            <!-- Una tarjeta por empresa -->
            <div v-for="e in porEmpresa" :key="e.empresa_id" class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ e.empresa }}</h3>
                        <p class="text-xs text-gray-500">RUC {{ e.ruc || '—' }} · {{ e.empleados }} trabajadores ({{ e.empleados_onp }} ONP / {{ e.empleados_afp }} AFP) · Base imponible {{ money(e.base_imponible) }}</p>
                    </div>
                    <button @click="descargarPlame(e.empresa_id)" class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">⬇ Descargar PLAME (Excel)</button>
                </div>

                <div class="grid gap-4 p-5 lg:grid-cols-3">
                    <!-- SUNAT -->
                    <div class="rounded-lg border border-indigo-200 bg-indigo-50/40">
                        <div class="border-b border-indigo-100 px-4 py-2 text-sm font-semibold text-indigo-900">🏛️ A SUNAT (PLAME)</div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-indigo-100">
                                <tr><td class="px-4 py-2 text-gray-600">EsSalud (9%)</td><td class="px-4 py-2 text-right font-medium">{{ money(e.essalud) }}</td></tr>
                                <tr><td class="px-4 py-2 text-gray-600">ONP (retención 13%)</td><td class="px-4 py-2 text-right font-medium">{{ money(e.onp) }}</td></tr>
                                <tr><td class="px-4 py-2 text-gray-600">Renta 5ta (retención)</td><td class="px-4 py-2 text-right font-medium">{{ money(e.renta_5ta) }}</td></tr>
                                <tr v-if="e.senati > 0"><td class="px-4 py-2 text-gray-600">SENATI</td><td class="px-4 py-2 text-right font-medium">{{ money(e.senati) }}</td></tr>
                            </tbody>
                            <tfoot><tr class="bg-indigo-100 font-bold text-indigo-900"><td class="px-4 py-2">Total SUNAT</td><td class="px-4 py-2 text-right">{{ money(e.total_sunat) }}</td></tr></tfoot>
                        </table>
                    </div>

                    <!-- AFP -->
                    <div class="rounded-lg border border-purple-200 bg-purple-50/40">
                        <div class="border-b border-purple-100 px-4 py-2 text-sm font-semibold text-purple-900">📈 A las AFP (AFPnet)</div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-purple-100">
                                <tr v-for="a in e.afp_detalle" :key="a.afp">
                                    <td class="px-4 py-2 text-gray-600">
                                        {{ a.afp }} <span class="text-xs text-gray-400">({{ a.empleados }})</span>
                                        <div class="text-xs text-gray-400">Aporte {{ money(a.aporte) }} · Com. {{ money(a.comision) }} · Prima {{ money(a.prima) }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium align-top">{{ money(a.total) }}</td>
                                </tr>
                                <tr v-if="e.afp_detalle.length === 0"><td class="px-4 py-3 text-center text-gray-400" colspan="2">Sin trabajadores en AFP</td></tr>
                            </tbody>
                            <tfoot><tr class="bg-purple-100 font-bold text-purple-900"><td class="px-4 py-2">Total AFPnet</td><td class="px-4 py-2 text-right">{{ money(e.afp_total) }}</td></tr></tfoot>
                        </table>
                    </div>

                    <!-- Seguros -->
                    <div class="rounded-lg border border-teal-200 bg-teal-50/40">
                        <div class="border-b border-teal-100 px-4 py-2 text-sm font-semibold text-teal-900">🛡️ Seguros (póliza)</div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-teal-100">
                                <tr><td class="px-4 py-2 text-gray-600">SCTR Pensión</td><td class="px-4 py-2 text-right font-medium">{{ money(e.sctr_pension) }}</td></tr>
                                <tr><td class="px-4 py-2 text-gray-600">SCTR Salud</td><td class="px-4 py-2 text-right font-medium">{{ money(e.sctr_salud) }}</td></tr>
                                <tr><td class="px-4 py-2 text-gray-600">Vida Ley</td><td class="px-4 py-2 text-right font-medium">{{ money(e.vida_ley) }}</td></tr>
                            </tbody>
                            <tfoot><tr class="bg-teal-100 font-bold text-teal-900"><td class="px-4 py-2">Total seguros</td><td class="px-4 py-2 text-right">{{ money(e.total_seguros) }}</td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Total general -->
            <div v-if="porEmpresa.length" class="rounded-lg bg-gray-900 p-5 text-white">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-400">Total general (las {{ porEmpresa.length }} empresas)</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><p class="text-xs text-gray-400">A SUNAT (PLAME)</p><p class="text-xl font-bold text-indigo-300">{{ money(totalGeneral.total_sunat) }}</p></div>
                    <div><p class="text-xs text-gray-400">A las AFP (AFPnet)</p><p class="text-xl font-bold text-purple-300">{{ money(totalGeneral.afp_total) }}</p></div>
                    <div><p class="text-xs text-gray-400">Seguros (SCTR + Vida Ley)</p><p class="text-xl font-bold text-teal-300">{{ money(totalGeneral.total_seguros) }}</p></div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
