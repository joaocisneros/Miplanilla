<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contratistas: { type: Array, default: () => [] },
    empresas: { type: Array, default: () => [] },
    productos: { type: Array, default: () => [] },
    trabajos: { type: Array, default: () => [] },
    codigos: { type: Array, default: () => [] },
    igv: { type: Number, default: 0.18 },
});

const permisos = computed(() => usePage().props.auth?.permissions ?? []);
const puedeGestionar = computed(() => permisos.value.includes('contratistas.gestionar'));
const puedeAvance = computed(() => permisos.value.includes('contratistas.avance'));

// Vista: pestaña activa y contratista seleccionado (maestro-detalle)
const tab = ref('ots');
const selId = ref(null);
const sel = computed(() => props.contratistas.find((c) => c.id === selId.value) ?? null);

// Los inactivos se ocultan de la lista; se consultan con el enlace de abajo.
const verInactivos = ref(false);
const contratistasVisibles = computed(() => props.contratistas.filter((c) => verInactivos.value || c.activo));
const inactivosCount = computed(() => props.contratistas.filter((c) => !c.activo).length);
if (props.contratistas.length) selId.value = (props.contratistas.find((c) => c.activo) ?? props.contratistas[0]).id;

// Filtro de OTs del contratista seleccionado. Por defecto solo las que
// requieren atencion (en curso o con saldo); las cerradas se consultan filtrando.
const fEstadoOt = ref('pendientes');
const otsVisibles = computed(() => {
    const lista = sel.value?.ordenes ?? [];
    if (fEstadoOt.value === 'pendientes') return lista.filter((o) => o.estado === 'en_curso' || o.saldo_por_pagar > 0);
    if (fEstadoOt.value === 'terminadas') return lista.filter((o) => o.estado === 'terminada' && o.saldo_por_pagar <= 0);
    return lista;
});

// Buscador global de OTs (por codigo o producto, en todos los contratistas)
const busca = ref('');
const otsEncontradas = computed(() => {
    const q = busca.value.trim().toUpperCase();
    if (q.length < 2) return [];
    const res = [];
    for (const c of props.contratistas) {
        for (const ot of c.ordenes) {
            if (String(ot.codigo).toUpperCase().includes(q) || String(ot.producto ?? '').toUpperCase().includes(q)) {
                res.push({ contratistaId: c.id, contratista: c.nombre, ot });
            }
        }
    }
    return res.slice(0, 15);
});
function irAOt(r) {
    selId.value = r.contratistaId;
    busca.value = '';
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const fdmy = (s) => (s ? String(s).split('-').reverse().join('/') : '');
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';

// ---- Corte de pago: se elige MES + QUINCENA (las fechas salen solas) ----
const hoy = new Date();
const dd = (n) => String(n).padStart(2, '0');
const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const cAnio = ref(hoy.getFullYear());
const cMes = ref(hoy.getMonth() + 1);
const cQuincena = ref(hoy.getDate() <= 15 ? 1 : 2);
const cContratista = ref(''); // '' = todos

const desde = computed(() => `${cAnio.value}-${dd(cMes.value)}-${cQuincena.value === 1 ? '01' : '16'}`);
const hasta = computed(() => {
    const fin = new Date(cAnio.value, cMes.value, 0).getDate();
    return `${cAnio.value}-${dd(cMes.value)}-${cQuincena.value === 1 ? '15' : dd(fin)}`;
});

const enRango = (f) => f.fecha >= desde.value && f.fecha <= hasta.value;

const corte = computed(() => props.contratistas
    .filter((c) => !cContratista.value || c.id === cContratista.value)
    .map((c) => {
        // agrupa por OT: solo los avances del periodo AÚN NO PAGADOS suman al
        // corte; lo ya pagado se muestra aparte como referencia (nunca se repite).
        const filas = [];
        let yaPagado = 0;
        for (const ot of c.ordenes) {
            const enPeriodo = ot.avances.filter(enRango);
            if (!enPeriodo.length) continue;
            const porPagar = enPeriodo.filter((a) => !a.pagado);
            yaPagado += enPeriodo.filter((a) => a.pagado).reduce((s, a) => s + a.monto, 0);
            if (!porPagar.length) continue;
            filas.push({
                ot: ot.codigo,
                producto: ot.producto,
                descripcion: ot.descripcion,
                pct: Math.round(porPagar.reduce((s, a) => s + a.porcentaje, 0) * 100) / 100,
                monto: porPagar.reduce((s, a) => s + a.monto, 0),
                pagado: false,
            });
        }
        const total = filas.reduce((s, f) => s + f.monto, 0);
        const pendiente = filas.length > 0;
        return { id: c.id, nombre: c.nombre, cuenta: c.cuenta, filas, total, yaPagado: Math.round(yaPagado * 100) / 100, facturar: total * (1 + props.igv), pendiente };
    })
    .filter((c) => c.filas.length > 0 || c.yaPagado > 0));

const corteTotal = computed(() => corte.value.reduce((s, c) => s + c.total, 0));

// Confirmación de pago en modal (resumen completo antes de registrar)
const mostrarPago = ref(false);
const pagoSel = ref(null);
const pagoEnviando = ref(false);
function pagarContratista(c) {
    pagoSel.value = c;
    mostrarPago.value = true;
}
function confirmarPago() {
    pagoEnviando.value = true;
    router.post(route('contratistas.corte.pagar'),
        { desde: desde.value, hasta: hasta.value, contratista_id: pagoSel.value.id },
        { preserveScroll: true, onFinish: () => { pagoEnviando.value = false; mostrarPago.value = false; } });
}

// ---- Modales ----
const mostrarContratista = ref(false);
const fC = useForm({ id: null, nombre: '', ruc: '', telefono: '', cuenta: '', activo: true });
function abrirContratista(c = null) {
    fC.clearErrors();
    fC.id = c?.id ?? null;
    fC.nombre = c?.nombre ?? '';
    fC.ruc = c?.ruc ?? '';
    fC.telefono = c?.telefono ?? '';
    fC.cuenta = c?.cuenta ?? '';
    fC.activo = c?.activo ?? true;
    mostrarContratista.value = true;
}
function guardarContratista() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrarContratista.value = false; } };
    fC.id ? fC.put(route('contratistas.update', fC.id), opts) : fC.post(route('contratistas.store'), opts);
}
function eliminarContratista() {
    if (confirm(`¿Eliminar a "${fC.nombre}"?\n\nSolo se puede si no tiene órdenes de trabajo registradas.`)) {
        router.delete(route('contratistas.destroy', fC.id), { preserveScroll: true, onSuccess: () => { mostrarContratista.value = false; } });
    }
}

