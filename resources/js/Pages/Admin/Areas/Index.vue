<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ areas: { type: Array, default: () => [] } });

const editandoId = ref(null);
const form = useForm({ nombre: '', es_riesgo: false, activo: true });

function nuevo() { editandoId.value = null; form.reset(); form.activo = true; }
function editar(a) { editandoId.value = a.id; form.nombre = a.nombre; form.es_riesgo = !!a.es_riesgo; form.activo = !!a.activo; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => nuevo() };
    editandoId.value ? form.put(route('admin.areas.update', editandoId.value), opts) : form.post(route('admin.areas.store'), opts);
}
function eliminar(a) { if (confirm(`¿Eliminar el área "${a.nombre}"?`)) form.delete(route('admin.areas.destroy', a.id), { preserveScroll: true }); }
</script>

<template>
    <Head title="Áreas" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Áreas (de la empresa activa)</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium">{{ editandoId ? 'Editar' : 'Nueva' }} área</h3>
                    <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="md:col-span-2"><label class="text-sm text-gray-700">Nombre</label><input v-model="form.nombre" type="text" placeholder="Ej. Producción" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.nombre" class="text-sm text-red-600">{{ form.errors.nombre }}</p></div>
                        <div class="flex items-center gap-2 pt-6"><input v-model="form.es_riesgo" type="checkbox" id="r" class="rounded" /><label for="r" class="text-sm">Actividad de riesgo (SCTR)</label></div>
                        <div class="flex items-center gap-3 md:col-span-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                            <button v-if="editandoId" type="button" @click="nuevo" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold">Cancelar</button>
                        </div>
                    </form>
                </div>
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Riesgo</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="a in areas" :key="a.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ a.nombre }}</td>
                                <td class="px-4 py-3">{{ a.es_riesgo ? 'Sí' : 'No' }}</td>
                                <td class="px-4 py-3"><span :class="a.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs">{{ a.activo ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="px-4 py-3 text-right"><button @click="editar(a)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(a)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                            </tr>
                            <tr v-if="areas.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin áreas todavía.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
