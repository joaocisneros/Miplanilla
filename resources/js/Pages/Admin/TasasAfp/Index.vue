<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ tasas: { type: Array, default: () => [] } });

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ afp: '', tipo: 'mixta', aporte_obligatorio: 0.10, comision_flujo: 0, comision_saldo: 0, prima_seguro: 0.0174, rem_max_asegurable: '', vigente_desde: '', vigente_hasta: '', confirmado: false, fuente: '' });

function abrirNuevo() { editandoId.value = null; form.reset(); form.tipo = 'mixta'; form.aporte_obligatorio = 0.10; form.prima_seguro = 0.0174; form.clearErrors(); mostrar.value = true; }
function abrirEditar(t) {
    editandoId.value = t.id;
    Object.assign(form, { afp: t.afp, tipo: t.tipo, aporte_obligatorio: t.aporte_obligatorio, comision_flujo: t.comision_flujo, comision_saldo: t.comision_saldo, prima_seguro: t.prima_seguro, rem_max_asegurable: t.rem_max_asegurable ?? '', vigente_desde: t.vigente_desde?.substring(0,10) ?? '', vigente_hasta: t.vigente_hasta?.substring(0,10) ?? '', confirmado: !!t.confirmado, fuente: t.fuente ?? '' });
    form.clearErrors(); mostrar.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.tasas-afp.update', editandoId.value), opts) : form.post(route('admin.tasas-afp.store'), opts);
}
function eliminar(t) { if (confirm(`¿Eliminar ${t.afp} (${t.tipo})?`)) form.delete(route('admin.tasas-afp.destroy', t.id), { preserveScroll: true }); }
const pct = (v) => (v * 100).toFixed(2) + '%';
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Tasas AFP/ONP" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Tasas AFP / ONP</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nueva tasa</button>
            </div>
        </template>

        <div class="space-y-6 p-6">
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">🌍 Tasas <b>nacionales (SBS)</b>, válidas para todas las empresas. Versionadas por vigencia.</div>
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">AFP</th><th class="px-3 py-3">Tipo</th><th class="px-3 py-3">Aporte</th><th class="px-3 py-3">Com. flujo</th><th class="px-3 py-3">Com. saldo</th><th class="px-3 py-3">Prima</th><th class="px-3 py-3">Estado</th><th class="px-3 py-3 text-right">Acción</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="t in tasas" :key="t.id">
                            <td class="px-3 py-3 font-medium">{{ t.afp }}</td>
                            <td class="px-3 py-3 capitalize">{{ t.tipo }}</td>
                            <td class="px-3 py-3">{{ pct(t.aporte_obligatorio) }}</td>
                            <td class="px-3 py-3">{{ pct(t.comision_flujo) }}</td>
                            <td class="px-3 py-3">{{ pct(t.comision_saldo) }}</td>
                            <td class="px-3 py-3">{{ pct(t.prima_seguro) }}</td>
                            <td class="px-3 py-3"><span :class="t.confirmado ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="rounded-full px-2 py-1 text-xs">{{ t.confirmado ? 'OK' : 'Por confirmar' }}</span></td>
                            <td class="px-3 py-3 text-right"><button @click="abrirEditar(t)" class="text-indigo-600 hover:text-indigo-900">Editar</button><button @click="eliminar(t)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button></td>
                        </tr>
                        <tr v-if="tasas.length === 0"><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin tasas todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar tasa' : 'Nueva tasa'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><label class="text-sm text-gray-700">AFP</label><input v-model="form.afp" placeholder="INTEGRA / ONP" :class="inp" /><p v-if="form.errors.afp" class="text-xs text-red-600">{{ form.errors.afp }}</p></div>
                <div><label class="text-sm text-gray-700">Tipo</label><select v-model="form.tipo" :class="inp"><option value="mixta">Mixta</option><option value="sueldo">Sueldo (flujo)</option><option value="onp">ONP</option></select></div>
                <div><label class="text-sm text-gray-700">Aporte obligatorio</label><input v-model="form.aporte_obligatorio" type="number" step="0.0001" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Comisión flujo</label><input v-model="form.comision_flujo" type="number" step="0.0001" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Comisión saldo</label><input v-model="form.comision_saldo" type="number" step="0.0001" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Prima seguro</label><input v-model="form.prima_seguro" type="number" step="0.0001" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Rem. máx. asegurable</label><input v-model="form.rem_max_asegurable" type="number" step="0.01" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Vigente desde</label><input v-model="form.vigente_desde" type="date" :class="inp" /><p v-if="form.errors.vigente_desde" class="text-xs text-red-600">{{ form.errors.vigente_desde }}</p></div>
                <div class="flex items-center gap-2 md:col-span-2"><input v-model="form.confirmado" type="checkbox" id="confa" class="rounded" /><label for="confa" class="text-sm">Confirmado</label></div>
                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
