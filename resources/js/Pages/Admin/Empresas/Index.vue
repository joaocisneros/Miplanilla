<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ empresas: { type: Array, default: () => [] } });

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ ruc: '', razon_social: '', nombre_comercial: '', direccion: '', activo: true });

function abrirNuevo() { editandoId.value = null; form.reset(); form.activo = true; form.clearErrors(); mostrar.value = true; }
function abrirEditar(e) {
    editandoId.value = e.id;
    form.ruc = e.ruc; form.razon_social = e.razon_social; form.nombre_comercial = e.nombre_comercial ?? '';
    form.direccion = e.direccion ?? ''; form.activo = !!e.activo; form.clearErrors(); mostrar.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.empresas.update', editandoId.value), opts) : form.post(route('admin.empresas.store'), opts);
}
function eliminar(e) {
    if (confirm(`¿Eliminar la empresa "${e.razon_social}"?`)) form.delete(route('admin.empresas.destroy', e.id), { preserveScroll: true });
}
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Empresas" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Empresas</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nueva empresa</button>
            </div>
        </template>

        <div class="p-6">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="px-4 py-3">RUC</th><th class="px-4 py-3">Razón social</th><th class="px-4 py-3">Nombre comercial</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="e in empresas" :key="e.id">
                            <td class="px-4 py-3 text-gray-700">{{ e.ruc }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ e.razon_social }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ e.nombre_comercial ?? '—' }}</td>
                            <td class="px-4 py-3"><span :class="e.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs font-medium">{{ e.activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <button @click="abrirEditar(e)" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button @click="eliminar(e)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="empresas.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay empresas registradas todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar empresa' : 'Nueva empresa'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><label class="text-sm text-gray-700">RUC</label><input v-model="form.ruc" maxlength="11" :class="inp" /><p v-if="form.errors.ruc" class="text-xs text-red-600">{{ form.errors.ruc }}</p></div>
                <div><label class="text-sm text-gray-700">Razón social</label><input v-model="form.razon_social" :class="inp" /><p v-if="form.errors.razon_social" class="text-xs text-red-600">{{ form.errors.razon_social }}</p></div>
                <div><label class="text-sm text-gray-700">Nombre comercial</label><input v-model="form.nombre_comercial" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Dirección</label><input v-model="form.direccion" :class="inp" /></div>
                <div class="flex items-center gap-2"><input v-model="form.activo" type="checkbox" id="act" class="rounded" /><label for="act" class="text-sm">Activa</label></div>
                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
