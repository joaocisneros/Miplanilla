<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    periodos: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null }) },
    empresas: { type: Array, default: () => [] },
});

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeGenerar = computed(() => permisos.value.includes('planilla.generar'));
const puedeCerrar = computed(() => permisos.value.includes('planilla.cerrar'));

// Filtro
const fEmpresa = ref(props.filtros.empresa_id ?? '');
function filtrar() {
    router.get(route('planilla.index'), { empresa_id: fEmpresa.value || undefined }, { preserveState: true, preserveScroll: true });
}

const mostrarForm = ref(false);
const modoTodas = ref(false);
const form = useForm({ empresa_id: '', anio: new Date().getFullYear(), mes: new Date().getMonth() + 1, quincena: 1, fecha_inicio: '', fecha_fin: '', fecha_pago: '' });

const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function recalcularFechas() {
    const a = Number(form.anio), m = Number(form.mes);
    if (!a || !m) return;
    const dd = (n) => String(n).padStart(2, '0');
    const ultimoDia = new Date(a, m, 0).getDate();
    if (form.quincena === 1) { form.fecha_inicio = `${a}-${dd(m)}-01`; form.fecha_fin = `${a}-${dd(m)}-15`; }
    else if (form.quincena === 2) { form.fecha_inicio = `${a}-${dd(m)}-16`; form.fecha_fin = `${a}-${dd(m)}-${dd(ultimoDia)}`; }
    else { form.fecha_inicio = `${a}-${dd(m)}-01`; form.fecha_fin = `${a}-${dd(m)}-${dd(ultimoDia)}`; }
}
watch(() => [form.anio, form.mes, form.quincena], recalcularFechas);

function abrirNuevo(todas) {
    modoTodas.value = todas;
    form.reset();
    form.empresa_id = todas ? '' : (fEmpresa.value || '');
    form.clearErrors();
    recalcularFechas();
    mostrarForm.value = true;
}
function enviar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrarForm.value = false; form.reset(); } };
    modoTodas.value
        ? form.post(route('planilla.generar-todas'), opts)
        : form.post(route('planilla.periodos.store'), opts);
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
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
const selectCls = 'rounded-md border-gray-300 py-1.5 text-sm';
</script>

<template>
    <Head title="Planilla" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Planilla</h2>
                <div class="flex gap-2">
                    <button v-if="puedeGenerar" @click="abrirNuevo(true)" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">⚡ Generar todas las empresas</button>
                    <button v-if="puedeGenerar" @click="abrirNuevo(false)" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo periodo</button>
                </div>
            </div>
        </template>
        <div class="p-6">
            <div class="space-y-6">

                <!-- Filtro -->
                <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Empresa</label>
                        <select v-model="fEmpresa" @change="filtrar" :class="selectCls">
                            <option value="">Todas las empresas</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                        </select>
                    </div>
                    <div class="ml-auto self-center text-sm text-gray-500">{{ periodos.length }} periodo(s)</div>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Periodo</th><th class="px-4 py-3">Rango</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Empleados</th><th class="px-4 py-3">Total neto</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in periodos" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3"><span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{{ p.empresa ?? '—' }}</span></td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ p.descripcion }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ p.fecha_inicio }} → {{ p.fecha_fin }}</td>
                                <td class="px-4 py-3"><span :class="p.estado === 'cerrado' ? 'bg-gray-200 text-gray-700' : (p.payroll ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800')" class="rounded-full px-2 py-1 text-xs">{{ p.payroll ? p.payroll.estado : 'sin generar' }}</span></td>
                                <td class="px-4 py-3">{{ p.payroll?.cantidad_empleados ?? '—' }}</td>
                                <td class="px-4 py-3">{{ p.payroll ? money(p.payroll.total_neto) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link v-if="p.payroll" :href="route('planilla.show', p.payroll.id)" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">👁 Ver</Link>
                                        <button v-if="puedeGenerar && p.estado !== 'cerrado'" @click="generar(p)" class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">{{ p.payroll ? '↻ Recalcular' : '⚙ Generar' }}</button>
                                        <button v-if="puedeCerrar && p.payroll && p.estado !== 'cerrado'" @click="cerrar(p.payroll.id, p.descripcion)" class="inline-flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">🔒 Cerrar</button>
                                        <span v-if="p.estado === 'cerrado'" class="text-xs text-gray-400">Cerrado</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="periodos.length === 0"><td colspan="7" class="px-4 py-6 text-center text-gray-500">No hay periodos. Crea uno con "Nuevo periodo".</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <CrudModal :show="mostrarForm" :titulo="modoTodas ? 'Generar todas las empresas' : 'Nuevo periodo'" @close="mostrarForm = false">
            <form @submit.prevent="enviar" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div v-if="modoTodas" class="md:col-span-3 rounded-md bg-emerald-50 p-3 text-xs text-emerald-800">
                    Se creará y generará este periodo en <b>todas las empresas</b>, cada una con su planilla por separado.
                </div>
                <div v-else class="md:col-span-3">
                    <label class="text-sm font-semibold text-gray-700">Empresa *</label>
                    <select v-model="form.empresa_id" :class="inp">
                        <option value="">— Selecciona empresa —</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                    </select>
                    <p v-if="form.errors.empresa_id" class="text-xs text-red-600">{{ form.errors.empresa_id }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Año</label>
                    <input v-model="form.anio" type="number" min="2020" max="2100" :class="inp" />
                </div>
                <div>
                    <label class="text-sm text-gray-700">Mes</label>
                    <select v-model.number="form.mes" :class="inp">
                        <option v-for="(nombre, i) in meses" :key="i" :value="i + 1">{{ nombre }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Quincena</label>
                    <select v-model="form.quincena" :class="inp">
                        <option :value="1">1ra quincena (01–15)</option>
                        <option :value="2">2da quincena (16–fin)</option>
                        <option :value="null">Mensual (todo el mes)</option>
                    </select>
                </div>
                <div class="md:col-span-3 rounded-md bg-blue-50 p-3 text-xs text-blue-800">
                    Las fechas se completan solas según el mes y la quincena. Solo cámbialas si tu empresa usa un corte distinto.
                </div>
                <div>
                    <label class="text-sm text-gray-700">Fecha inicio</label>
                    <input v-model="form.fecha_inicio" type="date" :class="inp" />
                    <p v-if="form.errors.fecha_inicio" class="text-xs text-red-600">{{ form.errors.fecha_inicio }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Fecha fin</label>
                    <input v-model="form.fecha_fin" type="date" :class="inp" />
                    <p v-if="form.errors.fecha_fin" class="text-xs text-red-600">{{ form.errors.fecha_fin }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Fecha pago</label>
                    <input v-model="form.fecha_pago" type="date" :class="inp" />
                </div>
                <div class="flex items-center justify-end gap-3 md:col-span-3">
                    <button type="button" @click="mostrarForm = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" :class="modoTodas ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700'" class="rounded-md px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ modoTodas ? 'Generar las empresas' : 'Crear periodo' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
