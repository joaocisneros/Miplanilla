<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ turnos: { type: Array, default: () => [] } });

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ nombre: '', hora_entrada: '08:00', hora_salida: '18:00', refrigerio_min: 60, tolerancia_min: 5, cruza_medianoche: false, activo: true });

function abrirNuevo() { editandoId.value = null; form.reset(); form.hora_entrada = '08:00'; form.hora_salida = '18:00'; form.refrigerio_min = 60; form.tolerancia_min = 5; form.activo = true; form.clearErrors(); mostrar.value = true; }
function abrirEditar(t) {
    editandoId.value = t.id;
    Object.assign(form, { nombre: t.nombre, hora_entrada: t.hora_entrada?.substring(0,5), hora_salida: t.hora_salida?.substring(0,5), refrigerio_min: t.refrigerio_min, tolerancia_min: t.tolerancia_min, cruza_medianoche: !!t.cruza_medianoche, activo: !!t.activo });
    form.clearErrors(); mostrar.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.turnos.update', editandoId.value), opts) : form.post(route('admin.turnos.store'), opts);
}
function eliminar(t) { if (confirm(`¿Eliminar el turno "${t.nombre}"?`)) form.delete(route('admin.turnos.destroy', t.id), { preserveScroll: true }); }
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Turnos" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Turnos</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo turno</button>
            </div>
        </template>

        <div class="p-6">
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Entrada</th><th class="px-4 py-3">Salida</th><th class="px-4 py-3">Refrig.</th><th class="px-4 py-3">Tolerancia</th><th class="px-4 py-3">Nocturno</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="t in turnos" :key="t.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ t.nombre }}</td>
                            <td class="px-4 py-3">{{ t.hora_entrada?.substring(0,5) }}</td>
                            <td class="px-4 py-3">{{ t.hora_salida?.substring(0,5) }}</td>
                            <td class="px-4 py-3">{{ t.refrigerio_min }} min</td>
                            <td class="px-4 py-3">{{ t.tolerancia_min }} min</td>
                            <td class="px-4 py-3">{{ t.cruza_medianoche ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right"><button @click="abrirEditar(t)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(t)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                        </tr>
                        <tr v-if="turnos.length === 0"><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin turnos todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar turno' : 'Nuevo turno'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2"><label class="text-sm text-gray-700">Nombre</label><input v-model="form.nombre" placeholder="Ej. General" :class="inp" /><p v-if="form.errors.nombre" class="text-xs text-red-600">{{ form.errors.nombre }}</p></div>
                <div><label class="text-sm text-gray-700">Hora entrada</label><input v-model="form.hora_entrada" type="time" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Hora salida</label><input v-model="form.hora_salida" type="time" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Refrigerio (min)</label><input v-model="form.refrigerio_min" type="number" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Tolerancia (min)</label><input v-model="form.tolerancia_min" type="number" :class="inp" /></div>
                <div class="flex items-center gap-2 md:col-span-2"><input v-model="form.cruza_medianoche" type="checkbox" id="cm" class="rounded" /><label for="cm" class="text-sm">Turno nocturno (cruza medianoche)</label></div>
                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
