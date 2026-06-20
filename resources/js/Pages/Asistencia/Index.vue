<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    registros: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ desde: '', hasta: '' }) },
});

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeImportar = computed(() => permisos.value.includes('asistencia.sincronizar'));

const desde = ref(props.filtros.desde);
const hasta = ref(props.filtros.hasta);

const importForm = useForm({ archivo: null });

function filtrar() {
    router.get(route('asistencia.index'), { desde: desde.value, hasta: hasta.value }, { preserveState: true });
}
function importar() {
    importForm.post(route('asistencia.import'), {
        preserveScroll: true,
        onSuccess: () => importForm.reset('archivo'),
    });
}

const colorEstado = (e) => {
    if (e === 'NORMAL') return 'bg-green-100 text-green-800';
    if (e?.startsWith('FALTA')) return 'bg-red-100 text-red-800';
    if (['VACACIONES','LICENCIA','DESCANSO_MEDICO','SUBSIDIO'].includes(e)) return 'bg-blue-100 text-blue-800';
    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="Asistencia" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Asistencia</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- Importación -->
                <div v-if="puedeImportar" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-2 text-lg font-medium text-gray-900">Importar asistencia (Excel / CSV)</h3>
                    <p class="mb-4 text-sm text-gray-500">
                        Columnas esperadas: <code>dni, fecha, estado, minutos_tarde, horas_extra, hora_entrada, hora_salida</code>.
                        <a :href="route('asistencia.plantilla')" class="ml-1 text-indigo-600 underline">Descargar plantilla</a>
                    </p>
                    <form @submit.prevent="importar" class="flex flex-wrap items-center gap-3">
                        <input type="file" accept=".xlsx,.xls,.csv,.txt"
                            @input="importForm.archivo = $event.target.files[0]"
                            class="text-sm" />
                        <button type="submit" :disabled="importForm.processing || !importForm.archivo"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                            Importar
                        </button>
                        <span v-if="importForm.progress" class="text-sm text-gray-500">{{ importForm.progress.percentage }}%</span>
                    </form>
                    <p v-if="importForm.errors.archivo" class="mt-2 text-sm text-red-600">{{ importForm.errors.archivo }}</p>
                </div>

                <!-- Filtro -->
                <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div><label class="text-sm text-gray-700">Desde</label><input v-model="desde" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" /></div>
                    <div><label class="text-sm text-gray-700">Hasta</label><input v-model="hasta" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" /></div>
                    <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filtrar</button>
                </div>

                <!-- Listado -->
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Trabajador</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Tardanza</th><th class="px-4 py-3">H. Extra</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="r in registros" :key="r.id">
                                <td class="px-4 py-2">{{ r.fecha }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ r.empleado }}</td>
                                <td class="px-4 py-2"><span :class="colorEstado(r.estado)" class="rounded-full px-2 py-1 text-xs">{{ r.estado }}</span></td>
                                <td class="px-4 py-2">{{ r.minutos_tarde }} min</td>
                                <td class="px-4 py-2">{{ r.horas_extra }}</td>
                            </tr>
                            <tr v-if="registros.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin registros en el rango. Importa un Excel para empezar.</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
