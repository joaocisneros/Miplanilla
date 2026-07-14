<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ feriados: { type: Array, default: () => [] } });

const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
const hoy = new Date().toISOString().slice(0, 10);
const fechaCorta = (f) => (f ?? '').substring(0, 10);

// Mapa fecha => {id, nombre} y años disponibles
const mapa = computed(() => {
    const m = {};
    for (const f of props.feriados) m[fechaCorta(f.fecha)] = f;
    return m;
});
const anios = computed(() => {
    const s = [...new Set(props.feriados.map((f) => fechaCorta(f.fecha).substring(0, 4)))].sort();
    return s.length ? s : [String(new Date().getFullYear())];
});
const anioSel = ref(String(new Date().getFullYear()));
if (!anios.value.includes(anioSel.value)) anioSel.value = anios.value[0];

const delAnio = computed(() => props.feriados.filter((f) => fechaCorta(f.fecha).startsWith(anioSel.value + '-')));

// ---- Mini calendarios (12 meses del año elegido) ----
const nombresMes = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const cabDias = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
function celdasMes(anio, mes) {
    // celdas del mes (lunes primero): nulls de relleno + días
    const primero = new Date(anio, mes, 1);
    const nDias = new Date(anio, mes + 1, 0).getDate();
    const offset = (primero.getDay() + 6) % 7; // 0=lunes
    const celdas = Array(offset).fill(null);
    for (let d = 1; d <= nDias; d++) celdas.push(d);
    return celdas;
}
const fkey = (mes, d) => `${anioSel.value}-${String(mes + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

// ---- CRUD ----
const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ fecha: '', nombre: '' });
function abrirNuevo(fechaPre = '') { editandoId.value = null; form.reset(); form.fecha = fechaPre; form.clearErrors(); mostrar.value = true; }
function abrirEditar(f) { editandoId.value = f.id; form.fecha = fechaCorta(f.fecha); form.nombre = f.nombre; form.clearErrors(); mostrar.value = true; }
function clicDia(mes, d) {
    const k = fkey(mes, d);
    mapa.value[k] ? abrirEditar(mapa.value[k]) : abrirNuevo(k);
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.feriados.update', editandoId.value), opts) : form.post(route('admin.feriados.store'), opts);
}
function eliminar() {
    if (confirm(`¿Eliminar el feriado "${form.nombre}" (${form.fecha})?`)) {
        router.delete(route('admin.feriados.destroy', editandoId.value), { preserveScroll: true, onSuccess: () => { mostrar.value = false; } });
    }
}

// ---- Generar año completo (fijos + Semana Santa calculada) ----
function generarAnio() {
    const sugerido = String(Math.max(...anios.value.map(Number)) + 1);
    const anio = prompt('¿Qué año quieres generar?\n\nSe crean los feriados fijos del Perú y se calcula Semana Santa automáticamente.', sugerido);
    if (!anio) return;
    router.post(route('admin.feriados.generar'), { anio }, { preserveScroll: true, onSuccess: () => { anioSel.value = String(anio); } });
}
</script>

<template>
    <Head title="Feriados" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Feriados</h2>
                <div class="flex items-center gap-2">
                    <button @click="generarAnio" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" title="Crea los feriados fijos del año y calcula Semana Santa">⚙ Generar año</button>
                    <button @click="abrirNuevo()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Feriado</button>
                </div>
            </div>
        </template>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Año</label>
                        <select v-model="anioSel" class="rounded-md border-gray-300 py-1.5 text-sm">
                            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div class="text-sm text-gray-500">{{ delAnio.length }} feriado(s) en {{ anioSel }}</div>
                    <div class="ml-auto text-xs text-gray-400">💡 Haz clic en un día del calendario para agregar o editar un feriado.</div>
                </div>

                <!-- Calendario anual -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <div v-for="(nm, mes) in nombresMes" :key="mes" class="rounded-lg bg-white p-3 shadow-sm">
                        <div class="mb-2 text-center text-sm font-bold text-gray-700">{{ nm }}</div>
                        <div class="grid grid-cols-7 gap-0.5 text-center text-[11px]">
                            <div v-for="(c, i) in cabDias" :key="'c' + i" class="py-0.5 font-semibold" :class="i === 6 ? 'text-red-400' : 'text-gray-400'">{{ c }}</div>
                            <template v-for="(d, i) in celdasMes(Number(anioSel), mes)" :key="i">
                                <div v-if="d === null"></div>
                                <button v-else @click="clicDia(mes, d)"
                                    class="mx-auto flex h-6 w-6 items-center justify-center rounded-full tabular-nums transition"
                                    :class="[
                                        mapa[fkey(mes, d)] ? 'bg-indigo-600 font-bold text-white hover:bg-indigo-700' : 'text-gray-600 hover:bg-gray-100',
                                        fkey(mes, d) === hoy && !mapa[fkey(mes, d)] ? 'ring-2 ring-indigo-400' : '',
                                    ]"
                                    :title="mapa[fkey(mes, d)]?.nombre ?? ''">{{ d }}</button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Lista del año -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-2">Fecha</th><th class="px-4 py-2">Día</th><th class="px-4 py-2">Feriado</th><th class="px-4 py-2 text-right">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="f in delAnio" :key="f.id" class="hover:bg-indigo-50/40" :class="fechaCorta(f.fecha) < hoy ? 'opacity-50' : ''">
                                <td class="whitespace-nowrap px-4 py-1.5 font-semibold tabular-nums text-gray-800">{{ fechaCorta(f.fecha) }}</td>
                                <td class="px-4 py-1.5 text-gray-500">{{ new Date(fechaCorta(f.fecha) + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'long' }) }}</td>
                                <td class="px-4 py-1.5 font-medium text-gray-900">{{ f.nombre }}</td>
                                <td class="whitespace-nowrap px-4 py-1.5 text-right">
                                    <button @click="abrirEditar(f)" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-100" title="Editar">✏️</button>
                                </td>
                            </tr>
                            <tr v-if="delAnio.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin feriados en {{ anioSel }} — usa "⚙ Generar año".</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <CrudModal :show="mostrar" max-width="md" :titulo="editandoId ? 'Editar feriado' : 'Nuevo feriado'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="space-y-4">
                <div>
                    <label class="text-sm text-gray-700">Fecha *</label>
                    <input v-model="form.fecha" type="date" :class="inp" />
                    <p v-if="form.errors.fecha" class="text-xs text-red-600">{{ form.errors.fecha }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Nombre *</label>
                    <input v-model="form.nombre" type="text" placeholder="Fiestas Patrias" :class="inp" />
                    <p v-if="form.errors.nombre" class="text-xs text-red-600">{{ form.errors.nombre }}</p>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button v-if="editandoId" type="button" @click="eliminar" class="rounded-md bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">🗑 Eliminar</button>
                    <div class="ml-auto flex gap-3">
                        <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar</button>
                    </div>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