const mostrarOt = ref(false);
const fO = useForm({ id: null, contratista_id: '', empresa_id: '', codigo: '', producto: '', descripcion: '', precio: '', estado: 'en_curso' });

// Productos y trabajos: catálogos propios (cajas de opciones).
const productosActivos = computed(() => props.productos.filter((p) => p.activo));
const trabajosActivos = computed(() => props.trabajos.filter((t) => t.activo));

// Catalogo de codigos OT (unidades del taller): se registran una vez en la
// pestaña Catalogos y en la OT solo se seleccionan. Al elegir el codigo, el
// producto se llena solo.
const codigosActivos = computed(() => props.codigos.filter((c) => c.activo));
// Codigo y producto van amarrados: al elegir el codigo, el producto se pone
// solo desde el catalogo (crear y editar por igual).
watch(() => fO.codigo, (cod) => {
    if (!cod) return;
    const entrada = props.codigos.find((c) => c.codigo === cod);
    if (entrada?.producto) fO.producto = entrada.producto;
});

// Buscadores de los catalogos
const buscaCod = ref('');
const buscaProd = ref('');
const codigosFiltrados = computed(() => {
    const q = buscaCod.value.trim().toUpperCase();
    return props.codigos.filter((c) => !q || c.codigo.toUpperCase().includes(q) || String(c.producto ?? '').toUpperCase().includes(q));
});
const productosFiltrados = computed(() => {
    const q = buscaProd.value.trim().toUpperCase();
    return props.productos.filter((p) => !q || p.nombre.toUpperCase().includes(q));
});

// CRUD del catalogo de codigos OT (en modal)
const mostrarCod = ref(false);
const prodNuevo = ref(false); // true = escribir un producto que no esta en la lista
const fCod = useForm({ id: null, codigo: '', producto: '', activo: true });
function abrirCodigo(c = null) {
    fCod.clearErrors();
    fCod.id = c?.id ?? null;
    fCod.codigo = c?.codigo ?? '';
    fCod.producto = c?.producto ?? '';
    fCod.activo = c?.activo ?? true;
    prodNuevo.value = false;
    mostrarCod.value = true;
}
function guardarCodigo() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrarCod.value = false; fCod.reset(); } };
    fCod.id ? fCod.put(route('contratistas.codigos.update', fCod.id), opts) : fCod.post(route('contratistas.codigos.store'), opts);
}
function editarCodigo(c) {
    abrirCodigo(c);
}
function eliminarCodigo(c) {
    if (confirm(`¿Eliminar el código "${c.codigo}"?`)) {
        router.delete(route('contratistas.codigos.destroy', c.id), { preserveScroll: true });
    }
}

// CRUD del catalogo de productos
const fP = useForm({ id: null, nombre: '', activo: true });
function guardarProducto() {
    const opts = { preserveScroll: true, onSuccess: () => fP.reset() };
    fP.id ? fP.put(route('contratistas.productos.update', fP.id), opts) : fP.post(route('contratistas.productos.store'), opts);
}
function editarProducto(p) {
    fP.id = p.id;
    fP.nombre = p.nombre;
    fP.activo = p.activo;
}
function eliminarProducto(p) {
    if (confirm(`¿Eliminar el producto "${p.nombre}"?`)) {
        router.delete(route('contratistas.productos.destroy', p.id), { preserveScroll: true });
    }
}

// CRUD del catalogo de trabajos/descripciones
const buscaTrab = ref('');
const trabajosFiltrados = computed(() => {
    const q = buscaTrab.value.trim().toUpperCase();
    return props.trabajos.filter((t) => !q || t.nombre.toUpperCase().includes(q));
});
const fT = useForm({ id: null, nombre: '', activo: true });
function guardarTrabajo() {
    const opts = { preserveScroll: true, onSuccess: () => fT.reset() };
    fT.id ? fT.put(route('contratistas.trabajos.update', fT.id), opts) : fT.post(route('contratistas.trabajos.store'), opts);
}
function editarTrabajo(t) {
    fT.id = t.id;
    fT.nombre = t.nombre;
    fT.activo = t.activo;
}
function eliminarTrabajo(t) {
    if (confirm(`¿Eliminar el trabajo "${t.nombre}"?`)) {
        router.delete(route('contratistas.trabajos.destroy', t.id), { preserveScroll: true });
    }
}

// Sugiere el siguiente correlativo "NNN/AA" mirando todos los codigos existentes
function siguienteCodigo() {
    // Serie POR AÑO: el correlativo cuenta solo los códigos del año actual
    // (formato NNN/AA). Al cambiar el año, arranca de nuevo en 001/AA.
    const anio = String(new Date().getFullYear()).slice(-2);
    let max = 0;
    const codigosTodos = [
        ...props.codigos.map((c) => c.codigo),
        ...props.contratistas.flatMap((c) => c.ordenes.map((o) => o.codigo)),
    ];
    for (const cod of codigosTodos) {
        const m = String(cod).match(new RegExp(`^0*(\\d+)\\/${anio}$`));
        if (m) max = Math.max(max, parseInt(m[1], 10));
    }
    return `${String(max + 1).padStart(3, '0')}/${anio}`;
}

