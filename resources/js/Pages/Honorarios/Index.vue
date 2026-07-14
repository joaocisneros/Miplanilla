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
const roles = computed(() => usePage().props.auth?.roles ?? []);
const puedeGenerar = computed(() => permisos.value.includes('planilla.generar'));
// Cerrar y reabrir: SOLO el administrador.
const puedeCerrar = computed(() => roles.value.includes('ADMIN'));

// Filtros. Por defecto solo se ven los periodos ABIERTOS;
// los cerrados se consultan eligiendo "Cerrados" (o "Todos").
const fEmpresa = ref(props.filtros.empresa_id ?? '');
const fEstado = ref('abiertos');
const fMes = ref('');
const fAnio = ref('');
function filtrar() {
    router.get(route('honorarios.index'), { empresa_id: fEmpresa.value || undefined }, { preserveState: true, preserveScroll: true });
}
const aniosDisponibles = computed(() => [...new Set(props.periodos.map((p) => p.fecha_inicio?.substring(0, 4)))].filter(Boolean).sort().reverse());
const periodosVisibles = computed(() =>
    props.periodos.filter((p) => {
        if (fEstado.value === 'abiertos' && p.estado === 'cerrado') return false;
        if (fEstado.value === 'cerrado' && p.estado !== 'cerrado') return false;
        if (fMes.value && p.fecha_inicio?.substring(5, 7) !== fMes.value) return false;
        if (fAnio.value && p.fecha_inicio?.substring(0, 4) !== fAnio.value) return false;
        return true;
    })
);

function cerrarPeriodo(p) {
    if (confirm(`¿Cerrar "${p.descripcion}" de ${p.empresa}?\n\nSe cierra TODO el periodo (planilla + honorarios) y ya NO se podrá recalcular. Hazlo solo cuando los pagos estén revisados.`)) {
        router.post(route('honorarios.cerrar', p.payroll_id), {}, { preserveScroll: true });
    }
}
function reabrirPeriodo(p) {
    if (confirm(`¿Reabrir "${p.descripcion}" de ${p.empresa}?\n\nVolverá a poder recalcularse. Ojo: si hay trabajadores cesados o tarifas cambiadas después del periodo, un recálculo puede alterar los pagos ya validados.`)) {
        router.post(route('honorarios.reabrir', p.payroll_id), {}, { preserveScroll: true });
    }
}

// Generar honorarios (independiente del módulo Planilla: no hace falta entrar ahí)
const mostrarForm = ref(false);
const meses = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const form = useForm({ anio: new Date().getFullYear(), mes: new Date().getMonth() + 1, quincena: 1, fecha_inicio: '', fecha_fin: '', fecha_pago: '' });

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

function abrirGenerar() {
    form.reset();
    form.clearErrors();
    recalcularFechas();
    mostrarForm.value = true;
}
function generar() {
    form.post(route('honorarios.generar'), {
        preserveScroll: true,
        onSuccess: () => { mostrarForm.value = false; },
    });
}

function recalcular(p) {
    if (confirm(`Recalcular "${p.descripcion}" de ${p.empresa}? Esto recalcula TODO el periodo (planilla + honorarios).`)) {
        router.post(route('honorarios.recalcular', p.payroll_id), {}, { preserveScroll: true });
    }
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
const selectCls = 'rounded-md border-gray-300 py-1.5 text-sm';
</script>

<template>
    <Head title="Honorarios (RxH)" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Recibos por Honorarios (RxH)</h2>
                <button v-if="puedeGenerar" @click="abrirGenerar" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">⚙ Generar honorarios</button>
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
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Estado</label>
                        <select v-model="fEstado" :class="selectCls">
                            <option value="abiertos">Abiertos</option>
                            <option value="cerrado">Cerrados</option>
                            <option value="todos">Todos</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Mes</label>
                        <select v-model="fMes" :class="selectCls">
                            <option value="">Todos</option>
                            <option v-for="(m, i) in meses.slice(1)" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Año</label>
                        <select v-model="fAnio" :class="selectCls">
                            <option value="">Todos</option>
                            <option v-for="a in aniosDisponibles" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div class="ml-auto self-center text-sm text-gray-500">{{ periodosVisibles.length }} periodo(s) con honorarios</div>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Periodo</th><th class="px-4 py-3">Rango</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Trabajadores</th><th class="px-4 py-3">Total neto</th><th class="px-4 py-3 text-right">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in periodosVisibles" :key="p.payroll_id" class="hover:bg-gray-50">
                                <td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ p.empresa }}</span></td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ p.descripcion }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ p.fecha_inicio }} → {{ p.fecha_fin }}</td>
                                <td class="px-4 py-3"><span :class="p.estado === 'cerrado' ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-800'" class="rounded-full px-2 py-1 text-xs">{{ p.estado }}</span></td>
                                <td class="px-4 py-3">{{ p.cantidad_empleados }}</td>
                                <td class="px-4 py-3">{{ money(p.total_neto) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('honorarios.show', p.payroll_id)" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">👁 Ver</Link>
                                        <a :href="route('honorarios.excel', p.payroll_id)" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" title="Descargar Excel">📥 Excel</a>
                                        <button v-if="puedeGenerar && p.estado !== 'cerrado'" @click="recalcular(p)" class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100" title="Recalcula TODO el periodo (planilla + honorarios)">↻ Recalcular</button>
                                        <button v-if="puedeCerrar && p.estado !== 'cerrado'" @click="cerrarPeriodo(p)" class="inline-flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100" title="Cierra el periodo: ya no se podrá recalcular">🔒 Cerrar</button>
                                        <span v-if="p.estado === 'cerrado'" class="text-xs text-gray-400">🔒 Cerrado</span>
                                        <button v-if="puedeCerrar && p.estado === 'cerrado'" @click="reabrirPeriodo(p)" class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100" title="Solo el administrador puede reabrir">🔓 Reabrir</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="periodosVisibles.length === 0"><td colspan="7" class="px-4 py-6 text-center text-gray-500">No hay honorarios generados todavía. Registra empleados con modalidad "Recibos por Honorarios" y usa "Generar honorarios".</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <CrudModal :show="mostrarForm" titulo="Generar honorarios" @close="mostrarForm = false">
            <form @submit.prevent="generar" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-3 rounded-md bg-emerald-50 p-3 text-xs text-emerald-800">
                    Calcula el periodo para <b>todas las empresas</b> que tengan trabajadores activos por honorarios. No afecta la planilla regular.
                </div>
                <div>
                    <label class="text-sm text-gray-700">Año</label>
                    <input v-model="form.anio" type="number" min="2020" max="2100" :class="inp" />
                </div>
                <div>
                    <label class="text-sm text-gray-700">Mes</label>
                    <select v-model.number="form.mes" :class="inp">
                        <option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option>
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
                    Las fechas se completan solas según el mes y la quincena. Solo cámbialas si el corte es distinto.
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
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">Generar</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
