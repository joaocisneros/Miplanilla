<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import BotonAccion from '@/Components/BotonAccion.vue';
import EmpleadoForm from '@/Components/EmpleadoForm.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    empleados: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null, sede_id: null }) },
    empresas: { type: Array, default: () => [] },
    sedes: { type: Array, default: () => [] },
    areas: Array, cargos: Array, turnos: Array, tiposContrato: Array,
});

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeGestionar = computed(() => permisos.value.includes('empleados.gestionar'));

// Alerta de vencimiento de contratos a plazo fijo
function badgeVenc(dias) {
    if (dias < 0) return { texto: 'Vencido', clase: 'bg-red-100 text-red-800' };
    if (dias <= 30) return { texto: `Vence en ${dias} d`, clase: 'bg-amber-100 text-amber-800' };
    return { texto: 'Plazo fijo', clase: 'bg-gray-100 text-gray-600' };
}
const contratosPorVencer = computed(() =>
    props.empleados.filter((e) => e.dias_vencimiento !== null && e.dias_vencimiento <= 30)
        .sort((a, b) => a.dias_vencimiento - b.dias_vencimiento)
);

// Filtros
const fEmpresa = ref(props.filtros.empresa_id ?? '');
const fSede = ref(props.filtros.sede_id ?? '');
const sedesFiltro = computed(() => props.sedes.filter((s) => !fEmpresa.value || String(s.empresa_id) === String(fEmpresa.value)));

function filtrar() {
    router.get(route('empleados.index'), { empresa_id: fEmpresa.value || undefined, sede_id: fSede.value || undefined }, { preserveState: true, preserveScroll: true });
}
function cambiarEmpresaFiltro() {
    fSede.value = '';
    filtrar();
}

// Buscador + filtros client-side (sobre la lista ya cargada de la empresa elegida)
const q = ref('');
const fArea = ref('');
const fCargo = ref('');
const fEstado = ref('activo');
const fModalidad = ref('');
const areasUnicas = computed(() => [...new Set(props.empleados.map((e) => e.area).filter(Boolean))].sort());
const cargosUnicos = computed(() => [...new Set(props.empleados.map((e) => e.cargo).filter(Boolean))].sort());
const empleadosFiltrados = computed(() => props.empleados.filter((e) => {
    if (q.value) {
        const s = q.value.toLowerCase();
        if (!`${e.nombre_completo} ${e.numero_documento}`.toLowerCase().includes(s)) return false;
    }
    if (fArea.value && e.area !== fArea.value) return false;
    if (fCargo.value && e.cargo !== fCargo.value) return false;
    if (fEstado.value === 'activo' && !e.activo) return false;
    if (fEstado.value === 'cesado' && e.activo) return false;
    if (fModalidad.value && (e.modalidad || 'planilla') !== fModalidad.value) return false;
    return true;
}));

function exportar() {
    window.location.href = route('empleados.export', {
        empresa_id: fEmpresa.value || undefined,
        q: q.value || undefined,
        area: fArea.value || undefined,
        cargo: fCargo.value || undefined,
        estado: fEstado.value || undefined,
        modalidad: fModalidad.value || undefined,
    });
}

const mostrar = ref(false);
const modo = ref('create');
const seleccionado = ref(null);
const formKey = ref(0);

function abrirNuevo() {
    modo.value = 'create';
    seleccionado.value = null;
    formKey.value++;
    mostrar.value = true;
}
function abrirEditar(emp) {
    modo.value = 'edit';
    seleccionado.value = emp;
    formKey.value++;
    mostrar.value = true;
}
function onGuardado() {
    mostrar.value = false;
}

// Documentos
const mostrarDocs = ref(false);
const empDocs = ref(null);
const docForm = useForm({ tipo: 'ficha_firmada', archivo: null });
function abrirDocs(emp) {
    empDocs.value = emp;
    docForm.reset();
    docForm.tipo = 'ficha_firmada';
    mostrarDocs.value = true;
}
function subirDoc() {
    docForm.post(route('empleados.documentos.subir', empDocs.value.id), {
        preserveScroll: true,
        onSuccess: () => { docForm.reset('archivo'); mostrarDocs.value = false; },
    });
}
function eliminarDoc(doc) {
    if (confirm(`¿Eliminar "${doc.nombre_original}"?`)) {
        router.delete(route('empleados.documentos.eliminar', doc.id), { preserveScroll: true });
    }
}
const tipoDoc = (t) => ({ ficha_firmada: '📋 Ficha firmada', dni: '🪪 DNI', contrato: '📄 Contrato', otro: '📎 Otro' }[t] ?? t);