function abrirOt(contratistaId, ot = null) {
    fO.clearErrors();
    fO.id = ot?.id ?? null;
    fO.contratista_id = contratistaId;
    fO.empresa_id = ot?.empresa_id ?? '';
    fO.codigo = ot?.codigo ?? '';
    fO.producto = ot?.producto ?? '';
    fO.descripcion = ot?.descripcion ?? '';
    fO.precio = ot?.precio ?? '';
    fO.estado = ot?.estado ?? 'en_curso';
    mostrarOt.value = true;
}
function guardarOt() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrarOt.value = false; } };
    fO.id ? fO.put(route('contratistas.ots.update', fO.id), opts) : fO.post(route('contratistas.ots.store'), opts);
}
function eliminarOt() {
    if (confirm(`¿Eliminar la OT "${fO.codigo}" y sus avances?\n\nSolo se puede si no tiene avances pagados.`)) {
        router.delete(route('contratistas.ots.destroy', fO.id), { preserveScroll: true, onSuccess: () => { mostrarOt.value = false; } });
    }
}

const mostrarAvance = ref(false);
const otSel = ref(null);
const fA = useForm({ orden_trabajo_id: null, fecha: new Date().toISOString().slice(0, 10), porcentaje: '', nota: '' });
function abrirAvance(ot) {
    fA.clearErrors();
    otSel.value = ot;
    fA.orden_trabajo_id = ot.id;
    fA.porcentaje = '';
    fA.nota = '';
    mostrarAvance.value = true;
}
function guardarAvance() {
    fA.post(route('contratistas.avances.store'), { preserveScroll: true, onSuccess: () => { mostrarAvance.value = false; } });
}
function eliminarAvance(a) {
    if (confirm(`¿Eliminar el avance de ${a.porcentaje}% del ${a.fecha}?`)) {
        router.delete(route('contratistas.avances.destroy', a.id), { preserveScroll: true });
    }
}

const mostrarDetalle = ref(false);
function verAvances(ot) {
    otSel.value = ot;
    mostrarDetalle.value = true;
}

const estadoBadge = (e) => ({
    en_curso: 'bg-blue-100 text-blue-800',
    terminada: 'bg-green-100 text-green-800',
    anulada: 'bg-gray-200 text-gray-600',
}[e] ?? 'bg-gray-100 text-gray-600');

// Etiqueta que combina trabajo + pago (lo que uno necesita saber de un vistazo)
function estadoOt(ot) {
    if (ot.estado === 'anulada') return { texto: 'anulada', clase: 'bg-gray-200 text-gray-600' };
    if (ot.estado === 'terminada' && ot.saldo_por_pagar > 0) return { texto: '💰 falta pagar', clase: 'bg-amber-100 text-amber-800' };
    if (ot.estado === 'terminada') return { texto: '✔ hecha y pagada', clase: 'bg-green-100 text-green-800' };
    if (ot.saldo_por_pagar > 0) return { texto: 'en curso · falta pagar', clase: 'bg-blue-100 text-blue-800' };
    return { texto: 'en curso', clase: 'bg-blue-100 text-blue-800' };
}

// ---- Vista POR UNIDAD: una misma unidad (código) puede tener OTs de varios
// contratistas; aquí se ve quiénes le están trabajando y a quién falta pagar.
const buscaUnidad = ref('');
const unidadSel = ref('');
const unidades = computed(() => {
    const mapa = {};
    for (const c of props.contratistas) {
        for (const ot of c.ordenes) {
            if (!mapa[ot.codigo]) mapa[ot.codigo] = { codigo: ot.codigo, producto: ot.producto, n: 0, saldo: 0 };
            mapa[ot.codigo].n++;
            mapa[ot.codigo].saldo += ot.saldo_por_pagar;
            if (!mapa[ot.codigo].producto && ot.producto) mapa[ot.codigo].producto = ot.producto;
        }
    }
    const q = buscaUnidad.value.trim().toUpperCase();
    return Object.values(mapa)
        .filter((u) => !q || u.codigo.toUpperCase().includes(q) || String(u.producto ?? '').toUpperCase().includes(q))
        .sort((a, b) => a.codigo.localeCompare(b.codigo, undefined, { numeric: true }));
});
const filasUnidad = computed(() => {
    if (!unidadSel.value) return [];
    const filas = [];
    for (const c of props.contratistas) {
        for (const ot of c.ordenes) {
            if (ot.codigo === unidadSel.value) filas.push({ contratista: c.nombre, ot });
        }
    }
    return filas.sort((a, b) => a.contratista.localeCompare(b.contratista));
});
const totalUnidad = computed(() => filasUnidad.value.reduce((s, f) => ({
    precio: s.precio + f.ot.precio,
    avanzado: s.avanzado + f.ot.monto_avanzado,
    pagado: s.pagado + f.ot.monto_pagado,
    saldo: s.saldo + f.ot.saldo_por_pagar,
}), { precio: 0, avanzado: 0, pagado: 0, saldo: 0 }));
</script>

