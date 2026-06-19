<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    empresa: { type: Object, default: null },
    sedes: { type: Array, default: () => [] },
});

const editandoId = ref(null);
const form = useForm({ nombre: '', direccion: '', activo: true });

function nuevo() { editandoId.value = null; form.reset(); form.activo = true; }
function editar(s) { editandoId.value = s.id; form.nombre = s.nombre; form.direccion = s.direccion ?? ''; form.activo = !!s.activo; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => nuevo() };
    editandoId.value ? form.put(route('admin.sedes.update', editandoId.value), opts) : form.post(route('admin.sedes.store'), opts);
}
function eliminar(s) { if (confirm(`¿Eliminar la sede "${s.nombre}"?`)) form.delete(route('admin.sedes.destroy', s.id), { preserveScroll: true }); }
</script>

<template>
    <Head title="Sedes" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Sedes <span v-if="empresa" class="text-gray-400">— {{ empresa.razon_social }}</span>
            </h2>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="!empresa" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    Selecciona una empresa activa en la barra superior para gestionar sus sedes.
                </div>

                <template v-else>
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-4 text-lg font-medium">{{ editandoId ? 'Editar sede' : 'Nueva sede' }}</h3>
                        <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm text-gray-700">Nombre</label>
                                <input v-model="form.nombre" type="text" placeholder="Ej. Planta Cajamarquilla" class="mt-1 block w-full rounded-md border-gray-300" />
                                <p v-if="form.errors.nombre" class="text-sm text-red-600">{{ form.errors.nombre }}</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-700">Dirección</label>
                                <input v-model="form.direccion" type="text" class="mt-1 block w-full rounded-md border-gray-300" />
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="form.activo" type="checkbox" id="act" class="rounded" />
                                <label for="act" class="text-sm">Activa</label>
                            </div>
                            <div class="flex items-center gap-3 md:col-span-2">
                                <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                                <button v-if="editandoId" type="button" @click="nuevo" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold">Cancelar</button>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Dirección</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="s in sedes" :key="s.id">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ s.nombre }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ s.direccion ?? '—' }}</td>
                                    <td class="px-4 py-3"><span :class="s.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs">{{ s.activo ? 'Activa' : 'Inactiva' }}</span></td>
                                    <td class="px-4 py-3 text-right"><button @click="editar(s)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(s)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                                </tr>
                                <tr v-if="sedes.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Esta empresa no tiene sedes todavía.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
