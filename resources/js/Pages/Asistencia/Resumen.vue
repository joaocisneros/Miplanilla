<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    filas: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null, anio: null, mes: null }) },
    estados: { type: Object, default: () => ({}) },
    empresas: { type: Array, default: () => [] },
});

const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const fEmpresa = ref(props.filtros.empresa_id ?? '');
const fAnio = ref(props.filtros.anio ?? new Date().getFullYear());
const fMes = ref(props.filtros.mes ?? new Date().getMonth() + 1);
const fQuincena = ref(props.filtros.quincena ?? '');
const anioActual = new Date().getFullYear();
const anios = Array.from({ length: 6 }, (_, i) => anioActual + 1 - i);

const buscar = ref('');
const tipoFiltro = ref(''); // '', faltas, tardanzas, he, otros
const filasFiltradas = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    return props.filas.filter((f) => {
        const coincideTexto = !q || (f.empleado || '').toLowerCase().includes(q) || (f.documento || '').toLowerCase().includes(q);
        const coincideTipo =
            tipoFiltro.value === '' ||
            (tipoFiltro.value === 'faltas' && f.faltas > 0) ||
            (tipoFiltro.value === 'tardanzas' && f.tardanza_min > 0) ||
            (tipoFiltro.value === 'he' && f.horas_extra > 0) ||
            (tipoFiltro.value === 'otros' && Object.keys(f.otros || {}).length > 0);
        return coincideTexto && coincideTipo;
    });
});

