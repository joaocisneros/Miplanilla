<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import BotonAccion from '@/Components/BotonAccion.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    regimenes: { type: Object, default: () => ({}) },
});

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ ruc: '', razon_social: '', nombre_comercial: '', direccion: '', representante_legal: '', representante_dni: '', representante_cargo: '', regimen_laboral: 'general', remype_numero: '', remype_fecha: '', giro: '', modo_calculo: 'excel', activo: true });

function abrirNuevo() { editandoId.value = null; form.reset(); form.activo = true; form.regimen_laboral = 'general'; form.modo_calculo = 'excel'; form.clearErrors(); mostrar.value = true; }
function abrirEditar(e) {
    editandoId.value = e.id;
    form.ruc = e.ruc; form.razon_social = e.razon_social; form.nombre_comercial = e.nombre_comercial ?? '';
    form.direccion = e.direccion ?? '';
    form.representante_legal = e.representante_legal ?? ''; form.representante_dni = e.representante_dni ?? ''; form.representante_cargo = e.representante_cargo ?? '';
    form.regimen_laboral = e.regimen_laboral ?? 'general';
    form.remype_numero = e.remype_numero ?? '';
    form.remype_fecha = e.remype_fecha ? String(e.remype_fecha).substring(0, 10) : '';
    form.giro = e.giro ?? '';
    form.modo_calculo = e.modo_calculo ?? 'excel';
    form.activo = !!e.activo; form.clearErrors(); mostrar.value = true;
}
function etiquetaRegimen(k) { return props.regimenes[k] ?? k ?? '—'; }
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.empresas.update', editandoId.value), opts) : form.post(route('admin.empresas.store'), opts);
}
function eliminar(e) {
    if (confirm(`¿Eliminar la empresa "${e.razon_social}"?`)) form.delete(route('admin.empresas.destroy', e.id), { preserveScroll: true });
}
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Empresas" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Empresas</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nueva empresa</button>
            </div>
        </template>

        <div class="p-6">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="px-4 py-3">RUC</th><th class="px-4 py-3">Razón social</th><th class="px-4 py-3">Régimen</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="e in empresas" :key="e.id">
                            <td class="px-4 py-3 text-gray-700">{{ e.ruc }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ e.razon_social }}</td>
                            <td class="px-4 py-3"><span :class="e.regimen_laboral === 'microempresa' ? 'bg-amber-100 text-amber-800' : (e.regimen_laboral === 'pequena' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700')" class="rounded-full px-2 py-1 text-xs font-medium">{{ etiquetaRegimen(e.regimen_laboral) }}</span></td>
                            <td class="px-4 py-3"><span :class="e.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-1 text-xs font-medium">{{ e.activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-2"><BotonAccion variante="editar" @click="abrirEditar(e)" /><BotonAccion variante="eliminar" @click="eliminar(e)" /></div>
                            </td>
                        </tr>
                        <tr v-if="empresas.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay empresas registradas todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar empresa' : 'Nueva empresa'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><label class="text-sm text-gray-700">RUC</label><input v-model="form.ruc" maxlength="11" :class="inp" /><p v-if="form.errors.ruc" class="text-xs text-red-600">{{ form.errors.ruc }}</p></div>
                <div><label class="text-sm text-gray-700">Razón social</label><input v-model="form.razon_social" :class="inp" /><p v-if="form.errors.razon_social" class="text-xs text-red-600">{{ form.errors.razon_social }}</p></div>
                <div><label class="text-sm text-gray-700">Nombre comercial</label><input v-model="form.nombre_comercial" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Dirección</label><input v-model="form.direccion" :class="inp" /></div>

                <div class="md:col-span-2 mt-2 border-t pt-3 text-xs font-semibold uppercase text-gray-500">Régimen laboral</div>
                <div>
                    <label class="text-sm text-gray-700">Régimen</label>
                    <select v-model="form.regimen_laboral" :class="inp">
                        <option v-for="(label, k) in regimenes" :key="k" :value="k">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.regimen_laboral" class="text-xs text-red-600">{{ form.errors.regimen_laboral }}</p>
                    <p v-if="form.regimen_laboral === 'microempresa'" class="mt-1 text-xs text-amber-700">Microempresa: sin gratificación ni CTS; vacaciones de 15 días; salud puede ser SIS.</p>
                    <p v-else-if="form.regimen_laboral === 'pequena'" class="mt-1 text-xs text-blue-700">Pequeña empresa: media gratificación, media CTS, vacaciones de 15 días; EsSalud.</p>
                </div>
                <div><label class="text-sm text-gray-700">Giro / actividad</label><input v-model="form.giro" placeholder="Ej: Fabricación de carrocerías metálicas" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">N° acogimiento REMYPE</label><input v-model="form.remype_numero" placeholder="Ej: 0001683243-2019" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Fecha acogimiento REMYPE</label><input v-model="form.remype_fecha" type="date" :class="inp" /></div>

                <div class="md:col-span-2 mt-2 border-t pt-3 text-xs font-semibold uppercase text-gray-500">Modo de cálculo de planilla</div>
                <div class="md:col-span-2">
                    <select v-model="form.modo_calculo" :class="inp">
                        <option value="excel">Como su Excel — horas extra/sábados/bonos van en movilidad (NO afectos)</option>
                        <option value="legal">Legal — horas extra/sábados/bonos afectos (pagan AFP/EsSalud/Renta)</option>
                    </select>
                    <p v-if="form.modo_calculo === 'excel'" class="mt-1 text-xs text-amber-700">Réplica del Excel del cliente: los adicionales suman al pago pero no son base de aportes ni renta.</p>
                    <p v-else class="mt-1 text-xs text-green-700">Modo legal: los adicionales pagan todos sus aportes (recomendado por el contador).</p>
                </div>

                <div class="md:col-span-2 mt-2 border-t pt-3 text-xs font-semibold uppercase text-gray-500">Representante legal (firma los contratos)</div>
                <div><label class="text-sm text-gray-700">Nombre del representante</label><input v-model="form.representante_legal" placeholder="Ej: Juan Pérez García" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">DNI del representante</label><input v-model="form.representante_dni" maxlength="15" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Cargo</label><input v-model="form.representante_cargo" placeholder="Ej: Gerente General" :class="inp" /></div>
                <div class="flex items-center gap-2"><input v-model="form.activo" type="checkbox" id="act" class="rounded" /><label for="act" class="text-sm">Activa</label></div>
                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
