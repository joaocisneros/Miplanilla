<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import EmpleadoForm from '@/Components/EmpleadoForm.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    empleados: { type: Array, default: () => [] },
    sedes: Array, areas: Array, cargos: Array, turnos: Array,
});

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeGestionar = computed(() => permisos.value.includes('empleados.gestionar'));

const mostrar = ref(false);
const modo = ref('create');
const seleccionado = ref(null);
const formKey = ref(0); // fuerza re-montar el form al cambiar de empleado

function abrirNuevo() {
    modo.value = 'create';
    seleccionado.value = null;
    formKey.value++;
    mostrar.value = true;
}
function abrirEditar(emp) {
    modo.value = 'edit';
    seleccionado.value = emp;
    formKey.value++;
    mostrar.value = true;
}
function onGuardado() {
    mostrar.value = false;
}
function eliminar(emp) {
    if (confirm(`¿Eliminar a ${emp.nombre_completo}?`)) {
        router.delete(route('empleados.destroy', emp.id), { preserveScroll: true });
    }
}
const money = (v) => v != null ? 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }) : '—';
</script>

<template>
    <Head title="Empleados" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Empleados</h2>
                <button v-if="puedeGestionar" @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo empleado</button>
            </div>
        </template>

        <div class="p-6">
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Trabajador</th>
                            <th class="px-4 py-3">Documento</th>
                            <th class="px-4 py-3">Sede</th>
                            <th class="px-4 py-3">Sueldo</th>
                            <th class="px-4 py-3">Pensión</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="emp in empleados" :key="emp.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ emp.nombre_completo }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ emp.numero_documento }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ emp.sede ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ money(emp.sueldo_basico) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ emp.sistema_pensiones ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="puedeGestionar" @click="abrirEditar(emp)" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button v-if="puedeGestionar" @click="eliminar(emp)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="empleados.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay empleados en esta empresa/sede. Usa "Nuevo empleado".</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" max-width="5xl" :titulo="modo === 'edit' ? 'Editar empleado' : 'Nuevo empleado'" @close="mostrar = false">
            <div class="max-h-[75vh] overflow-y-auto pr-1">
                <EmpleadoForm
                    :key="formKey"
                    :sedes="sedes" :areas="areas" :cargos="cargos" :turnos="turnos"
                    :empleado="seleccionado?.empleado ?? null"
                    :contrato="seleccionado?.contrato ?? null"
                    :derechohabientes="seleccionado?.derechohabientes ?? []"
                    :modo="modo"
                    @guardado="onGuardado"
                    @cancelar="mostrar = false"
                />
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
