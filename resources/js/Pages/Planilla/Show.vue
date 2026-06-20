<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    payroll: { type: Object, required: true },
    detalles: { type: Array, default: () => [] },
});
const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
</script>

<template>
    <Head title="Detalle de planilla" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('planilla.index')" class="text-sm text-indigo-600">&larr; Planilla</Link>
                <h2 class="text-xl font-semibold text-gray-800">{{ payroll.descripcion }} — {{ payroll.empresa }}</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- Resumen -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Empleados</div><div class="text-2xl font-bold">{{ payroll.cantidad_empleados }}</div></div>
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Total ingresos</div><div class="text-xl font-bold text-gray-800">{{ money(payroll.total_ingresos) }}</div></div>
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Total neto</div><div class="text-xl font-bold text-green-700">{{ money(payroll.total_neto) }}</div></div>
                    <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Aportes empleador</div><div class="text-xl font-bold text-blue-700">{{ money(payroll.total_aportes_empleador) }}</div></div>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Trabajador</th><th class="px-4 py-3">Base afecta</th><th class="px-4 py-3">Ingresos</th><th class="px-4 py-3">Pensión</th><th class="px-4 py-3">Renta 5ta</th><th class="px-4 py-3">Neto</th><th class="px-4 py-3 text-right">Boleta</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="d in detalles" :key="d.id">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ d.empleado }}</td>
                                <td class="px-4 py-2">{{ money(d.base_afecta) }}</td>
                                <td class="px-4 py-2">{{ money(d.total_ingresos) }}</td>
                                <td class="px-4 py-2 text-red-600">{{ money(d.pension_total) }}</td>
                                <td class="px-4 py-2 text-red-600">{{ money(d.renta_5ta) }}</td>
                                <td class="px-4 py-2 font-semibold text-green-700">{{ money(d.neto) }}</td>
                                <td class="px-4 py-2 text-right"><a :href="route('boletas.pdf', d.id)" class="text-indigo-600 hover:text-indigo-900">PDF</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