function filtrar() {
    router.get(route('asistencia.resumen'), {
        empresa_id: fEmpresa.value || undefined, anio: fAnio.value, mes: fMes.value,
        quincena: fQuincena.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

// Modal detalle por trabajador
const mostrar = ref(false);
const sel = ref(null);
const form = useForm({ empresa_id: '', employee_id: '', filas: [] });
const trabajado = (e) => ['NORMAL','TRABAJO_SABADO','TRABAJO_DOMINGO','TRABAJO_FERIADO'].includes(e);
const diaSemana = (f) => ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][new Date(f + 'T00:00:00').getDay()];
// El backend/planilla usa horas_extra en horas decimales; en pantalla se escribe en minutos.
const horasExtraMin = (d) => (d.horas_extra ? Math.round(d.horas_extra * 60) : 0);
function setHorasExtraMin(d, min) {
    d.horas_extra = min ? Math.round((min / 60) * 100) / 100 : 0;
}
const minTexto = (min) => (min ? `${Math.floor(min / 60)}h ${min % 60}min` : '');
// Categoría del día según su estado, igual que la leyenda de Asistencia diaria:
// rojo = descuenta el día, verde = se paga igual, ámbar = día especial que paga extra.
const categoriaEstado = (estado) => {
    if (['FALTA', 'LICENCIA_SIN_GOCE'].includes(estado)) return 'rojo';
    if (['TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO'].includes(estado)) return 'ambar';
    if (estado !== 'NORMAL') return 'verde';
    return '';
};
const filaClase = (estado) => ({ rojo: 'bg-red-50', ambar: 'bg-amber-50', verde: 'bg-emerald-50/60' }[categoriaEstado(estado)] || '');
const estadoSelectClase = (estado) => ({
    rojo: 'border-red-300 bg-red-50 text-red-900 font-medium',
    ambar: 'border-amber-300 bg-amber-50 text-amber-900 font-medium',
    verde: 'border-emerald-300 bg-emerald-50 text-emerald-900 font-medium',
}[categoriaEstado(estado)] || '');

function abrirDetalle(fila) {
    sel.value = fila;
    form.empresa_id = fEmpresa.value;
    form.employee_id = fila.employee_id;
    form.filas = fila.dias.map((d) => ({ ...d }));
    form.clearErrors();
    mostrar.value = true;
}
function guardar() {
    form.post(route('asistencia.empleado-mes.guardar'), {
        preserveScroll: true,
        onSuccess: () => { mostrar.value = false; filtrar(); },
    });
}

const otrosTexto = (o) => Object.entries(o || {}).map(([k, n]) => `${n} ${k.toLowerCase().replaceAll('_', ' ')}`).join(', ');
const ctrl = 'h-10 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const inp = 'block w-full rounded-md border-gray-300 text-sm';
</script>

<template>
    <Head title="Consolidado de asistencia" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Consolidado de asistencia</h2>
                <a :href="route('asistencia.diario')" class="text-sm text-indigo-600 hover:text-indigo-900">Registro diario →</a>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                Revisa el mes completo de cada trabajador <b>antes de generar la planilla</b>. Haz <b>clic en un trabajador</b> para ver sus días y justificar/corregir tardanzas o faltas.
            </div>

            <!-- Filtros -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Empresa</label>
                        <select v-model="fEmpresa" @change="filtrar" :class="ctrl">
                            <option value="">— Selecciona empresa —</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Año</label>
                        <select v-model.number="fAnio" @change="filtrar" :class="ctrl">
                            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Mes</label>
                        <select v-model.number="fMes" @change="filtrar" :class="ctrl">
                            <option v-for="(n, i) in meses" :key="i" :value="i + 1">{{ n }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Periodo</label>
                        <select v-model="fQuincena" @change="filtrar" :class="ctrl">
                            <option value="">Mes completo</option>
                            <option value="1">1ra quincena</option>
                            <option value="2">2da quincena</option>
                        </select>
                    </div>
                </div>
                <div v-if="fEmpresa" class="mt-3 flex flex-wrap items-center gap-3">
                    <input v-model="buscar" type="search" placeholder="🔎 Buscar por DNI o nombre…" class="h-10 flex-1 rounded-md border-gray-300 text-sm shadow-sm sm:max-w-xs" />
                    <select v-model="tipoFiltro" class="h-10 rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Mostrar: todos</option>
                        <option value="faltas">Solo con faltas</option>
                        <option value="tardanzas">Solo con tardanzas</option>
                        <option value="he">Solo con horas extra</option>
                        <option value="otros">Solo con incidencias (vacaciones/licencia…)</option>
                    </select>
                    <span class="text-sm text-gray-500">{{ filasFiltradas.length }} de {{ filas.length }}</span>
                </div>
            </div>

            <div v-if="!fEmpresa" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                Elige una <b>empresa</b> para ver el resumen del mes.
            </div>

            <div v-else class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-3">DNI</th>
                            <th class="whitespace-nowrap px-3 py-3">Trabajador</th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">Tardanza<br><span class="text-[10px] normal-case text-gray-400">(min)</span></th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">H.E.<br><span class="text-[10px] normal-case text-gray-400">(min)</span></th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">H.E.<br><span class="text-[10px] normal-case text-gray-400">(días)</span></th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">Faltas</th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">Días<br><span class="text-[10px] normal-case text-gray-400">trab.</span></th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">Sábado</th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">Feriados<br><span class="text-[10px] normal-case text-gray-400">/dom.</span></th>
                            <th class="whitespace-nowrap px-2 py-3 text-center">Vacac.</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="f in filasFiltradas" :key="f.employee_id" class="cursor-pointer hover:bg-indigo-50" @click="abrirDetalle(f)">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ f.documento }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ f.empleado }}
                                <span v-if="f.importado" class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700" title="Datos del cuadro resumen importado de tu Excel">importado</span>
                                <span v-else-if="f.con_datos" class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-700" title="Registrado en el sistema día por día">sistema</span>
                            </td>
                            <td class="px-2 py-2 text-center"><span :class="f.tardanza_min ? 'font-semibold text-amber-600' : 'text-gray-300'">{{ f.tardanza_min }}</span></td>
                            <td class="px-2 py-2 text-center"><span :class="f.he_minutos ? 'font-semibold text-blue-600' : 'text-gray-300'">{{ f.he_minutos ?? 0 }}</span></td>
                            <td class="px-2 py-2 text-center"><span :class="f.he_dias ? 'font-semibold text-blue-600' : 'text-gray-300'">{{ f.he_dias ?? 0 }}</span></td>
                            <td class="px-2 py-2 text-center"><span :class="f.faltas ? 'font-semibold text-red-600' : 'text-gray-300'">{{ f.faltas }}</span></td>
                            <td class="px-2 py-2 text-center font-medium">{{ f.dias_trabajados }}</td>
                            <td class="px-2 py-2 text-center"><span :class="f.sabado ? 'font-semibold text-yellow-700' : 'text-gray-300'">{{ f.sabado ?? 0 }}</span></td>
                            <td class="px-2 py-2 text-center"><span :class="f.feriados_domingos ? 'font-semibold text-purple-700' : 'text-gray-300'">{{ f.feriados_domingos ?? 0 }}</span></td>
                            <td class="px-2 py-2 text-center"><span :class="f.vacaciones ? 'font-semibold text-emerald-700' : 'text-gray-300'">{{ f.vacaciones ?? 0 }}</span></td>
                            <td class="whitespace-nowrap px-3 py-2 text-right"><span class="text-indigo-600">👁 Ver días</span></td>
                        </tr>
                        <tr v-if="filasFiltradas.length === 0"><td colspan="11" class="px-4 py-6 text-center text-gray-500">Sin resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: días del trabajador (editable) -->
        <CrudModal :show="mostrar" max-width="5xl" :titulo="sel ? sel.empleado : 'Detalle'" @close="mostrar = false">
            <div v-if="sel" class="space-y-3">
                <p v-if="sel.importado" class="text-sm text-gray-500">📋 Datos del <b>cuadro resumen importado</b> de tu Excel (sin detalle día por día). DNI {{ sel.documento }} · {{ meses[fMes - 1] }} {{ fAnio }}.</p>
                <template v-else>
                <p class="text-sm text-gray-500">DNI {{ sel.documento }} · {{ meses[fMes - 1] }} {{ fAnio }}. Cambia el estado, pon la tardanza en 0 si está justificada, y anota el motivo en Observación.</p>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-red-50 border border-red-200"></span> Descuenta el día</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-emerald-50 border border-emerald-200"></span> Se paga igual</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-amber-50 border border-amber-200"></span> Día especial (paga extra)</span>
                </div>
                </template>
                <div class="max-h-[55vh] overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-3 py-2">Fecha</th><th class="px-3 py-2 w-72">Estado</th><th class="px-3 py-2 w-24">Tard. (min)</th><th class="px-3 py-2 w-24">H.E. (min)</th><th class="px-3 py-2">Observación</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(d, i) in form.filas" :key="i" :class="filaClase(d.estado)">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ diaSemana(d.fecha) }} {{ d.fecha }}</td>
                                <td class="px-3 py-2"><select v-model="d.estado" :class="[inp, estadoSelectClase(d.estado)]"><option v-for="(label, key) in estados" :key="key" :value="key">{{ label }}</option></select></td>
                                <td class="px-3 py-2">
                                    <input v-model.number="d.minutos_tarde" type="number" min="0" max="600" :disabled="!trabajado(d.estado)" :class="[inp, !trabajado(d.estado) && 'bg-gray-100']" />
                                    <span v-if="d.minutos_tarde" class="mt-0.5 block text-xs text-gray-400">{{ minTexto(d.minutos_tarde) }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <input :value="horasExtraMin(d)" @input="setHorasExtraMin(d, $event.target.valueAsNumber || 0)" type="number" min="0" max="720" :disabled="!trabajado(d.estado)" :class="[inp, !trabajado(d.estado) && 'bg-gray-100']" />
                                    <span v-if="horasExtraMin(d)" class="mt-0.5 block text-xs text-gray-400">{{ minTexto(horasExtraMin(d)) }}</span>
                                </td>
                                <td class="px-3 py-2"><input v-model="d.observacion" type="text" maxlength="255" :class="inp" placeholder="motivo / justificación" /></td>
                            </tr>
                            <tr v-if="form.filas.length === 0"><td colspan="5" class="px-3 py-6 text-center text-gray-500">Este trabajador no tiene días registrados en el mes.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-end gap-3 border-t pt-3">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button @click="guardar" :disabled="form.processing || form.filas.length === 0" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Guardar cambios</button>
                </div>
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
