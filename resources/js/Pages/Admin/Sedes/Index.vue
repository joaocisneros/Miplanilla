<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sedes: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null }) },
    empresas: { type: Array, default: () => [] },
});

const fEmpresa = ref(props.filtros.empresa_id ?? '');
function filtrar() {
    router.get(route('admin.sedes.index'), { empresa_id: fEmpresa.value || undefined }, { preserveState: true, preserveScroll: true });
}

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ empresa_id: '', nombre: '', direccion: '', activo: true });

function abrirNuevo() { editandoId.value = null; form.reset(); form.empresa_id = fEmpresa.value || ''; form.activo = true; form.clearErrors(); mostrar.value = true; }
function abrirEditar(s) { editandoId.value = s.id; form.empresa_id = s.empresa_id; form.nombre = s.nombre; form.direccion = s.direccion ?? ''; form.activo = !!s.activo; form.clearErrors(); mostrar.value = true; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.sedes.update', editandoId.value), opts) : form.post(route('admin.sedes.store'), opts);
}
function eliminar(s) { if (confirm(`¿Eliminar la sede "${s.nombre}"?`)) form.delete(route('admin.sedes.destroy', s.id), { preserveScroll: true }); }
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
const selectCls = 'rounded-md border-gray-300 py-1.5 text-sm';
</script>

<template>
    <Head title="Sedes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Sedes</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nueva sede</button>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                <div>
                    <label class="block text-xs uppercase text-gray-500">Empresa</label>
                    <select v-model="fEmpresa" @change="filtrar" :class="selectCls">
                        <option value="">Todas las empresas</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                    </select>
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Dirección</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="s in sedes" :key="s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3"><span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{{ s.empresa ?? '—' }}</span></td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ s.nombre }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ s.direccion ?? '—' }}</td>
                            <td class="px-4 py-3"><span :class="s.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs">{{ s.activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td class="px-4 py-3 text-right"><button @click="abrirEditar(s)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(s)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                        </tr>
                        <tr v-if="sedes.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay sedes con ese filtro.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar sede' : 'Nueva sede'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4">
                <div><label class="text-sm font-semibold text-gray-700">Empresa *</label><select v-model="form.empresa_id" :class="inp"><option value="">— Selecciona —</option><option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option></select><p v-if="form.errors.empresa_id" class="text-xs text-red-600">{{ form.errors.empresa_id }}</p></div>
                <div><label class="text-sm text-gray-700">Nombre</label><input v-model="form.nombre" placeholder="Ej. Planta Cajamarquilla" :class="inp" /><p v-if="form.errors.nombre" class="text-xs text-red-600">{{ form.errors.nombre }}</p></div>
                <div><label class="text-sm text-gray-700">Dirección</label><input v-model="form.direccion" :class="inp" /></div>
                <div class="flex items-center gap-2"><input v-model="form.activo" type="checkbox" id="acts" class="rounded" /><label for="acts" class="text-sm">Activa</label></div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
