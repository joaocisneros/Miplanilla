<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    filas: { type: Array, default: () => [] },
    historial: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null }) },
});

const empresaId = ref(props.filtros.empresa_id);
function filtrar() { router.get(route('vacaciones.index'), { empresa_id: empresaId.value }, { preserveState: true }); }

const mostrar = ref(false);
const trabajadorSel = ref(null);
const form = useForm({ empresa_id: null, employee_id: null, fecha_inicio: '', fecha_fin: '', dias: 1, observacion: '' });

function abrirRegistro(f) {
    trabajadorSel.value = f;
    form.reset(); form.clearErrors();
    form.empresa_id = props.filtros.empresa_id;
    form.employee_id = f.id;
    mostrar.value = true;
}
function guardar() {
    form.post(route('vacaciones.store'), { preserveScroll: true, onSuccess: () => { mostrar.value = false; } });
}
function eliminar(v) { if (confirm('¿Eliminar este registro de vacaciones?')) router.delete(route('vacaciones.destroy', v.id), { preserveScroll: true }); }

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Vacaciones" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Vacaciones</h2></template>

        <div class="p-6 space-y-6">
            <div class="rounded-lg bg-lime-50 p-4 text-sm text-lime-900">
                🌴 Todo trabajador tiene derecho a <b>30 días de vacaciones por año</b> de servicios (se acumulan <b>2.5 días por mes</b>). El pago equivale a <b>una remuneración</b> por los 30 días. Aquí ves los <b>días ganados, gozados y el saldo</b>, y registras cuándo las toma.
            </div>

            <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <label class="text-sm text-gray-700">Empresa</label>
                    <select v-model="empresaId" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
                <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Ver</button>
            </div>

            <div v-if="filtros.empresa_id" class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="px-4 py-3">DNI</th><th class="px-4 py-3">Trabajador</th><th class="px-4 py-3">Ingreso</th><th class="px-4 py-3 text-center">Meses</th><th class="px-4 py-3 text-center">Ganados</th><th class="px-4 py-3 text-center">Gozados</th><th class="px-4 py-3 text-center">Saldo</th><th class="px-4 py-3 text-right">Acción</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="f in filas" :key="f.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2">{{ f.dni }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ f.trabajador }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ f.ingreso }}</td>
                            <td class="px-4 py-2 text-center">{{ f.meses_servicio }}</td>
                            <td class="px-4 py-2 text-center">{{ f.dias_ganados }}</td>
                            <td class="px-4 py-2 text-center">{{ f.dias_gozados }}</td>
                            <td class="px-4 py-2 text-center font-semibold" :class="f.saldo > 0 ? 'text-green-700' : 'text-gray-500'">{{ f.saldo }}</td>
                            <td class="px-4 py-2 text-right"><button @click="abrirRegistro(f)" class="rounded-md bg-lime-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-lime-700">+ Registrar</button></td>
                        </tr>
                        <tr v-if="filas.length === 0"><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin trabajadores activos.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="filtros.empresa_id && historial.length" class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700">Historial de vacaciones tomadas</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-2">Trabajador</th><th class="px-4 py-2">Desde</th><th class="px-4 py-2">Hasta</th><th class="px-4 py-2 text-center">Días</th><th class="px-4 py-2 text-right">Pago</th><th class="px-4 py-2">Obs.</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="v in historial" :key="v.id">
                            <td class="px-4 py-2 font-medium">{{ v.trabajador }}</td>
                            <td class="px-4 py-2">{{ v.fecha_inicio }}</td>
                            <td class="px-4 py-2">{{ v.fecha_fin }}</td>
                            <td class="px-4 py-2 text-center">{{ v.dias }}</td>
                            <td class="px-4 py-2 text-right text-green-700">{{ money(v.monto) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ v.observacion }}</td>
                            <td class="px-4 py-2 text-right"><button @click="eliminar(v)" class="text-red-600 hover:text-red-900">Eliminar</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="'Registrar vacaciones — ' + (trabajadorSel?.trabajador ?? '')" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <p class="md:col-span-2 text-xs text-gray-500">Saldo disponible: <b>{{ trabajadorSel?.saldo }}</b> días · Sueldo: {{ money(trabajadorSel?.sueldo) }}. El pago se calcula como sueldo ÷ 30 × días.</p>
                <div><label class="text-sm text-gray-700">Desde</label><input v-model="form.fecha_inicio" type="date" :class="inp" /><p v-if="form.errors.fecha_inicio" class="text-xs text-red-600">{{ form.errors.fecha_inicio }}</p></div>
                <div><label class="text-sm text-gray-700">Hasta</label><input v-model="form.fecha_fin" type="date" :class="inp" /><p v-if="form.errors.fecha_fin" class="text-xs text-red-600">{{ form.errors.fecha_fin }}</p></div>
                <div><label class="text-sm text-gray-700">Días</label><input v-model="form.dias" type="number" min="1" :class="inp" /><p v-if="form.errors.dias" class="text-xs text-red-600">{{ form.errors.dias }}</p></div>
                <div><label class="text-sm text-gray-700">Observación</label><input v-model="form.observacion" :class="inp" /></div>
                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-lime-600 px-4 py-2 text-sm font-semibold text-white hover:bg-lime-700 disabled:opacity-50">Registrar</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
