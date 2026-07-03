<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import BotonAccion from '@/Components/BotonAccion.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ cargos: { type: Array, default: () => [] } });

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ nombre: '', categoria: '', activo: true });

function abrirNuevo() { editandoId.value = null; form.reset(); form.activo = true; form.clearErrors(); mostrar.value = true; }
function abrirEditar(c) { editandoId.value = c.id; form.nombre = c.nombre; form.categoria = c.categoria ?? ''; form.activo = !!c.activo; form.clearErrors(); mostrar.value = true; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.cargos.update', editandoId.value), opts) : form.post(route('admin.cargos.store'), opts);
}
function eliminar(c) { if (confirm(`¿Eliminar el cargo "${c.nombre}"?`)) form.delete(route('admin.cargos.destroy', c.id), { preserveScroll: true }); }
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Cargos" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Cargos</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo cargo</button>
            </div>
        </template>

        <div class="p-6">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Categoría</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="c in cargos" :key="c.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ c.nombre }}</td>
                            <td class="px-4 py-3">{{ c.categoria ?? '—' }}</td>
                            <td class="px-4 py-3"><span :class="c.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs">{{ c.activo ? 'Activo' : 'Inactivo' }}</span></td>
                            <td class="px-4 py-3 text-right"><div class="inline-flex gap-2"><BotonAccion variante="editar" @click="abrirEditar(c)" /><BotonAccion variante="eliminar" @click="eliminar(c)" /></div></td>
                        </tr>
                        <tr v-if="cargos.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin cargos todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar cargo' : 'Nuevo cargo'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4">
                <div><label class="text-sm text-gray-700">Nombre</label><input v-model="form.nombre" placeholder="Ej. Ingeniero" :class="inp" /><p v-if="form.errors.nombre" class="text-xs text-red-600">{{ form.errors.nombre }}</p></div>
                <div><label class="text-sm text-gray-700">Categoría</label><input v-model="form.categoria" placeholder="MAESTRO / OFICIAL / AYUDANTE" :class="inp" /></div>
                <div class="flex items-center gap-2"><input v-model="form.activo" type="checkbox" id="ac" class="rounded" /><label for="ac" class="text-sm">Activo</label></div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