<template>
    <Head title="Contratistas" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Contratistas — pago por avance de obra</h2>
                <div class="flex items-center gap-2">
                    <a :href="route('contratistas.export.general')" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" title="Todas las OTs con su historial de avances, pagos y saldos">📥 Exportar detallado</a>
                    <button v-if="puedeGestionar" @click="abrirContratista()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Contratista</button>
                </div>
            </div>
        </template>
        <div class="p-6">
            <div class="space-y-4">
                <!-- Pestañas -->
                <div class="flex gap-2">
                    <button @click="tab = 'ots'" :class="tab === 'ots' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="rounded-md px-4 py-2 text-sm font-semibold shadow-sm">📋 Órdenes de trabajo</button>
                    <button @click="tab = 'unidad'" :class="tab === 'unidad' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="rounded-md px-4 py-2 text-sm font-semibold shadow-sm">🚚 Por unidad</button>
                    <button @click="tab = 'corte'" :class="tab === 'corte' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="rounded-md px-4 py-2 text-sm font-semibold shadow-sm">💵 Corte de pago</button>
                    <button v-if="puedeGestionar" @click="tab = 'productos'" :class="tab === 'productos' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="rounded-md px-4 py-2 text-sm font-semibold shadow-sm">🏷️ Catálogos</button>
                </div>

                <!-- ===== Pestaña: POR UNIDAD (quiénes trabajan cada unidad y pagos) ===== -->
                <div v-if="tab === 'unidad'" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(15rem,20rem)_1fr]">
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b border-gray-100 p-3">
                            <input v-model="buscaUnidad" type="text" placeholder="🔍 Buscar unidad o producto..." class="w-full rounded-md border-gray-300 py-1.5 text-sm" />
                        </div>
                        <div class="max-h-[34rem] overflow-y-auto">
                            <button v-for="u in unidades" :key="u.codigo" @click="unidadSel = u.codigo"
                                class="block w-full border-b border-gray-50 px-4 py-2.5 text-left hover:bg-indigo-50/50"
                                :class="unidadSel === u.codigo ? 'bg-indigo-50' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-gray-800">{{ u.codigo }}</span>
                                    <span v-if="u.saldo > 0" class="text-xs font-semibold text-red-600">{{ money(u.saldo) }}</span>
                                    <span v-else class="text-xs text-green-600">✔ al día</span>
                                </div>
                                <div class="truncate text-xs text-gray-500">{{ u.producto }} · {{ u.n }} OT(s)</div>
                            </button>
                            <div v-if="unidades.length === 0" class="p-6 text-center text-sm text-gray-400">Sin resultados.</div>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div v-if="!unidadSel" class="p-10 text-center text-gray-400">Elige una unidad de la izquierda para ver quiénes le están trabajando.</div>
                        <template v-else>
                            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
                                <div>
                                    <div class="text-lg font-bold text-gray-900">{{ unidadSel }}</div>
                                    <div class="text-xs text-gray-500">{{ filasUnidad[0]?.ot.producto }}</div>
                                </div>
                                <div class="ml-auto flex items-center gap-5 text-right">
                                    <div><div class="text-[10px] uppercase text-gray-500">Pactado</div><div class="font-bold tabular-nums">{{ money(totalUnidad.precio) }}</div></div>
                                    <div><div class="text-[10px] uppercase text-gray-500">Avanzado</div><div class="font-bold tabular-nums">{{ money(totalUnidad.avanzado) }}</div></div>
                                    <div><div class="text-[10px] uppercase text-gray-500">Pagado</div><div class="font-bold tabular-nums text-green-700">{{ money(totalUnidad.pagado) }}</div></div>
                                    <div><div class="text-[10px] uppercase text-gray-500">Falta pagar</div><div class="font-bold tabular-nums" :class="totalUnidad.saldo > 0 ? 'text-red-600' : 'text-gray-400'">{{ money(totalUnidad.saldo) }}</div></div>
                                </div>
                            </div>
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr><th class="px-4 py-2">Contratista</th><th class="px-4 py-2">Trabajo que realiza</th><th class="px-4 py-2 text-right">Precio</th><th class="px-4 py-2">Avance</th><th class="px-4 py-2 text-right">Pagado</th><th class="px-4 py-2 text-right">Falta pagar</th><th class="px-4 py-2 text-center">Estado</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 [&_td]:tabular-nums">
                                    <tr v-for="f in filasUnidad" :key="f.ot.id" class="hover:bg-indigo-50/30">
                                        <td class="whitespace-nowrap px-4 py-2.5 font-semibold text-gray-800">{{ f.contratista }}</td>
                                        <td class="px-4 py-2.5 text-xs text-gray-600">{{ f.ot.descripcion }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right">{{ money(f.ot.precio) }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200"><div class="h-full rounded-full bg-blue-500" :style="{ width: Math.min(f.ot.avance_total, 100) + '%' }"></div></div>
                                                <span class="text-xs font-semibold">{{ f.ot.avance_total }}%</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right text-green-700">{{ money(f.ot.monto_pagado) }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold" :class="f.ot.saldo_por_pagar > 0 ? 'text-red-600' : 'text-gray-400'">{{ money(f.ot.saldo_por_pagar) }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-center"><span :class="estadoOt(f.ot).clase" class="rounded-full px-2 py-0.5 text-[10px]">{{ estadoOt(f.ot).texto }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </template>
                    </div>
                </div>

                <!-- ===== Pestaña: OTs por contratista (maestro-detalle) ===== -->
                <div v-if="tab === 'ots'" class="space-y-3">
                <!-- Buscador global de OT -->
                <div class="relative max-w-md">
                    <input v-model="busca" type="text" placeholder="🔍 Buscar OT por código o producto (ej. 047/26)" class="w-full rounded-md border-gray-300 py-2 text-sm shadow-sm" />
                    <div v-if="otsEncontradas.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg">
                        <button v-for="(r, i) in otsEncontradas" :key="i" @click="irAOt(r)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-indigo-50">
                            <span><b>{{ r.ot.codigo }}</b> <span class="text-gray-500">{{ r.ot.producto }}</span></span>
                            <span class="text-xs text-gray-400">{{ r.contratista }}</span>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                    <!-- Lista de contratistas -->
                    <div class="space-y-2 lg:col-span-1">
                        <button v-for="c in contratistasVisibles" :key="c.id" @click="selId = c.id"
                            class="w-full rounded-lg border p-3 text-left shadow-sm transition"
                            :class="[selId === c.id ? 'border-indigo-400 bg-indigo-50' : 'border-transparent bg-white hover:bg-gray-50', !c.activo ? 'opacity-60' : '']">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-800">{{ c.nombre }}</span>
                                <span v-if="!c.activo" class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] text-gray-600">inactivo</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs">
                                <span class="text-gray-500">{{ c.ordenes.length }} OT(s)</span>
                                <span :class="c.saldo_por_pagar > 0 ? 'font-bold text-red-600' : 'text-green-700'">{{ money(c.saldo_por_pagar) }}</span>
                            </div>
                        </button>
                        <button v-if="inactivosCount" @click="verInactivos = !verInactivos" class="w-full rounded-md py-1.5 text-center text-xs text-gray-500 hover:text-indigo-600 hover:underline">
                            {{ verInactivos ? 'Ocultar inactivos' : `Ver ${inactivosCount} inactivo(s)` }}
                        </button>
                        <div v-if="contratistasVisibles.length === 0" class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                            Sin contratistas activos. Crea uno con "+ Contratista".
                        </div>
                    </div>

                    <!-- Detalle del contratista seleccionado -->
                    <div v-if="sel" class="lg:col-span-3">
                        <div class="rounded-lg bg-white shadow-sm">
                            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
                                <div>
                                    <div class="text-lg font-bold text-gray-800">{{ sel.nombre }}</div>
                                    <div class="text-xs text-gray-500">
                                        <span v-if="sel.ruc">RUC {{ sel.ruc }} · </span>
                                        <span v-if="sel.telefono">📞 {{ sel.telefono }} · </span>
                                        <span v-if="sel.cuenta">Cta. {{ sel.cuenta }}</span>
                                    </div>
                                </div>
                                <div class="ml-auto flex items-center gap-3">
                                    <select v-model="fEstadoOt" class="rounded-md border-gray-300 py-1.5 text-xs">
                                        <option value="pendientes">Pendientes (en curso o con saldo)</option>
                                        <option value="terminadas">Cerradas (hechas y pagadas)</option>
                                        <option value="todas">Todas</option>
                                    </select>
                                    <div class="text-right">
                                        <div class="text-[10px] uppercase text-gray-500">Saldo por pagar</div>
                                        <div class="font-bold" :class="sel.saldo_por_pagar > 0 ? 'text-red-600' : 'text-green-700'">{{ money(sel.saldo_por_pagar) }}</div>
                                    </div>
                                    <button v-if="puedeGestionar" @click="abrirOt(sel.id)" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">+ Nueva OT</button>
                                    <button v-if="puedeGestionar" @click="abrirContratista(sel)" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">✏️ Editar</button>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                        <tr>
                                            <th class="px-4 py-2">OT</th><th class="px-4 py-2">Trabajo</th>
                                            <th class="px-4 py-2 text-right">Precio</th><th class="w-40 px-4 py-2">Avance</th>
                                            <th class="px-4 py-2 text-right">Saldo</th><th class="px-4 py-2 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 [&_td]:tabular-nums">
                                        <tr v-for="ot in otsVisibles" :key="ot.id" class="hover:bg-gray-50" :class="ot.estado === 'anulada' ? 'opacity-50' : ''">
                                            <td class="px-4 py-2">
                                                <div class="font-semibold text-gray-800">{{ ot.codigo }}</div>
                                                <span :class="estadoOt(ot).clase" class="whitespace-nowrap rounded-full px-2 py-0.5 text-[10px]">{{ estadoOt(ot).texto }}</span>
                                            </td>
                                            <td class="max-w-[260px] px-4 py-2">
                                                <div class="truncate text-gray-700" :title="ot.producto">{{ ot.producto }}</div>
                                                <div class="truncate text-xs text-gray-400" :title="ot.descripcion">{{ ot.descripcion }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right">{{ money(ot.precio) }}</td>
                                            <td class="px-4 py-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 w-24 overflow-hidden rounded bg-gray-200">
                                                        <div class="h-2 rounded" :class="ot.avance_total >= 100 ? 'bg-green-500' : 'bg-blue-500'" :style="{ width: Math.min(ot.avance_total, 100) + '%' }"></div>
                                                    </div>
                                                    <span class="text-xs font-semibold text-gray-600">{{ ot.avance_total }}%</span>
                                                </div>
                                                <div class="mt-0.5 text-[11px] text-gray-400">avanzado {{ money(ot.monto_avanzado) }} · <span :class="ot.monto_pagado > 0 ? 'font-semibold text-green-600' : ''">pagado {{ money(ot.monto_pagado) }}</span></div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right font-semibold" :class="ot.saldo_por_pagar > 0 ? 'text-red-600' : 'text-gray-400'">{{ money(ot.saldo_por_pagar) }}</td>
                                            <td class="px-4 py-2">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button v-if="puedeAvance && ot.estado !== 'anulada' && ot.avance_total < 100" @click="abrirAvance(ot)" class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100">+ Avance</button>
                                                    <button @click="verAvances(ot)" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200">👁</button>
                                                    <button v-if="puedeGestionar" @click="abrirOt(sel.id, ot)" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 hover:bg-gray-200">✏️</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="otsVisibles.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ sel.ordenes.length ? 'Nada pendiente aquí — usa el filtro para ver las cerradas.' : 'Sin órdenes de trabajo. Crea una con "+ Nueva OT".' }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <!-- ===== Pestaña: Corte de pago ===== -->
                <div v-if="tab === 'corte'" class="space-y-4">
                    <!-- Selector simple: mes + quincena + contratista -->
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-xs uppercase text-gray-500">Año</label>
                                <input v-model.number="cAnio" type="number" min="2020" max="2100" class="w-24 rounded-md border-gray-300 py-1.5 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs uppercase text-gray-500">Mes</label>
                                <select v-model.number="cMes" class="rounded-md border-gray-300 py-1.5 text-sm">
                                    <option v-for="(m, i) in meses" :key="i" :value="i + 1">{{ m }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase text-gray-500">Quincena</label>
                                <select v-model.number="cQuincena" class="rounded-md border-gray-300 py-1.5 text-sm">
                                    <option :value="1">1ra (del 1 al 15)</option>
                                    <option :value="2">2da (del 16 a fin de mes)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase text-gray-500">Contratista</label>
                                <select v-model="cContratista" class="rounded-md border-gray-300 py-1.5 text-sm">
                                    <option value="">Todos</option>
                                    <option v-for="c in contratistas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                            </div>
                            <a v-if="corte.length" :href="route('contratistas.corte.excel', { desde, hasta, contratista_id: cContratista || undefined })" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">📥 Exportar</a>
                            <div class="ml-auto text-right">
                                <div class="text-xs uppercase text-gray-500">Total del periodo</div>
                                <div class="text-xl font-bold text-gray-800">{{ money(corteTotal) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Una tarjeta por contratista: qué hizo y cuánto se le paga -->
                    <div v-for="c in corte" :key="c.id" class="rounded-lg bg-white shadow-sm">
                        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
                            <div>
                                <div class="font-bold text-gray-800">{{ c.nombre }}</div>
                                <div v-if="c.cuenta" class="text-xs text-gray-500">Cta. {{ c.cuenta }}</div>
                            </div>
                            <div class="ml-auto flex items-center gap-4">
                                <div v-if="c.yaPagado > 0" class="text-right">
                                    <div class="text-[10px] uppercase text-gray-500">Ya pagado (periodo)</div>
                                    <div class="text-lg font-semibold text-green-700">{{ money(c.yaPagado) }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] uppercase text-gray-500">Se le paga</div>
                                    <div class="text-lg font-bold text-gray-900">{{ money(c.total) }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] uppercase text-gray-500">Su factura (con IGV)</div>
                                    <div class="text-lg font-semibold text-gray-500">{{ money(c.facturar) }}</div>
                                </div>
                                <span v-if="!c.pendiente" class="rounded-full bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-800">✔ Todo pagado</span>
                                <button v-else-if="puedeGestionar" @click="pagarContratista(c)" class="rounded-md bg-green-700 px-4 py-2 text-xs font-semibold text-white hover:bg-green-800">💵 Registrar pago</button>
                            </div>
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th class="px-4 py-2">OT</th><th class="px-4 py-2">Trabajo realizado</th><th class="px-4 py-2 text-right">Avanzó</th><th class="px-4 py-2 text-right">Le corresponde</th><th class="px-4 py-2 text-center">Estado</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 [&_td]:tabular-nums">
                                <tr v-for="(f, i) in c.filas" :key="i">
                                    <td class="whitespace-nowrap px-4 py-2 font-semibold text-gray-800">{{ f.ot }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ f.producto }} <span class="text-xs text-gray-400">— {{ f.descripcion }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">{{ f.pct }}%</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-semibold">{{ money(f.monto) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-center">
                                        <span v-if="f.pagado" class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">pagado</span>
                                        <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">pendiente</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="corte.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                        Ningún contratista tiene avances registrados en {{ cQuincena === 1 ? '1ra' : '2da' }} quincena de {{ meses[cMes - 1] }} {{ cAnio }}.
                    </div>
                </div>

                <!-- ===== Pestaña: Catálogos (unidades a la izquierda, trabajos a la derecha) ===== -->
                <div v-if="tab === 'productos'" class="mx-auto grid max-w-7xl grid-cols-1 items-start gap-6 xl:grid-cols-2">
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                            <h3 class="text-sm font-bold text-gray-800">📇 Unidades (código + producto) <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ codigos.length }}</span></h3>
                            <div class="flex items-center gap-2">
                                <input v-model="buscaCod" type="text" placeholder="🔍 Buscar código o producto..." class="w-56 rounded-md border-gray-300 py-1.5 text-xs" />
                                <button @click="abrirCodigo()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">+ Agregar</button>
                            </div>
                        </div>
                        <div class="max-h-[32rem] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr><th class="px-4 py-2">Código</th><th class="px-4 py-2">Producto</th><th class="px-4 py-2 text-right">Acciones</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="c in codigosFiltrados" :key="c.id" class="hover:bg-indigo-50/40" :class="!c.activo ? 'opacity-45' : ''">
                                        <td class="whitespace-nowrap px-4 py-1.5 font-semibold text-gray-800">{{ c.codigo }} <span v-if="!c.activo" class="ml-1 text-[10px] font-normal text-gray-500">(inactivo)</span></td>
                                        <td class="px-4 py-1.5 text-gray-600">{{ c.producto }}</td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right">
                                            <button @click="editarCodigo(c)" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-100" title="Editar">✏️</button>
                                            <button @click="eliminarCodigo(c)" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-red-50" title="Eliminar">🗑</button>
                                        </td>
                                    </tr>
                                    <tr v-if="codigosFiltrados.length === 0"><td colspan="3" class="px-4 py-6 text-center text-gray-400">{{ buscaCod ? 'Sin resultados para la búsqueda.' : 'Sin códigos registrados.' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Catálogo de trabajos / descripciones -->
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                            <h3 class="text-sm font-bold text-gray-800">🛠 Trabajos / descripciones <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ trabajos.length }}</span></h3>
                            <input v-model="buscaTrab" type="text" placeholder="🔍 Buscar trabajo..." class="w-56 rounded-md border-gray-300 py-1.5 text-xs" />
                        </div>
                        <form @submit.prevent="guardarTrabajo" class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/60 px-4 py-2.5">
                            <input v-model="fT.nombre" type="text" :placeholder="fT.id ? 'Editando trabajo...' : 'Nuevo trabajo (ej. ARMADO DE CHASIS)'" class="w-full rounded-md border-gray-300 py-1.5 text-xs" />
                            <label v-if="fT.id" class="flex items-center gap-1 whitespace-nowrap text-xs text-gray-600"><input v-model="fT.activo" type="checkbox" class="rounded border-gray-300" /> Activo</label>
                            <button type="submit" :disabled="fT.processing || !fT.nombre.trim()" class="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ fT.id ? 'Guardar' : '+ Agregar' }}</button>
                            <button v-if="fT.id" type="button" @click="fT.reset()" class="whitespace-nowrap rounded-md bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700">Cancelar</button>
                        </form>
                        <p v-if="fT.errors.nombre" class="px-4 pt-1 text-xs text-red-600">{{ fT.errors.nombre }}</p>
                        <div class="max-h-[24rem] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr><th class="px-4 py-2">Trabajo</th><th class="px-4 py-2 text-right">Acciones</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="t in trabajosFiltrados" :key="t.id" class="hover:bg-indigo-50/40" :class="!t.activo ? 'opacity-45' : ''">
                                        <td class="px-4 py-1.5 text-gray-800">{{ t.nombre }} <span v-if="!t.activo" class="ml-1 text-[10px] text-gray-500">(inactivo)</span></td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right">
                                            <button @click="editarTrabajo(t)" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-100" title="Editar">✏️</button>
                                            <button @click="eliminarTrabajo(t)" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-red-50" title="Eliminar">🗑</button>
                                        </td>
                                    </tr>
                                    <tr v-if="trabajosFiltrados.length === 0"><td colspan="2" class="px-4 py-6 text-center text-gray-400">{{ buscaTrab ? 'Sin resultados para la búsqueda.' : 'Sin trabajos registrados.' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal contratista -->
        <CrudModal :show="mostrarContratista" :titulo="fC.id ? 'Editar contratista' : 'Nuevo contratista'" @close="mostrarContratista = false">
            <form @submit.prevent="guardarContratista" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-700">Nombre *</label>
                    <input v-model="fC.nombre" type="text" :class="inp" />
                    <p v-if="fC.errors.nombre" class="text-xs text-red-600">{{ fC.errors.nombre }}</p>
                </div>
                <div><label class="text-sm text-gray-700">RUC</label><input v-model="fC.ruc" type="text" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Teléfono</label><input v-model="fC.telefono" type="text" :class="inp" /></div>
                <div class="md:col-span-2"><label class="text-sm text-gray-700">Cuenta bancaria</label><input v-model="fC.cuenta" type="text" :class="inp" /></div>
                <label v-if="fC.id" class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2"><input v-model="fC.activo" type="checkbox" class="rounded border-gray-300" /> Activo</label>
                <div class="flex items-center justify-between gap-3 md:col-span-2">
                    <button v-if="fC.id" type="button" @click="eliminarContratista" class="rounded-md bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">🗑 Eliminar</button>
                    <div class="ml-auto flex gap-3">
                        <button type="button" @click="mostrarContratista = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                        <button type="submit" :disabled="fC.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar</button>
                    </div>
                </div>
            </form>
        </CrudModal>

        <!-- Modal OT -->
        <CrudModal :show="mostrarOt" :titulo="fO.id ? 'Editar orden de trabajo' : 'Nueva orden de trabajo'" @close="mostrarOt = false">
            <form @submit.prevent="guardarOt" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-700">1. Unidad (código — producto) *</label>
                    <select v-model="fO.codigo" :class="inp">
                        <option value="">— Selecciona la unidad del taller —</option>
                        <option v-if="fO.codigo && !codigosActivos.some((c) => c.codigo === fO.codigo)" :value="fO.codigo">{{ fO.codigo }}{{ fO.producto ? ' — ' + fO.producto : '' }}</option>
                        <option v-for="c in codigosActivos" :key="c.id" :value="c.codigo">{{ c.codigo }}{{ c.producto ? ' — ' + c.producto : '' }}</option>
                    </select>
                    <p class="mt-0.5 text-[11px] text-gray-400">¿Falta la unidad? Regístrala en la pestaña 🏷️ Catálogos.</p>
                    <p v-if="fO.errors.codigo" class="text-xs text-red-600">{{ fO.errors.codigo }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-700">2. Trabajo a realizar</label>
                    <select v-model="fO.descripcion" :class="inp">
                        <option value="">—</option>
                        <option v-if="fO.descripcion && !trabajosActivos.some((t) => t.nombre === fO.descripcion)" :value="fO.descripcion">{{ fO.descripcion }}</option>
                        <option v-for="t in trabajosActivos" :key="t.id" :value="t.nombre">{{ t.nombre }}</option>
                    </select>
                    <p class="mt-0.5 text-[11px] text-gray-400">¿Falta uno? Regístralo en la pestaña 🏷️ Catálogos.</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">3. Precio pactado (S/) *</label>
                    <input v-model="fO.precio" type="number" step="0.01" min="0" :class="inp" />
                    <p v-if="fO.errors.precio" class="text-xs text-red-600">{{ fO.errors.precio }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">4. Empresa (a quién factura)</label>
                    <select v-model="fO.empresa_id" :class="inp">
                        <option value="">—</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                    </select>
                </div>
                <div v-if="fO.id">
                    <label class="text-sm text-gray-700">Estado</label>
                    <select v-model="fO.estado" :class="inp">
                        <option value="en_curso">En curso</option>
                        <option value="terminada">Terminada</option>
                        <option value="anulada">Anulada</option>
                    </select>
                </div>
                <div class="flex items-end justify-between gap-3" :class="fO.id ? '' : 'md:col-span-2'">
                    <button v-if="fO.id" type="button" @click="eliminarOt" class="rounded-md bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">🗑 Eliminar</button>
                    <div class="ml-auto flex gap-3">
                        <button type="button" @click="mostrarOt = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                        <button type="submit" :disabled="fO.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar</button>
                    </div>
                </div>
            </form>
        </CrudModal>

        <!-- Modal código OT (catálogo) -->
        <CrudModal :show="mostrarCod" max-width="md" :titulo="fCod.id ? 'Editar código OT' : 'Registrar código OT'" @close="mostrarCod = false">
            <form @submit.prevent="guardarCodigo" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm text-gray-700">Código *</label>
                        <input v-model="fCod.codigo" type="text" placeholder="Escribe el código..." :class="inp" />
                        <button v-if="!fCod.id && !fCod.codigo" type="button" @click="fCod.codigo = siguienteCodigo()" class="mt-0.5 text-[11px] text-indigo-600 hover:underline">Usar siguiente: {{ siguienteCodigo() }}</button>
                        <p v-if="fCod.errors.codigo" class="text-xs text-red-600">{{ fCod.errors.codigo }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-700">Producto</label>
                        <select v-if="!prodNuevo" v-model="fCod.producto" :class="inp">
                            <option value="">— Selecciona —</option>
                            <option v-if="fCod.producto && !productosActivos.some((p) => p.nombre === fCod.producto)" :value="fCod.producto">{{ fCod.producto }}</option>
                            <option v-for="p in productosActivos" :key="p.id" :value="p.nombre">{{ p.nombre }}</option>
                        </select>
                        <input v-else v-model="fCod.producto" type="text" placeholder="Nombre del producto nuevo..." :class="inp" />
                        <button type="button" @click="prodNuevo = !prodNuevo; fCod.producto = ''" class="mt-0.5 text-[11px] text-indigo-600 hover:underline">
                            {{ prodNuevo ? '← Elegir de la lista' : '+ Producto nuevo (escribir)' }}
                        </button>
                    </div>
                </div>
                <label v-if="fCod.id" class="flex items-center gap-2 text-sm text-gray-700"><input v-model="fCod.activo" type="checkbox" class="rounded border-gray-300" /> Activo</label>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="mostrarCod = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="fCod.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar</button>
                </div>
            </form>
        </CrudModal>

        <!-- Modal avance -->
        <CrudModal :show="mostrarAvance" :titulo="'Registrar avance — OT ' + (otSel?.codigo ?? '')" @close="mostrarAvance = false">
            <form @submit.prevent="guardarAvance" class="space-y-4">
                <div class="rounded-md bg-blue-50 p-3 text-xs text-blue-800">
                    {{ otSel?.producto }} — {{ otSel?.descripcion }}<br>
                    Precio: <b>{{ money(otSel?.precio) }}</b> · Avance actual: <b>{{ otSel?.avance_total }}%</b> · Falta: <b>{{ (100 - (otSel?.avance_total ?? 0)).toFixed(2) }}%</b>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-700">Fecha *</label>
                        <input v-model="fA.fecha" type="date" :class="inp" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-700">% avanzado *</label>
                        <div class="flex items-center gap-2">
                            <input v-model="fA.porcentaje" type="number" step="0.01" min="0.01" :max="100 - (otSel?.avance_total ?? 0)" :class="inp" />
                            <button v-if="(otSel?.avance_total ?? 0) < 100" type="button" @click="fA.porcentaje = Math.round((100 - (otSel?.avance_total ?? 0)) * 100) / 100" class="whitespace-nowrap rounded-md bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100" :title="'Completa el ' + (100 - (otSel?.avance_total ?? 0)) + '% restante para terminar la OT'">✔ Completar ({{ Math.round((100 - (otSel?.avance_total ?? 0)) * 100) / 100 }}%)</button>
                        </div>
                        <p v-if="fA.errors.porcentaje" class="text-xs text-red-600">{{ fA.errors.porcentaje }}</p>
                    </div>
                </div>
                <div v-if="fA.porcentaje > 0" class="text-sm text-gray-600">Este avance equivale a <b>{{ money((otSel?.precio ?? 0) * fA.porcentaje / 100) }}</b></div>
                <div><label class="text-sm text-gray-700">Nota</label><input v-model="fA.nota" type="text" :class="inp" /></div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="mostrarAvance = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="fA.processing" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Registrar</button>
                </div>
            </form>
        </CrudModal>

        <!-- Modal detalle de avances -->
        <CrudModal :show="mostrarDetalle" :titulo="'Avances — OT ' + (otSel?.codigo ?? '')" @close="mostrarDetalle = false">
            <div class="space-y-3 text-sm">
                <table class="w-full">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr><th class="py-1">Fecha</th><th class="py-1 text-right">%</th><th class="py-1 text-right">Monto</th><th class="py-1 text-center">Pagado</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="a in otSel?.avances ?? []" :key="a.id">
                            <td class="py-1.5">{{ fdmy(a.fecha) }} <span v-if="a.nota" class="text-xs text-gray-400">({{ a.nota }})</span></td>
                            <td class="py-1.5 text-right tabular-nums">{{ a.porcentaje }}%</td>
                            <td class="py-1.5 text-right tabular-nums">{{ money(a.monto) }}</td>
                            <td class="py-1.5 text-center">
                                <span v-if="a.pagado" class="whitespace-nowrap rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">✔ pagado el {{ fdmy(a.fecha_pago) }}</span>
                                <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">⏳ pendiente</span>
                            </td>
                            <td class="py-1.5 text-right">
                                <button v-if="puedeGestionar && !a.pagado" @click="eliminarAvance(a)" class="text-xs text-red-600 hover:underline">eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="!otSel?.avances?.length"><td colspan="5" class="py-4 text-center text-gray-400">Sin avances registrados.</td></tr>
                    </tbody>
                </table>
                <div class="flex justify-end border-t pt-3">
                    <button @click="mostrarDetalle = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cerrar</button>
                </div>
            </div>
        </CrudModal>

        <!-- Modal de confirmación de pago (resumen profesional del corte) -->
        <CrudModal :show="mostrarPago" titulo="💵 Confirmar pago al contratista" @close="mostrarPago = false">
            <div v-if="pagoSel" class="space-y-4 text-sm">
                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="text-base font-bold text-gray-900">{{ pagoSel.nombre }}</div>
                    <div class="mt-1 grid grid-cols-1 gap-1 text-xs text-gray-600 sm:grid-cols-2">
                        <div>📅 Periodo: <span class="font-semibold text-gray-800">{{ desde }} al {{ hasta }}</span></div>
                        <div v-if="pagoSel.cuenta">🏦 Cuenta: <span class="font-semibold text-gray-800">{{ pagoSel.cuenta }}</span></div>
                    </div>
                </div>
                <table class="w-full">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr><th class="py-1">OT</th><th class="py-1">Trabajo</th><th class="py-1 text-right">Avanzó</th><th class="py-1 text-right">Monto</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="f in pagoSel.filas" :key="f.ot">
                            <td class="py-1.5 font-semibold text-gray-800">{{ f.ot }}</td>
                            <td class="py-1.5 text-xs text-gray-600">{{ f.producto }}<span v-if="f.descripcion" class="text-gray-400"> — {{ f.descripcion }}</span></td>
                            <td class="py-1.5 text-right tabular-nums">{{ f.pct }}%</td>
                            <td class="py-1.5 text-right tabular-nums">{{ money(f.monto) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 text-sm">
                        <tr><td colspan="3" class="py-1.5 text-right font-semibold text-gray-700">Se le paga</td><td class="py-1.5 text-right font-bold tabular-nums">{{ money(pagoSel.total) }}</td></tr>
                        <tr class="text-xs text-gray-500"><td colspan="3" class="py-1 text-right">IGV (18%)</td><td class="py-1 text-right tabular-nums">{{ money(pagoSel.facturar - pagoSel.total) }}</td></tr>
                        <tr><td colspan="3" class="py-1.5 text-right font-semibold text-emerald-700">Su factura debe ser (con IGV)</td><td class="py-1.5 text-right font-bold tabular-nums text-emerald-700">{{ money(pagoSel.facturar) }}</td></tr>
                    </tfoot>
                </table>
                <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">Al confirmar, estos avances quedan marcados como pagados y ya no aparecerán en cortes futuros.</p>
                <div class="flex justify-end gap-3 border-t pt-3">
                    <button @click="mostrarPago = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button @click="confirmarPago" :disabled="pagoEnviando" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">✔ Confirmar pago</button>
                </div>
            </div>
        </CrudModal>
    </AuthenticatedLayout>
</template>
