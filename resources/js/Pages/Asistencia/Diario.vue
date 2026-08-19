<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    fecha: String,
    feriado: { type: String, default: null },
    filas: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null, sede_id: null }) },
    estados: { type: Object, default: () => ({}) },
    empresas: { type: Array, default: () => [] },
    sedes: { type: Array, default: () => [] },
    turnos: { type: Array, default: () => [] },
});

const fEmpresa = ref(props.filtros.empresa_id ?? '');
const fSede = ref(props.filtros.sede_id ?? '');
const fModalidad = ref(props.filtros.modalidad ?? 'planilla');
const fecha = ref(props.fecha);
const mostrarEstados = ref(false);
const sedesFiltro = computed(() => props.sedes.filter((s) => !fEmpresa.value || String(s.empresa_id) === String(fEmpresa.value)));

const form = useForm({
    empresa_id: props.filtros.empresa_id ?? '',
    fecha: props.fecha,
    filas: props.filas.map((f) => ({ ...f })),
});

const trabajado = (estado) => ['NORMAL', 'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO'].includes(estado);

// Convierte "HH:mm" a minutos del día
function aMin(hhmm) {
    if (!hhmm) return null;
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
}
// Hora desde la que cuenta la tardanza (entrada del turno + tolerancia), para el tooltip.
function desdeTolerancia(f) {
    const m = aMin(f.turno_entrada);
    if (m === null) return '';
    const total = m + (f.turno_tolerancia || 0);
    return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
}

// El backend/planilla usa f.horas_extra en horas decimales; en pantalla RRHH escribe minutos.
function horasExtraMin(f) {
    return f.horas_extra ? Math.round(f.horas_extra * 60) : 0;
}
function setHorasExtraMin(f, min) {
    f.horas_extra = min ? Math.round((min / 60) * 100) / 100 : 0;
    if (!f.horas_extra) f.horas_extra_aprobadas = false;
}
function minTexto(min) {
    if (!min) return '';
    const h = Math.floor(min / 60), m = min % 60;
    return h ? `${h}h ${m}min` : `${m}min`;
}

function onEstadoChange(f) {
    f.observacion = f.estado === 'NORMAL' ? '' : (props.estados[f.estado] || '');
    recalc(f);
}

// Recalcula tardanza (min) y horas extra según el horario del turno y las horas marcadas.
// Tardanza y horas extra son totalmente independientes: una no compensa a la otra.
function recalc(f) {
    if (!trabajado(f.estado)) { f.minutos_tarde = 0; f.horas_extra = 0; f.horas_extra_aprobadas = false; return; }
    const ent = aMin(f.entrada), entTurno = aMin(f.turno_entrada);
    if (ent !== null && entTurno !== null) {
        const limite = entTurno + (f.turno_tolerancia || 0);
        f.minutos_tarde = ent > limite ? ent - limite : 0;
    }
    const sal = aMin(f.salida), salTurno = aMin(f.turno_salida);
    if (sal !== null && salTurno !== null) {
        f.horas_extra = sal > salTurno ? Math.round(((sal - salTurno) / 60) * 100) / 100 : 0;
        if (!f.horas_extra) f.horas_extra_aprobadas = false;
    }
}

function recargar() {
    router.get(route('asistencia.diario'), {
        empresa_id: fEmpresa.value || undefined,
        sede_id: fSede.value || undefined,
        modalidad: fModalidad.value,
        fecha: fecha.value,
    }, { preserveState: false });
}
function cambiarEmpresa() { fSede.value = ''; recargar(); }
function cambiarModalidad(m) { fModalidad.value = m; recargar(); }

function marcarTodosPresente() {
    form.filas.forEach((f) => { f.estado = 'NORMAL'; f.entrada = ''; f.salida = ''; f.minutos_tarde = 0; f.horas_extra = 0; f.horas_extra_aprobadas = false; });
}

// Resalta un momento las filas que sí cambiaron al guardar, para confirmar
// visualmente cuáles se actualizaron sin mover el scroll.
const baseline = ref(new Map(props.filas.map((f) => [f.employee_id, JSON.stringify(f)])));
const resaltados = ref(new Set());
let resaltadoTimer = null;

