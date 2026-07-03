<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    registros: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null, desde: '', hasta: '' }) },
    empresas: { type: Array, default: () => [] },
});

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeImportar = computed(() => permisos.value.includes('asistencia.sincronizar'));

const fEmpresa = ref(props.filtros.empresa_id ?? '');
const desde = ref(props.filtros.desde);
const hasta = ref(props.filtros.hasta);

const hoy = new Date();
const resumenForm = useForm({ empresa_id: '', anio: hoy.getFullYear(), mes: hoy.getMonth() + 1, quincena: '1', archivo: null });
const detalleForm = useForm({ empresa_id: '', archivo: null });

// Plantilla mensual (formato A) — descarga con filtros e importación
const descarga = ref({ empresa_id: '', anio: hoy.getFullYear(), mes: hoy.getMonth() + 1 });
const mensualForm = useForm({ archivo: null });
const anios = Array.from({ length: 6 }, (_, i) => 2026 + i); // 2026 en adelante

// Descarga con indicador de progreso (el Excel anual puede tardar ~20-30s).
const descargando = ref(false);
async function descargarArchivo(url, nombreFallback) {
    if (descargando.value) return;
    descargando.value = true;
    try {
        const resp = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/octet-stream' } });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const blob = await resp.blob();
        const cd = resp.headers.get('Content-Disposition');
        const m = cd && cd.match(/filename="?([^"]+)"?/);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = m ? m[1] : nombreFallback;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
    } catch (e) {
        alert('No se pudo descargar el archivo: ' + e.message);
    } finally {
        descargando.value = false;
    }
}

function descargarPlantillaMensual() {
    const q = { anio: descarga.value.anio, mes: descarga.value.mes };
    if (descarga.value.empresa_id) q.empresa_id = descarga.value.empresa_id;
    descargarArchivo(route('asistencia.plantilla-mensual', q), `ASISTENCIA_${descarga.value.anio}_${descarga.value.mes}.xlsx`);
}
function descargarPlantillaAnual() {
    descargarArchivo(route('asistencia.plantilla-anual', { anio: descarga.value.anio }), `ASISTENCIA_${descarga.value.anio}.xlsx`);
}
function importarMensual() {
    mensualForm.post(route('asistencia.import-mensual'), {
        preserveScroll: true,
        onSuccess: () => mensualForm.reset('archivo'),
    });
}

function filtrar() {
    router.get(route('asistencia.index'), {
        empresa_id: fEmpresa.value || undefined,
        desde: desde.value, hasta: hasta.value,
    }, { preserveState: true });
}
function importarResumen() {
    resumenForm.post(route('asistencia.import-resumen'), {
        preserveScroll: true,
        onSuccess: () => resumenForm.reset('archivo'),
    });
}
function importarDetalle() {
    detalleForm.post(route('asistencia.import-marcaciones'), {
        preserveScroll: true,
        onSuccess: () => detalleForm.reset('archivo'),
    });
}
const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const colorEstado = (e) => {
    if (e === 'NORMAL') return 'bg-green-100 text-green-800';
    if (e?.startsWith('FALTA')) return 'bg-red-100 text-red-800';
    if (['VACACIONES','LICENCIA','DESCANSO_MEDICO','SUBSIDIO'].includes(e)) return 'bg-blue-100 text-blue-800';
    return 'bg-gray-100 text-gray-700';
};
const origenLabel = (o) => ({ excel: '📄 Excel', manual: '✍️ Manual', biometrico: '🕒 Biométrico' }[o] ?? o ?? '—');
const selectCls = 'rounded-md border-gray-300 py-1.5 text-sm';
</script>

