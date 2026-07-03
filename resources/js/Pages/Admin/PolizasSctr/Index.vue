<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import BotonAccion from '@/Components/BotonAccion.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ polizas: { type: Array, default: () => [] } });

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ aseguradora: '', actividad_riesgo: '', tasa_salud: '', tasa_pension: '', vigente_desde: '', vigente_hasta: '', confirmado: false });

function abrirNuevo() { editandoId.value = null; form.reset(); form.clearErrors(); mostrar.value = true; }
function abrirEditar(p) {
    editandoId.value = p.id;
    Object.assign(form, { aseguradora: p.aseguradora, actividad_riesgo: p.actividad_riesgo ?? '', tasa_salud: p.tasa_salud, tasa_pension: p.tasa_pension, vigente_desde: p.vigente_desde?.substring(0, 10) ?? '', vigente_hasta: p.vigente_hasta?.substring(0, 10) ?? '', confirmado: !!p.confirmado });
    form.clearErrors(); mostrar.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.polizas-sctr.update', editandoId.value), opts) : form.post(route('admin.polizas-sctr.store'), opts);
}
function eliminar(p) { if (confirm('¿Eliminar esta póliza SCTR?')) form.delete(route('admin.polizas-sctr.destroy', p.id), { preserveScroll: true }); }

const pct = (v) => (Number(v ?? 0) * 100).toFixed(2) + '%';
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Pólizas SCTR" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Pólizas SCTR (Seguro Complementario de Trabajo de Riesgo)</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nueva póliza</button>
            </div>
        </template>

        <div class="space-y-6 p-6">
            <div class="rounded-lg bg-teal-50 p-4 text-sm text-teal-900">
                🛡️ El <b>SCTR</b> es obligatorio para trabajadores de <b>actividad de riesgo</b> (obra, maquinaria). Cubre <b>Salud</b> (EsSalud/EPS) y <b>Pensión</b> (ONP/aseguradora). Las tasas las define tu póliza; aquí se cargan para que la planilla calcule el aporte sobre la base afecta.
            </div>
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Aseguradora</th><th class="px-4 py-3">Actividad riesgo</th><th class="px-4 py-3">Tasa salud</th><th class="px-4 py-3">Tasa pensión</th><th class="px-4 py-3">Vigencia</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acción</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="p in polizas" :key="p.id">
                            <td class="px-4 py-3 font-medium">{{ p.aseguradora }}</td>
                            <td class="px-4 py-3">{{ p.actividad_riesgo ?? '—' }}</td>
                            <td class="px-4 py-3">{{ pct(p.tasa_salud) }}</td>
                            <td class="px-4 py-3">{{ pct(p.tasa_pension) }}</td>
                            <td class="px-4 py-3">{{ p.vigente_desde?.substring(0, 10) }}</td>
                            <td class="px-4 py-3"><span :class="p.confirmado ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="rounded-full px-2 py-1 text-xs">{{ p.confirmado ? 'Confirmada' : 'Por confirmar' }}</span></td>
                            <td class="px-4 py-3 text-right"><div class="inline-flex gap-2"><BotonAccion variante="editar" @click="abrirEditar(p)" /><BotonAccion variante="eliminar" @click="eliminar(p)" /></div></td>
                        </tr>
                        <tr v-if="polizas.length === 0"><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin pólizas SCTR. Mientras no cargues una, el SCTR sale en 0 en la planilla.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar póliza SCTR' : 'Nueva póliza SCTR'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2"><label class="text-sm text-gray-700">Aseguradora</label><input v-model="form.aseguradora" :class="inp" /><p v-if="form.errors.aseguradora" class="text-xs text-red-600">{{ form.errors.aseguradora }}</p></div>
                <div class="md:col-span-2"><label class="text-sm text-gray-700">Actividad de riesgo</label><input v-model="form.actividad_riesgo" placeholder="Ej: Construcción civil" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Tasa salud (decimal, ej: 0.0153)</label><input v-model="form.tasa_salud" type="number" step="0.0001" :class="inp" /><p v-if="form.errors.tasa_salud" class="text-xs text-red-600">{{ form.errors.tasa_salud }}</p></div>
                <div><label class="text-sm text-gray-700">Tasa pensión (decimal, ej: 0.0163)</label><input v-model="form.tasa_pension" type="number" step="0.0001" :class="inp" /><p v-if="form.errors.tasa_pension" class="text-xs text-red-600">{{ form.errors.tasa_pension }}</p></div>
                <div><label class="text-sm text-gray-700">Vigente desde</label><input v-model="form.vigente_desde" type="date" :class="inp" /><p v-if="form.errors.vigente_desde" class="text-xs text-red-600">{{ form.errors.vigente_desde }}</p></div>
                <div><label class="text-sm text-gray-700">Vigente hasta</label><input v-model="form.vigente_hasta" type="date" :class="inp" /></div>
                <div class="flex items-center gap-2 md:col-span-2"><input v-model="form.confirmado" type="checkbox" id="confS" class="rounded" /><label for="confS" class="text-sm">Confirmada (póliza real cargada)</label></div>
                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