function eliminar(emp) {
    if (confirm(`¿Eliminar a ${emp.nombre_completo}?`)) {
        router.delete(route('empleados.destroy', emp.id), { preserveScroll: true });
    }
}
function cesar(emp) {
    const msg = emp.activo
        ? `¿Cesar a ${emp.nombre_completo}?\n\nYa no aparecerá en nuevas planillas, pero se conserva TODO su historial (boletas, contrato, ficha). Si vuelve, lo reactivas con un clic.`
        : `¿Reactivar a ${emp.nombre_completo}? Volverá a aparecer en la planilla.`;
    if (confirm(msg)) {
        router.patch(route('empleados.estado', emp.id), {}, { preserveScroll: true });
    }
}
const money = (v) => v != null ? 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }) : '—';
const selectCls = 'rounded-md border-gray-300 py-1.5 text-sm';
</script>

<template>
    <Head title="Empleados" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Empleados</h2>
                <button v-if="puedeGestionar" @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo empleado</button>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <!-- Filtros -->
            <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                <div class="min-w-[220px] flex-1">
                    <label class="block text-xs uppercase text-gray-500">Buscar</label>
                    <input v-model="q" type="text" placeholder="Nombre o DNI…" class="w-full rounded-md border-gray-300 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="block text-xs uppercase text-gray-500">Empresa</label>
                    <select v-model="fEmpresa" @change="cambiarEmpresaFiltro" :class="selectCls">
                        <option value="">Todas</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase text-gray-500">Área</label>
                    <select v-model="fArea" :class="selectCls">
                        <option value="">Todas</option>
                        <option v-for="a in areasUnicas" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase text-gray-500">Cargo</label>
                    <select v-model="fCargo" :class="selectCls">
                        <option value="">Todos</option>
                        <option v-for="c in cargosUnicos" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase text-gray-500">Estado</label>
                    <select v-model="fEstado" :class="selectCls">
                        <option value="">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="cesado">Cesados</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase text-gray-500">Modalidad</label>
                    <select v-model="fModalidad" :class="selectCls">
                        <option value="">Todos</option>
                        <option value="planilla">👷 Planilla</option>
                        <option value="honorarios">🧾 Honorarios (RxH)</option>
                    </select>
                </div>
                <button type="button" @click="exportar" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">📥 Exportar Excel</button>
                <div class="ml-auto self-center text-sm text-gray-500">{{ empleadosFiltrados.length }} de {{ empleados.length }}</div>
            </div>

            <div v-if="contratosPorVencer.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                ⚠️ <b>{{ contratosPorVencer.length }} contrato(s) a plazo fijo por vencer o vencidos.</b>
                Revisa si toca <b>renovar</b> o <b>cesar</b>:
                <span v-for="(e, i) in contratosPorVencer" :key="e.id">
                    {{ e.nombre_completo }} ({{ e.dias_vencimiento < 0 ? 'vencido' : 'en ' + e.dias_vencimiento + ' días' }}){{ i < contratosPorVencer.length - 1 ? ',' : '.' }}
                </span>
            </div>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2">DNI / Documento</th>
                            <th class="px-3 py-2">Nombres y apellidos</th>
                            <th class="px-3 py-2">Empresa</th>
                            <th class="px-3 py-2">Cargo</th>
                            <th class="px-3 py-2">Horario</th>
                            <th class="px-3 py-2">Sueldo</th>
                            <th class="px-3 py-2">Pensión</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="emp in empleadosFiltrados" :key="emp.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-2 text-[13px] font-medium text-gray-900">{{ emp.numero_documento }}</td>
                            <td class="px-3 py-2 text-[13px] text-gray-700">
                                {{ emp.nombre_completo }}
                                <span v-if="emp.dias_vencimiento !== null" :class="badgeVenc(emp.dias_vencimiento).clase" class="ml-1 whitespace-nowrap rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :title="'Contrato vence el ' + emp.fecha_cese">{{ badgeVenc(emp.dias_vencimiento).texto }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2"><span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">{{ emp.empresa ?? '—' }}</span></td>
                            <td class="px-3 py-2 text-[13px] leading-tight text-gray-700">
                                <div class="truncate">{{ emp.cargo ?? '—' }}</div>
                                <div class="truncate text-[10px] uppercase tracking-wide text-gray-400">{{ emp.area ?? '' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span v-if="emp.turno" class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700" :title="emp.turno">{{ emp.turno_horario ?? emp.turno }}</span>
                                <span v-else class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600" title="Falta asignar turno/horario">⚠ Sin horario</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-[13px] tabular-nums text-gray-700">{{ money(emp.sueldo_basico) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-[13px] text-gray-700">
                                {{ emp.sistema_pensiones ?? '—' }}
                                <span v-if="emp.sistema_pensiones === 'JUBILADO' && emp.afp" class="text-[11px] text-gray-400" :title="'Antes de jubilarse correspondía a ' + emp.afp">(correspondía: {{ emp.afp === 'ONP' ? 'ONP' : 'AFP ' + emp.afp }})</span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <a :href="route('empleados.ficha', emp.id)" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100" title="Generar ficha PDF para imprimir y firmar">🖨 Ficha</a>
                                    <a :href="route('empleados.contrato', emp.id)" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-sky-50 px-2 py-1 text-[11px] font-semibold text-sky-700 hover:bg-sky-100" title="Generar contrato PDF para imprimir y firmar">📄 Contrato</a>
                                    <button @click="abrirDocs(emp)" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-200" title="Documentos archivados">📎 Docs<span v-if="emp.documentos?.length" class="ml-0.5 rounded-full bg-gray-700 px-1 text-white">{{ emp.documentos.length }}</span></button>
                                    <button v-if="puedeGestionar" @click="abrirEditar(emp)" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100">✎ Editar</button>
                                    <button v-if="puedeGestionar && emp.activo" @click="cesar(emp)" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100" title="Cesar: deja de aparecer en la planilla, se conserva su historial">🚫 Cesar</button>
                                    <button v-if="puedeGestionar && !emp.activo" @click="cesar(emp)" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-green-50 px-2 py-1 text-[11px] font-semibold text-green-700 hover:bg-green-100" title="Reactivar: vuelve a la planilla">✓ Activar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="empleadosFiltrados.length === 0">
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500">No hay empleados con ese filtro/búsqueda.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" max-width="5xl" :titulo="modo === 'edit' ? 'Editar empleado' : 'Nuevo empleado'" @close="mostrar = false">
            <div class="max-h-[75vh] overflow-y-auto pr-1">
                <EmpleadoForm
                    :key="formKey"
                    :empresas="empresas" :sedes="sedes" :areas="areas" :cargos="cargos" :turnos="turnos" :tipos-contrato="tiposContrato"
                    :empresa-id-inicial="fEmpresa"
                    :empleado="seleccionado?.empleado ?? null"
                    :contrato="seleccionado?.contrato ?? null"
                    :derechohabientes="seleccionado?.derechohabientes ?? []"
                    :modo="modo"
                    @guardado="onGuardado"
                    @cancelar="mostrar = false"
                />
            </div>
        </CrudModal>

        <!-- Modal Documentos -->
        <CrudModal :show="mostrarDocs" max-width="2xl" :titulo="empDocs ? ('Documentos — ' + empDocs.nombre_completo) : 'Documentos'" @close="mostrarDocs = false">
            <div v-if="empDocs" class="space-y-4 text-sm">
                <div class="rounded-md bg-emerald-50 p-3 text-emerald-800">
                    Paso 1: <a :href="route('empleados.ficha', empDocs.id)" class="font-semibold underline">🖨 Descargar ficha PDF</a> → imprimir → el trabajador firma. &nbsp; Paso 2: escanear y subir abajo. 📁
                </div>

                <!-- Subir -->
                <form v-if="puedeGestionar" @submit.prevent="subirDoc" class="flex flex-wrap items-end gap-3 rounded-lg border p-3">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Tipo</label>
                        <select v-model="docForm.tipo" class="mt-1 rounded-md border-gray-300 text-sm">
                            <option value="ficha_firmada">Ficha firmada</option>
                            <option value="dni">DNI</option>
                            <option value="contrato">Contrato</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Archivo (PDF/foto)</label>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" @input="docForm.archivo = $event.target.files[0]" class="mt-1 text-sm" />
                    </div>
                    <button type="submit" :disabled="docForm.processing || !docForm.archivo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Subir</button>
                    <p v-if="docForm.errors.archivo" class="w-full text-xs text-red-600">{{ docForm.errors.archivo }}</p>
                </form>

                <!-- Lista -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Archivo</th><th class="px-3 py-2">Fecha</th><th class="px-3 py-2 text-right">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="d in empDocs.documentos" :key="d.id">
                            <td class="px-3 py-2">{{ tipoDoc(d.tipo) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ d.nombre_original }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ d.fecha }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <a :href="route('empleados.documentos.descargar', d.id)" class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-200"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>Descargar</a>
                                    <BotonAccion v-if="puedeGestionar" variante="eliminar" @click="eliminarDoc(d)" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!empDocs.documentos?.length"><td colspan="4" class="px-3 py-6 text-center text-gray-500">Aún no hay documentos archivados.</td></tr>
                    </tbody>
                </table>
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
