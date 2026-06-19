<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    empresas: { type: Array, default: () => [] },
});

const editandoId = ref(null);

const form = useForm({
    ruc: '',
    razon_social: '',
    nombre_comercial: '',
    direccion: '',
    activo: true,
});

function nuevo() {
    editandoId.value = null;
    form.reset();
    form.activo = true;
}

function editar(empresa) {
    editandoId.value = empresa.id;
    form.ruc = empresa.ruc;
    form.razon_social = empresa.razon_social;
    form.nombre_comercial = empresa.nombre_comercial ?? '';
    form.direccion = empresa.direccion ?? '';
    form.activo = !!empresa.activo;
}

function guardar() {
    if (editandoId.value) {
        form.put(route('admin.empresas.update', editandoId.value), {
            preserveScroll: true,
            onSuccess: () => nuevo(),
        });
    } else {
        form.post(route('admin.empresas.store'), {
            preserveScroll: true,
            onSuccess: () => nuevo(),
        });
    }
}

function eliminar(empresa) {
    if (confirm(`¿Eliminar la empresa "${empresa.razon_social}"?`)) {
        form.delete(route('admin.empresas.destroy', empresa.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Empresas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Empresas
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- Formulario alta/edición -->
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">
                        {{ editandoId ? 'Editar empresa' : 'Nueva empresa' }}
                    </h3>
                    <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">RUC</label>
                            <input v-model="form.ruc" type="text" maxlength="11"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                            <p v-if="form.errors.ruc" class="mt-1 text-sm text-red-600">{{ form.errors.ruc }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Razón social</label>
                            <input v-model="form.razon_social" type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                            <p v-if="form.errors.razon_social" class="mt-1 text-sm text-red-600">{{ form.errors.razon_social }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre comercial</label>
                            <input v-model="form.nombre_comercial" type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dirección</label>
                            <input v-model="form.direccion" type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="form.activo" type="checkbox" id="activo"
                                class="rounded border-gray-300" />
                            <label for="activo" class="text-sm text-gray-700">Activa</label>
                        </div>
                        <div class="flex items-center gap-3 md:col-span-2">
                            <button type="submit" :disabled="form.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ editandoId ? 'Actualizar' : 'Registrar' }}
                            </button>
                            <button v-if="editandoId" type="button" @click="nuevo"
                                class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabla -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">RUC</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Razón social</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nombre comercial</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="e in empresas" :key="e.id">
                                <td class="px-4 py-3 text-sm text-gray-700">{{ e.ruc }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ e.razon_social }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ e.nombre_comercial ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="e.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                        class="rounded-full px-2 py-1 text-xs font-medium">
                                        {{ e.activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <button @click="editar(e)" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                    <button @click="eliminar(e)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button>
                                </td>
                            </tr>
                            <tr v-if="empresas.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No hay empresas registradas todavía.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
