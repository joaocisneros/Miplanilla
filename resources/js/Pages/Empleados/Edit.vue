<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmpleadoForm from '@/Components/EmpleadoForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    sedes: Array, areas: Array, cargos: Array, turnos: Array,
    empleado: Object, contrato: Object, derechohabientes: Array,
});
</script>

<template>
    <Head title="Editar empleado" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('empleados.index')" class="text-sm text-indigo-600">&larr; Empleados</Link>
                <h2 class="text-xl font-semibold text-gray-800">
                    Editar: {{ empleado.apellido_paterno }} {{ empleado.nombres }}
                </h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <EmpleadoForm
                    :sedes="sedes" :areas="areas" :cargos="cargos" :turnos="turnos"
                    :empleado="empleado" :contrato="contrato" :derechohabientes="derechohabientes"
                    modo="edit" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
