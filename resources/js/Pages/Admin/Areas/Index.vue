<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ areas: { type: Array, default: () => [] } });

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ nombre: '', es_riesgo: false, activo: true });

function abrirNuevo() { editandoId.value = null; form.reset(); form.activo = true; form.clearErrors(); mostrar.value = true; }
function abrirEditar(a) { editandoId.value = a.id; form.nombre = a.nombre; form.es_riesgo = !!a.es_riesgo; form.activo = !!a.activo; form.clearErrors(); mostrar.value = true; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.areas.update', editandoId.value), opts) : form.post(route('admin.areas.store'), opts);
}
function eliminar(a) { if (confirm(`¿Eliminar el área "${a.nombre}"?`)) form.delete(route('admin.areas.destroy', a.id), { preserveScroll: true }); }
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Áreas" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Áreas (empresa activa)</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nueva área</button>
            </div>
        </template>

        <div class="p-6">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Riesgo</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="a in areas" :key="a.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ a.nombre }}</td>
                            <td class="px-4 py-3">{{ a.es_riesgo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3"><span :class="a.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs">{{ a.activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td class="px-4 py-3 text-right"><button @click="abrirEditar(a)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(a)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                        </tr>
                        <tr v-if="areas.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin áreas todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar área' : 'Nueva área'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4">
                <div><label class="text-sm text-gray-700">Nombre</label><input v-model="form.nombre" placeholder="Ej. Producción" :class="inp" /><p v-if="form.errors.nombre" class="text-xs text-red-600">{{ form.errors.nombre }}</p></div>
                <div class="flex items-center gap-2"><input v-model="form.es_riesgo" type="checkbox" id="r" class="rounded" /><label for="r" class="text-sm">Actividad de riesgo (SCTR)</label></div>
                <div class="flex items-center gap-2"><input v-model="form.activo" type="checkbox" id="aa" class="rounded" /><label for="aa" class="text-sm">Activa</label></div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
