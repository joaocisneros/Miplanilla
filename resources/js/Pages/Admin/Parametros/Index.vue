<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ parametros: { type: Array, default: () => [] } });

const editandoId = ref(null);
const form = useForm({
    anio: new Date().getFullYear(),
    uit: '',
    rmv: '',
    asignacion_familiar: '',
    dias_base: 30,
    vigente_desde: '',
    vigente_hasta: '',
    confirmado: false,
    fuente: '',
});

function nuevo() {
    editandoId.value = null;
    form.reset();
    form.dias_base = 30;
}
function editar(p) {
    editandoId.value = p.id;
    Object.assign(form, {
        anio: p.anio, uit: p.uit ?? '', rmv: p.rmv, asignacion_familiar: p.asignacion_familiar,
        dias_base: p.dias_base, vigente_desde: p.vigente_desde?.substring(0, 10) ?? '',
        vigente_hasta: p.vigente_hasta?.substring(0, 10) ?? '', confirmado: !!p.confirmado, fuente: p.fuente ?? '',
    });
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => nuevo() };
    editandoId.value
        ? form.put(route('admin.parametros.update', editandoId.value), opts)
        : form.post(route('admin.parametros.store'), opts);
}
</script>

<template>
    <Head title="Parámetros del periodo" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Parámetros legales por periodo</h2>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    🌍 Estos valores son <b>nacionales (ley peruana)</b>: válidos para todas las empresas. Se versionan por año/vigencia.
                </div>

                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium">{{ editandoId ? 'Editar' : 'Nuevo' }} parámetro</h3>
                    <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div><label class="text-sm text-gray-700">Año</label><input v-model="form.anio" type="number" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.anio" class="text-sm text-red-600">{{ form.errors.anio }}</p></div>
                        <div><label class="text-sm text-gray-700">UIT</label><input v-model="form.uit" type="number" step="0.01" placeholder="por confirmar" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div><label class="text-sm text-gray-700">RMV</label><input v-model="form.rmv" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.rmv" class="text-sm text-red-600">{{ form.errors.rmv }}</p></div>
                        <div><label class="text-sm text-gray-700">Asignación familiar</label><input v-model="form.asignacion_familiar" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div><label class="text-sm text-gray-700">Días base</label><input v-model="form.dias_base" type="number" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div><label class="text-sm text-gray-700">Vigente desde</label><input v-model="form.vigente_desde" type="date" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.vigente_desde" class="text-sm text-red-600">{{ form.errors.vigente_desde }}</p></div>
                        <div><label class="text-sm text-gray-700">Vigente hasta</label><input v-model="form.vigente_hasta" type="date" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div class="md:col-span-2"><label class="text-sm text-gray-700">Fuente</label><input v-model="form.fuente" type="text" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div class="flex items-center gap-2"><input v-model="form.confirmado" type="checkbox" id="conf" class="rounded" /><label for="conf" class="text-sm">Confirmado por contador</label></div>
                        <div class="flex items-center gap-3 md:col-span-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                            <button v-if="editandoId" type="button" @click="nuevo" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold">Cancelar</button>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Año</th><th class="px-4 py-3">UIT</th><th class="px-4 py-3">RMV</th><th class="px-4 py-3">Asig. fam.</th><th class="px-4 py-3">Días</th><th class="px-4 py-3">Vigencia</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acción</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in parametros" :key="p.id">
                                <td class="px-4 py-3 font-medium">{{ p.anio }}</td>
                                <td class="px-4 py-3">{{ p.uit ?? '—' }}</td>
                                <td class="px-4 py-3">{{ p.rmv }}</td>
                                <td class="px-4 py-3">{{ p.asignacion_familiar }}</td>
                                <td class="px-4 py-3">{{ p.dias_base }}</td>
                                <td class="px-4 py-3">{{ p.vigente_desde?.substring(0,10) }}</td>
                                <td class="px-4 py-3"><span :class="p.confirmado ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="rounded-full px-2 py-1 text-xs">{{ p.confirmado ? 'Confirmado' : 'Por confirmar' }}</span></td>
                                <td class="px-4 py-3 text-right"><button @click="editar(p)" class="text-indigo-600 hover:text-indigo-900">Editar</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
