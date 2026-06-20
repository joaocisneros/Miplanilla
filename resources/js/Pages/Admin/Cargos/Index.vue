<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ cargos: { type: Array, default: () => [] } });

const editandoId = ref(null);
const form = useForm({ nombre: '', categoria: '', activo: true });

function nuevo() { editandoId.value = null; form.reset(); form.activo = true; }
function editar(c) { editandoId.value = c.id; form.nombre = c.nombre; form.categoria = c.categoria ?? ''; form.activo = !!c.activo; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => nuevo() };
    editandoId.value ? form.put(route('admin.cargos.update', editandoId.value), opts) : form.post(route('admin.cargos.store'), opts);
}
function eliminar(c) { if (confirm(`¿Eliminar el cargo "${c.nombre}"?`)) form.delete(route('admin.cargos.destroy', c.id), { preserveScroll: true }); }
</script>

<template>
    <Head title="Cargos" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Cargos</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium">{{ editandoId ? 'Editar' : 'Nuevo' }} cargo</h3>
                    <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div><label class="text-sm text-gray-700">Nombre</label><input v-model="form.nombre" type="text" placeholder="Ej. Ingeniero" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.nombre" class="text-sm text-red-600">{{ form.errors.nombre }}</p></div>
                        <div><label class="text-sm text-gray-700">Categoría</label><input v-model="form.categoria" type="text" placeholder="MAESTRO / OFICIAL / AYUDANTE" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div class="flex items-center gap-3 md:col-span-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                            <button v-if="editandoId" type="button" @click="nuevo" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold">Cancelar</button>
                        </div>
                    </form>
                </div>
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Categoría</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="c in cargos" :key="c.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ c.nombre }}</td>
                                <td class="px-4 py-3">{{ c.categoria ?? '—' }}</td>
                                <td class="px-4 py-3"><span :class="c.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs">{{ c.activo ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="px-4 py-3 text-right"><button @click="editar(c)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(c)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                            </tr>
                            <tr v-if="cargos.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin cargos todavía.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
