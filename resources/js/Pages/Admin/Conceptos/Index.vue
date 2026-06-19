<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ conceptos: { type: Array, default: () => [] } });

// Copia editable local por fila
const filas = reactive(props.conceptos.map((c) => ({ ...c })));

const flags = [
    ['es_remunerativo', 'Remun.'],
    ['afecto_afp_onp', 'AFP/ONP'],
    ['afecto_essalud', 'EsSalud'],
    ['afecto_sctr', 'SCTR'],
    ['afecto_5ta', '5ta'],
    ['afecta_descuento_inasistencia', 'Desc. falta'],
    ['evalua_regularidad', 'Regularidad'],
    ['activo', 'Activo'],
];

function guardar(fila) {
    router.put(route('admin.conceptos.update', fila.id), {
        nombre: fila.nombre,
        es_remunerativo: fila.es_remunerativo,
        afecto_afp_onp: fila.afecto_afp_onp,
        afecto_essalud: fila.afecto_essalud,
        afecto_sctr: fila.afecto_sctr,
        afecto_5ta: fila.afecto_5ta,
        afecta_descuento_inasistencia: fila.afecta_descuento_inasistencia,
        evalua_regularidad: fila.evalua_regularidad,
        activo: fila.activo,
    }, { preserveScroll: true });
}

const colorTipo = (t) => ({
    ingreso: 'bg-green-100 text-green-800',
    descuento: 'bg-red-100 text-red-800',
    aporte_empleador: 'bg-blue-100 text-blue-800',
}[t] ?? 'bg-gray-100');
</script>

<template>
    <Head title="Conceptos de planilla" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Conceptos de planilla</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-indigo-50 p-4 text-sm text-indigo-800">
                    Cada concepto define su <b>naturaleza remunerativa</b>. El motor arma cada base (AFP/ONP, EsSalud, SCTR, 5ta) sumando solo los conceptos marcados como afectos.
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-3 text-left">Concepto</th>
                                <th class="px-3 py-3 text-left">Tipo</th>
                                <th v-for="[, label] in flags" :key="label" class="px-2 py-3 text-center">{{ label }}</th>
                                <th class="px-3 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="fila in filas" :key="fila.id">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900">{{ fila.nombre }}</div>
                                    <div class="text-xs text-gray-400">{{ fila.codigo }}</div>
                                </td>
                                <td class="px-3 py-2"><span :class="colorTipo(fila.tipo)" class="rounded-full px-2 py-1 text-xs">{{ fila.tipo }}</span></td>
                                <td v-for="[key] in flags" :key="key" class="px-2 py-2 text-center">
                                    <input type="checkbox" v-model="fila[key]" class="rounded border-gray-300" />
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button @click="guardar(fila)" class="rounded bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700">Guardar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
