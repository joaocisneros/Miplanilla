<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({ periodos: { type: Array, default: () => [] } });

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeGenerar = computed(() => permisos.value.includes('planilla.generar'));
const puedeCerrar = computed(() => permisos.value.includes('planilla.cerrar'));

const mostrarForm = ref(false);
const form = useForm({ anio: new Date().getFullYear(), mes: new Date().getMonth() + 1, quincena: 1, fecha_inicio: '', fecha_fin: '', fecha_pago: '' });

function crearPeriodo() {
    form.post(route('planilla.periodos.store'), { preserveScroll: true, onSuccess: () => { mostrarForm.value = false; form.reset(); } });
}
function generar(p) {
    if (confirm(`Generar/recalcular la planilla de "${p.descripcion}"?`)) {
        router.post(route('planilla.generar', p.id), {}, { preserveScroll: true });
    }
}
function cerrar(payrollId, desc) {
    if (confirm(`Cerrar la planilla "${desc}"? Ya no se podrá recalcular.`)) {
        router.post(route('planilla.cerrar', payrollId), {}, { preserveScroll: true });
    }
}
const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
</script>

<template>
    <Head title="Planilla" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Planilla</h2>
                <button v-if="puedeGenerar" @click="mostrarForm = !mostrarForm" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo periodo</button>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <div v-if="mostrarForm" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium">Nuevo periodo</h3>
                    <form @submit.prevent="crearPeriodo" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div><label class="text-sm text-gray-700">Año</label><input v-model="form.anio" type="number" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div><label class="text-sm text-gray-700">Mes</label><input v-model="form.mes" type="number" min="1" max="12" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div><label class="text-sm text-gray-700">Quincena</label><select v-model="form.quincena" class="mt-1 block w-full rounded-md border-gray-300"><option :value="1">1ra</option><option :value="2">2da</option><option :value="null">Mensual</option></select></div>
                        <div><label class="text-sm text-gray-700">Fecha inicio</label><input v-model="form.fecha_inicio" type="date" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.fecha_inicio" class="text-xs text-red-600">{{ form.errors.fecha_inicio }}</p></div>
                        <div><label class="text-sm text-gray-700">Fecha fin</label><input v-model="form.fecha_fin" type="date" class="mt-1 block w-full rounded-md border-gray-300" /><p v-if="form.errors.fecha_fin" class="text-xs text-red-600">{{ form.errors.fecha_fin }}</p></div>
                        <div><label class="text-sm text-gray-700">Fecha pago</label><input v-model="form.fecha_pago" type="date" class="mt-1 block w-full rounded-md border-gray-300" /></div>
                        <div class="md:col-span-3"><button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Crear</button></div>
                    </form>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Periodo</th><th class="px-4 py-3">Rango</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Empleados</th><th class="px-4 py-3">Total neto</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in periodos" :key="p.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ p.descripcion }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ p.fecha_inicio }} → {{ p.fecha_fin }}</td>
                                <td class="px-4 py-3"><span :class="p.estado === 'cerrado' ? 'bg-gray-200 text-gray-700' : (p.payroll ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800')" class="rounded-full px-2 py-1 text-xs">{{ p.payroll ? p.payroll.estado : 'sin generar' }}</span></td>
                                <td class="px-4 py-3">{{ p.payroll?.cantidad_empleados ?? '—' }}</td>
                                <td class="px-4 py-3">{{ p.payroll ? money(p.payroll.total_neto) : '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link v-if="p.payroll" :href="route('planilla.show', p.payroll.id)" class="text-indigo-600 hover:text-indigo-900">Ver</Link>
                                    <button v-if="puedeGenerar && p.estado !== 'cerrado'" @click="generar(p)" class="ml-3 text-blue-600 hover:text-blue-900">{{ p.payroll ? 'Recalcular' : 'Generar' }}</button>
                                    <button v-if="puedeCerrar && p.payroll && p.estado !== 'cerrado'" @click="cerrar(p.payroll.id, p.descripcion)" class="ml-3 text-red-600 hover:text-red-900">Cerrar</button>
                                </td>
                            </tr>
                            <tr v-if="periodos.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay periodos. Crea uno con "Nuevo periodo".</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