function guardar() {
    form.empresa_id = fEmpresa.value;
    form.fecha = fecha.value;
    form.post(route('asistencia.diario.guardar'), {
        preserveScroll: true,
        onSuccess: () => {
            const cambiados = new Set();
            form.filas.forEach((f) => {
                const snap = JSON.stringify(f);
                if (baseline.value.get(f.employee_id) !== snap) cambiados.add(f.employee_id);
                baseline.value.set(f.employee_id, snap);
            });
            resaltados.value = cambiados;
            clearTimeout(resaltadoTimer);
            resaltadoTimer = setTimeout(() => { resaltados.value = new Set(); }, 3000);

            if (cambiados.size) {
                const primerId = [...cambiados][0];
                nextTick(() => {
                    document.querySelector(`[data-empleado-id="${primerId}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
        },
    });
}

const inp = 'block w-full rounded-md border-gray-300 text-sm';
const selectCls = 'rounded-md border-gray-300 py-1.5 text-sm';
</script>

<template>
    <Head title="Registro diario de asistencia" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Registro diario de asistencia</h2>
                <a :href="route('asistencia.index')" class="text-sm text-indigo-600 hover:text-indigo-900">Ver historial / importar →</a>
            </div>
        </template>

        <div class="p-6">
            <div class="space-y-4">
                <div class="relative rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                    Todos aparecen como <b>Presente</b> por defecto. Pon la <b>hora de entrada/salida</b> (la tardanza se calcula sola contra la hora de entrada del turno, al minuto exacto), o cambia el estado a quien faltó. Marca <b>HE aprob.</b> solo si el supervisor aprobó las horas extra. Luego guarda.
                    <div class="mt-2">
                        <button
                            type="button"
                            class="cursor-pointer font-semibold text-blue-900 hover:underline"
                            @click="mostrarEstados = !mostrarEstados"
                        >
                            📖 ¿Qué significa cada estado? (clic para ver)
                        </button>
                        <div
                            v-if="mostrarEstados"
                            class="absolute left-0 right-0 z-10 mt-2 grid grid-cols-1 gap-4 rounded-lg border border-blue-200 bg-white p-4 text-xs shadow-lg md:grid-cols-3"
                        >
                            <div class="rounded-md bg-red-50 p-3">
                                <div class="mb-1 font-bold text-red-700">🔴 Descuentan el día (no se paga)</div>
                                <p class="text-red-900"><b>Falta:</b> no vino y no justificó.</p>
                                <p class="text-red-900"><b>Licencia sin goce:</b> permiso acordado pero sin pago.</p>
                            </div>
                            <div class="rounded-md bg-green-50 p-3">
                                <div class="mb-1 font-bold text-green-700">🟢 El día se paga igual</div>
                                <p class="text-green-900"><b>Falta justificada:</b> justificación aceptada.</p>
                                <p class="text-green-900"><b>Vacaciones:</b> goce vacacional.</p>
                                <p class="text-green-900"><b>Licencia con goce:</b> permiso pagado por ley (fallecimiento, paternidad…).</p>
                                <p class="text-green-900"><b>Descanso médico:</b> incapacidad con certificado (primeros 20 días/año los paga la empresa).</p>
                                <p class="text-green-900"><b>Subsidio:</b> desde el día 21 lo paga EsSalud.</p>
                                <p class="text-green-900"><b>Descanso:</b> su día libre de la semana (ej. rotativo de vigilancia).</p>
                                <p class="text-green-900"><b>Licencia hijo enfermo:</b> ley de cuidado familiar.</p>
                                <p class="text-green-900"><b>Feriado (descansó):</b> era feriado y se quedó en casa — se paga por ley.</p>
                            </div>
                            <div class="rounded-md bg-amber-50 p-3">
                                <div class="mb-1 font-bold text-amber-700">🟡 Vino en día especial (paga EXTRA)</div>
                                <p class="text-amber-900"><b>Trabajó sábado:</b> vino un sábado que no le tocaba.</p>
                                <p class="text-amber-900"><b>Trabajó domingo:</b> vino en su descanso dominical.</p>
                                <p class="text-amber-900"><b>Trabajó feriado:</b> vino en feriado — cobra el día más la sobretasa.</p>
                                <p class="mt-1 text-amber-800">💡 El monto extra de estos días se registra en <b>«Pagos adicionales»</b>.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modalidad: el cliente no quiere ver Planilla y Honorarios juntos -->
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="cambiarModalidad('planilla')"
                        class="rounded-md px-4 py-2 text-sm font-semibold"
                        :class="fModalidad === 'planilla' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    >
                        👷 Planilla
                    </button>
                    <button
                        type="button"
                        @click="cambiarModalidad('honorarios')"
                        class="rounded-md px-4 py-2 text-sm font-semibold"
                        :class="fModalidad === 'honorarios' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    >
                        🧾 Honorarios (RxH)
                    </button>
                </div>

                <!-- Filtros -->
                <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Empresa *</label>
                        <select v-model="fEmpresa" @change="cambiarEmpresa" :class="selectCls">
                            <option value="">— Selecciona empresa —</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Sede</label>
                        <select v-model="fSede" @change="recargar" :class="selectCls" :disabled="!fEmpresa">
                            <option value="">Todas las sedes</option>
                            <option v-for="s in sedesFiltro" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Fecha</label>
                        <input v-model="fecha" type="date" :class="selectCls" @change="recargar" />
                    </div>
                    <button v-if="fEmpresa" type="button" @click="marcarTodosPresente" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Marcar todos presentes</button>
                </div>

                <div v-if="!fEmpresa" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    Elige una <b>empresa</b> arriba para registrar la asistencia del día.
                </div>

                <template v-else>
                    <div v-if="feriado" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-900">
                        🎉 <b>Hoy es feriado: {{ feriado }}.</b> A los que descansaron márcalos <b>"Feriado (descansó)"</b>; a los que vinieron a trabajar, <b>"Trabajó feriado (paga extra)"</b>.
                    </div>
                    <div v-if="turnos.length" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border-2 border-indigo-200 bg-indigo-50 px-4 py-3 text-sm">
                        <span class="font-bold text-indigo-900">🕒 Turnos:</span>
                        <span v-for="t in turnos" :key="t.id" class="rounded-full border border-indigo-300 bg-white px-3 py-1 font-medium text-indigo-800">{{ t.nombre }}</span>
                    </div>

                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">DNI</th>
                                    <th class="px-4 py-3">Trabajador</th>
                                    <th class="px-4 py-3 w-32">Turno</th>
                                    <th class="px-4 py-3 w-44">Estado</th>
                                    <th class="px-4 py-3 w-28">Entrada</th>
                                    <th class="px-4 py-3 w-28">Salida</th>
                                    <th class="px-4 py-3 w-28">Tardanza (min)</th>
                                    <th class="px-4 py-3 w-28">Horas extra (min)</th>
                                    <th class="px-4 py-3 w-20 text-center">HE aprob.</th>
                                    <th class="px-4 py-3">Observación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(f, i) in form.filas" :key="f.employee_id" :data-empleado-id="f.employee_id" class="transition-colors duration-1000" :class="resaltados.has(f.employee_id) ? 'bg-emerald-200' : (f.estado !== 'NORMAL' ? 'bg-amber-50/40' : '')">
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ f.documento }}</td>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ f.empleado }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="f.turno_entrada" class="whitespace-nowrap rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700" :title="(f.turno_nombre ?? '') + ' · tolerancia ' + (f.turno_tolerancia ?? 0) + ' min (tardanza desde las ' + desdeTolerancia(f) + ')'">
                                            {{ f.turno_entrada }}–{{ f.turno_salida }}
                                        </span>
                                        <span v-else class="rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-red-600" title="Sin turno asignado: no se puede calcular la tardanza automáticamente">sin turno</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <select v-model="f.estado" @change="onEstadoChange(f)" :class="inp">
                                            <option v-for="(label, key) in estados" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2"><input v-model="f.entrada" @input="recalc(f)" type="time" :disabled="!trabajado(f.estado)" :class="[inp, !trabajado(f.estado) && 'bg-gray-100']" /></td>
                                    <td class="px-4 py-2"><input v-model="f.salida" @input="recalc(f)" type="time" :disabled="!trabajado(f.estado)" :class="[inp, !trabajado(f.estado) && 'bg-gray-100']" /></td>
                                    <td class="px-4 py-2">
                                        <input v-model.number="f.minutos_tarde" type="number" min="0" max="600" :disabled="!trabajado(f.estado) || !!f.entrada" :title="f.entrada ? 'Se calcula solo desde la hora de entrada' : ''" :class="[inp, (!trabajado(f.estado) || !!f.entrada) && 'bg-gray-100']" />
                                        <span v-if="f.minutos_tarde" class="mt-0.5 block text-xs text-gray-400">{{ minTexto(f.minutos_tarde) }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input :value="horasExtraMin(f)" @input="setHorasExtraMin(f, $event.target.valueAsNumber || 0)" type="number" min="0" max="720" :disabled="!trabajado(f.estado) || !!f.salida" :title="f.salida ? 'Se calcula solo desde la hora de salida' : ''" :class="[inp, (!trabajado(f.estado) || !!f.salida) && 'bg-gray-100']" />
                                        <span v-if="horasExtraMin(f)" class="mt-0.5 block text-xs text-gray-400">{{ minTexto(horasExtraMin(f)) }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-center"><input v-model="f.horas_extra_aprobadas" type="checkbox" :disabled="!trabajado(f.estado) || !f.horas_extra" class="rounded" /></td>
                                    <td class="px-4 py-2"><input v-model="f.observacion" type="text" maxlength="255" :class="inp" placeholder="opcional" /></td>
                                </tr>
                                <tr v-if="form.filas.length === 0">
                                    <td colspan="10" class="px-4 py-6 text-center text-gray-500">No hay empleados activos en esta empresa/sede.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Guardado ✓</span>
                        <button @click="guardar" :disabled="form.processing || form.filas.length === 0" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Guardar asistencia del día</button>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