<template>
    <Head title="Asistencia" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Historial de asistencia</h2>
                <a :href="route('asistencia.diario')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Registro diario</a>
            </div>
        </template>
        <div class="p-6">
            <div class="space-y-6">

                <!-- Asistencia por Excel: descargar plantilla del año e importar -->
                <div v-if="puedeImportar" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <!-- Encabezado -->
                    <div class="flex flex-col gap-2 bg-gradient-to-r from-indigo-600 to-sky-500 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/20 text-2xl">📋</div>
                            <div>
                                <h3 class="text-lg font-semibold leading-tight text-white">Asistencia por Excel</h3>
                                <p class="text-sm text-indigo-100">Descarga la plantilla, llénala con las horas y súbela. El sistema calcula todo.</p>
                            </div>
                        </div>
                        <span class="hidden rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white sm:inline-block">Un solo archivo · 3 empresas · todo el año</span>
                    </div>

                    <!-- Cómo funciona (banda informativa) -->
                    <div class="border-b border-slate-100 bg-slate-50 px-6 py-3">
                        <p class="text-sm text-slate-600">
                            <b class="text-slate-700">¿Cómo funciona?</b>
                            Tú solo escribes la <b>entrada y salida</b> de cada trabajador. El sistema calcula automáticamente la
                            <b class="text-rose-600">tardanza</b> (según turno y tolerancia) y las <b class="text-emerald-600">horas extra</b> (incluido el medio día del sábado).
                            Puedes llenar hasta la quincena, importar, y luego completar el mes y <b>volver a subir el mismo archivo</b> — no se duplica.
                        </p>
                    </div>

                    <!-- 3 pasos -->
                    <div class="grid gap-4 p-6 lg:grid-cols-3">
                        <!-- Paso 1: Descargar -->
                        <div class="relative rounded-lg border border-slate-200 bg-white p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700">1</span>
                                <h4 class="text-sm font-semibold text-slate-800">Descargar plantilla</h4>
                            </div>
                            <div class="mb-3">
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Año</label>
                                <select v-model="descarga.anio" class="w-28 rounded-md border-slate-300 py-1.5 text-sm"><option v-for="a in anios" :key="a" :value="a">{{ a }}</option></select>
                            </div>
                            <button type="button" @click="descargarPlantillaAnual" :disabled="descargando" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
                                <span v-if="descargando" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                <span v-else>📥</span>
                                {{ descargando ? 'Generando… (puede tardar ~20s)' : 'Descargar Excel del año' }}
                            </button>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">Trae las <b>3 empresas</b> y <b>todo el año</b>. Es un archivo grande: la descarga puede tardar <b>unos segundos</b>. Para ver un solo mes, usa el filtro <b>▼ de la columna MES</b> dentro del Excel.</p>
                            <details class="mt-2 text-xs text-slate-400">
                                <summary class="cursor-pointer hover:text-slate-600">¿Prefieres solo un mes?</summary>
                                <div class="mt-2 space-y-2">
                                    <div><label class="block text-[11px] uppercase text-slate-400">Empresa</label>
                                        <select v-model="descarga.empresa_id" class="w-full rounded-md border-slate-300 py-1 text-xs"><option value="">Todas</option><option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option></select>
                                    </div>
                                    <div><label class="block text-[11px] uppercase text-slate-400">Mes</label>
                                        <select v-model="descarga.mes" class="w-full rounded-md border-slate-300 py-1 text-xs"><option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option></select>
                                    </div>
                                    <button type="button" @click="descargarPlantillaMensual" :disabled="descargando" class="w-full rounded-md bg-slate-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600 disabled:cursor-wait disabled:opacity-60">{{ descargando ? 'Generando…' : 'Descargar solo ese mes' }}</button>
                                </div>
                            </details>
                        </div>

                        <!-- Paso 2: Llenar -->
                        <div class="relative rounded-lg border border-slate-200 bg-white p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-700">2</span>
                                <h4 class="text-sm font-semibold text-slate-800">Llenar en Excel</h4>
                            </div>
                            <ul class="space-y-2 text-xs leading-relaxed text-slate-600">
                                <li class="flex gap-2"><span class="text-amber-500">•</span> Escribe la <b>ENTRADA</b> y <b>SALIDA</b> de cada día (ej. <code class="rounded bg-slate-100 px-1">08:00</code>).</li>
                                <li class="flex gap-2"><span class="text-amber-500">•</span> Si faltó o tuvo permiso, elige el <b>ESTADO</b> (Falta, Vacaciones, etc.).</li>
                                <li class="flex gap-2"><span class="text-amber-500">•</span> Marca <b>HE APROB = SI</b> solo si autorizas esas horas extra.</li>
                                <li class="flex gap-2"><span class="text-amber-500">•</span> Los días en blanco se ignoran (no pasa nada).</li>
                            </ul>
                            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-700">💡 Si al abrir el Excel sale una barra de seguridad, clic derecho al archivo → <b>Propiedades → Desbloquear</b> (solo la primera vez).</p>
                        </div>

                        <!-- Paso 3: Importar -->
                        <div class="relative rounded-lg border border-slate-200 bg-white p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-700">3</span>
                                <h4 class="text-sm font-semibold text-slate-800">Importar (subir archivo)</h4>
                            </div>
                            <form @submit.prevent="importarMensual" class="space-y-3">
                                <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center transition hover:border-sky-400 hover:bg-sky-50">
                                    <span class="text-2xl">📎</span>
                                    <span class="text-xs font-medium text-slate-600">{{ mensualForm.archivo ? mensualForm.archivo.name : 'Elige el Excel llenado' }}</span>
                                    <span class="text-[11px] text-slate-400">.xlsx · .xlsm · .csv</span>
                                    <input type="file" accept=".xlsx,.xlsm,.xls,.csv,.txt" @input="mensualForm.archivo = $event.target.files[0]" class="hidden" />
                                </label>
                                <button type="submit" :disabled="mensualForm.processing || !mensualForm.archivo"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span v-if="mensualForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                    <span>{{ mensualForm.processing ? 'Importando…' : 'Importar asistencia' }}</span>
                                </button>
                            </form>
                            <p v-if="mensualForm.errors.archivo" class="mt-2 text-xs font-medium text-rose-600">{{ mensualForm.errors.archivo }}</p>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">Empareja por <b>DNI</b>. Al terminar, revisa en <b>«Asistencia diaria»</b> o <b>«Resumen mensual»</b>.</p>
                        </div>
                    </div>
                </div>

                <!-- Filtro -->
                <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div><label class="block text-xs uppercase text-gray-500">Empresa</label><select v-model="fEmpresa" @change="filtrar" :class="selectCls"><option value="">Todas</option><option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option></select></div>
                    <div><label class="block text-xs uppercase text-gray-500">Desde</label><input v-model="desde" type="date" :class="selectCls" /></div>
                    <div><label class="block text-xs uppercase text-gray-500">Hasta</label><input v-model="hasta" type="date" :class="selectCls" /></div>
                    <button @click="filtrar" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filtrar</button>
                </div>

                <!-- Listado -->
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Trabajador</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Entrada</th><th class="px-4 py-3">Salida</th><th class="px-4 py-3">Tardanza</th><th class="px-4 py-3">H. Extra</th><th class="px-4 py-3">Aprob.</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="r in registros" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2"><span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{{ r.empresa ?? '—' }}</span></td>
                                <td class="px-4 py-2">{{ r.fecha }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ r.empleado }}</td>
                                <td class="px-4 py-2"><span :class="colorEstado(r.estado)" class="rounded-full px-2 py-1 text-xs">{{ r.estado }}</span></td>
                                <td class="px-4 py-2">{{ r.entrada }}</td>
                                <td class="px-4 py-2">{{ r.salida }}</td>
                                <td class="px-4 py-2" :class="r.minutos_tarde > 0 ? 'text-red-600 font-medium' : ''">{{ r.minutos_tarde }} min</td>
                                <td class="px-4 py-2">{{ r.horas_extra }}</td>
                                <td class="px-4 py-2">{{ Number(r.horas_extra) > 0 ? (r.he_aprobadas ? '✅' : '⏳') : '—' }}</td>
                            </tr>
                            <tr v-if="registros.length === 0"><td colspan="9" class="px-4 py-6 text-center text-gray-500">Sin registros en el rango.</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
