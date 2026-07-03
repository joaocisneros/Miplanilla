<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    fecha: String,
    filas: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null, sede_id: null }) },
    estados: { type: Object, default: () => ({}) },
    empresas: { type: Array, default: () => [] },
    sedes: { type: Array, default: () => [] },
});

const fEmpresa = ref(props.filtros.empresa_id ?? '');
const fSede = ref(props.filtros.sede_id ?? '');
const fecha = ref(props.fecha);
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
// Recalcula tardanza (min) y horas extra según el horario del turno y las horas marcadas.
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
        fecha: fecha.value,
    }, { preserveState: false });
}
function cambiarEmpresa() { fSede.value = ''; recargar(); }

function marcarTodosPresente() {
    form.filas.forEach((f) => { f.estado = 'NORMAL'; f.entrada = ''; f.salida = ''; f.minutos_tarde = 0; f.horas_extra = 0; f.horas_extra_aprobadas = false; });
}
function guardar() {
    form.empresa_id = fEmpresa.value;
    form.fecha = fecha.value;
    form.post(route('asistencia.diario.guardar'), { preserveScroll: true });
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
                    <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                        Todos aparecen como <b>Presente</b> por defecto. Pon la <b>hora de entrada/salida</b> (la tardanza se calcula sola con el turno + 20 min de tolerancia), o cambia el estado a quien faltó. Marca <b>HE aprob.</b> solo si el supervisor aprobó las horas extra. Luego guarda.
                    </div>

                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">DNI</th>
                                    <th class="px-4 py-3">Trabajador</th>
                                    <th class="px-4 py-3 w-44">Estado</th>
                                    <th class="px-4 py-3 w-28">Entrada</th>
                                    <th class="px-4 py-3 w-28">Salida</th>
                                    <th class="px-4 py-3 w-28">Tardanza (min)</th>
                                    <th class="px-4 py-3 w-28">Horas extra</th>
                                    <th class="px-4 py-3 w-20 text-center">HE aprob.</th>
                                    <th class="px-4 py-3">Observación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(f, i) in form.filas" :key="f.employee_id" :class="f.estado !== 'NORMAL' ? 'bg-amber-50/40' : ''">
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ f.documento }}</td>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ f.empleado }}</td>
                                    <td class="px-4 py-2">
                                        <select v-model="f.estado" @change="recalc(f)" :class="inp">
                                            <option v-for="(label, key) in estados" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2"><input v-model="f.entrada" @input="recalc(f)" type="time" :disabled="!trabajado(f.estado)" :class="[inp, !trabajado(f.estado) && 'bg-gray-100']" /></td>
                                    <td class="px-4 py-2"><input v-model="f.salida" @input="recalc(f)" type="time" :disabled="!trabajado(f.estado)" :class="[inp, !trabajado(f.estado) && 'bg-gray-100']" /></td>
                                    <td class="px-4 py-2"><input v-model.number="f.minutos_tarde" type="number" min="0" max="600" :disabled="!trabajado(f.estado) || !!f.entrada" :title="f.entrada ? 'Se calcula solo desde la hora de entrada' : ''" :class="[inp, (!trabajado(f.estado) || !!f.entrada) && 'bg-gray-100']" /></td>
                                    <td class="px-4 py-2"><input v-model.number="f.horas_extra" type="number" min="0" max="12" step="0.5" :disabled="!trabajado(f.estado) || !!f.salida" :title="f.salida ? 'Se calcula solo desde la hora de salida' : ''" :class="[inp, (!trabajado(f.estado) || !!f.salida) && 'bg-gray-100']" /></td>
                                    <td class="px-4 py-2 text-center"><input v-model="f.horas_extra_aprobadas" type="checkbox" :disabled="!trabajado(f.estado) || !f.horas_extra" class="rounded" /></td>
                                    <td class="px-4 py-2"><input v-model="f.observacion" type="text" maxlength="255" :class="inp" placeholder="opcional" /></td>
                                </tr>
                                <tr v-if="form.filas.length === 0">
                                    <td colspan="9" class="px-4 py-6 text-center text-gray-500">No hay empleados activos en esta empresa/sede.</td>
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
