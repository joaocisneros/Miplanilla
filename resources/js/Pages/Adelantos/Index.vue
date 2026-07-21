<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import BotonAccion from '@/Components/BotonAccion.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    registros: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({ empresa_id: null }) },
});

const empresaId = ref(props.filtros.empresa_id);
function filtrar() { router.get(route('adelantos.index'), { empresa_id: empresaId.value }, { preserveState: true }); }

const hoy = new Date();
const mostrar = ref(false);
const form = useForm({
    empresa_id: null, employee_id: null, tipo: 'adelanto',
    anio: hoy.getFullYear(), mes: hoy.getMonth() + 1, monto: '', cuotas: 2, concepto: '',
});

function abrir() {
    if (!empresaId.value) { alert('Elige una empresa primero.'); return; }
    form.reset(); form.clearErrors();
    form.empresa_id = empresaId.value;
    form.anio = hoy.getFullYear(); form.mes = hoy.getMonth() + 1; form.tipo = 'adelanto'; form.cuotas = 2;
    mostrar.value = true;
}
function guardar() {
    form.post(route('adelantos.store'), { preserveScroll: true, onSuccess: () => { mostrar.value = false; } });
}
function eliminar(r) {
    if (r.grupo) {
        if (confirm('Este registro es parte de un préstamo. ¿Eliminar TODO el préstamo (todas las cuotas)?')) router.delete(route('adelantos.destroy-grupo', r.grupo), { preserveScroll: true });
    } else if (confirm('¿Eliminar este adelanto?')) {
        router.delete(route('adelantos.destroy', r.id), { preserveScroll: true });
    }
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';

// Vista previa EXACTA de lo que se va a registrar (mismo cálculo que el
// servidor: la última cuota ajusta el redondeo para cuadrar el total).
const previewCuotas = computed(() => {
    const total = Number(form.monto);
    if (!total || total <= 0) return [];
    if (form.tipo === 'adelanto') {
        return [{ n: 1, de: 1, mes: Number(form.mes), anio: Number(form.anio), monto: total }];
    }
    const n = Number(form.cuotas);
    if (!(n > 0)) return [];
    const cuota = Math.round((total / n) * 100) / 100;
    let acumulado = 0;
    let mes = Number(form.mes);
    let anio = Number(form.anio);
    const filas = [];
    for (let i = 1; i <= n; i++) {
        const montoCuota = i === n ? Math.round((total - acumulado) * 100) / 100 : cuota;
        acumulado += montoCuota;
        filas.push({ n: i, de: n, mes, anio, monto: montoCuota });
        mes++;
        if (mes > 12) { mes = 1; anio++; }
    }
    return filas;
});
</script>

<template>
    <Head title="Adelantos / Préstamos" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Adelantos y Préstamos</h2></template>

        <div class="p-6 space-y-6">
            <div class="rounded-lg bg-fuchsia-50 p-4 text-sm text-fuchsia-900">
                💸 Registra el dinero que le das al trabajador y se <b>descuenta solo</b> en la planilla.
                <b>Adelanto</b>: un único descuento en el mes que indiques. <b>Préstamo</b>: se divide en cuotas mensuales que se descuentan automáticamente.
            </div>

            <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <label class="text-sm text-gray-700">Empresa</label>
                    <select v-model="empresaId" @change="filtrar" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
                <button v-if="filtros.empresa_id" @click="abrir" class="rounded-md bg-fuchsia-600 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-700">+ Registrar adelanto / préstamo</button>
            </div>

            <div v-if="filtros.empresa_id" class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="px-4 py-3">Trabajador</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Periodo</th><th class="px-4 py-3 text-center">Cuota</th><th class="px-4 py-3 text-right">Monto</th><th class="px-4 py-3">Concepto</th><th class="px-4 py-3 text-right">Acción</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="r in registros" :key="r.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ r.trabajador }}</td>
                            <td class="px-4 py-2"><span :class="r.tipo === 'prestamo' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800'" class="rounded-full px-2 py-1 text-xs font-semibold">{{ r.tipo === 'prestamo' ? 'Préstamo' : 'Adelanto' }}</span></td>
                            <td class="px-4 py-2">{{ meses[r.mes] }} {{ r.anio }}</td>
                            <td class="px-4 py-2 text-center text-gray-500">{{ r.cuota ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-red-600">{{ money(r.monto) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ r.concepto }}</td>
                            <td class="px-4 py-2 text-right"><BotonAccion variante="eliminar" @click="eliminar(r)" /></td>
                        </tr>
                        <tr v-if="registros.length === 0"><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin adelantos ni préstamos registrados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" titulo="Registrar adelanto / préstamo" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-700">Trabajador</label>
                    <select v-model="form.employee_id" :class="inp">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="e in empleados" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                    </select>
                    <p v-if="form.errors.employee_id" class="text-xs text-red-600">{{ form.errors.employee_id }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-700">¿Se paga en un solo descuento, o en cuotas?</label>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        <button type="button" @click="form.tipo = 'adelanto'"
                            class="rounded-md border-2 px-3 py-2.5 text-left text-sm transition"
                            :class="form.tipo === 'adelanto' ? 'border-fuchsia-600 bg-fuchsia-50' : 'border-gray-200 hover:border-gray-300'">
                            <div class="font-semibold text-gray-800">💵 Adelanto</div>
                            <div class="text-xs text-gray-500">Se descuenta TODO en un solo mes</div>
                        </button>
                        <button type="button" @click="form.tipo = 'prestamo'"
                            class="rounded-md border-2 px-3 py-2.5 text-left text-sm transition"
                            :class="form.tipo === 'prestamo' ? 'border-fuchsia-600 bg-fuchsia-50' : 'border-gray-200 hover:border-gray-300'">
                            <div class="font-semibold text-gray-800">📅 Préstamo</div>
                            <div class="text-xs text-gray-500">Se reparte en varios meses (cuotas)</div>
                        </button>
                    </div>
                </div>
                <div><label class="text-sm text-gray-700">{{ form.tipo === 'prestamo' ? 'Monto total del préstamo' : 'Monto a descontar' }}</label><input v-model="form.monto" type="number" step="0.01" :class="inp" placeholder="0.00" /><p v-if="form.errors.monto" class="text-xs text-red-600">{{ form.errors.monto }}</p></div>
                <div v-if="form.tipo === 'prestamo'">
                    <label class="text-sm text-gray-700">¿En cuántos meses se paga?</label>
                    <input v-model="form.cuotas" type="number" min="1" :class="inp" placeholder="Ej: 3" />
                    <p v-if="form.errors.cuotas" class="text-xs text-red-600">{{ form.errors.cuotas }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">{{ form.tipo === 'prestamo' ? 'Empieza a descontarse en' : 'Mes del descuento' }}</label>
                    <div class="mt-1 flex gap-2">
                        <select v-model="form.mes" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"><option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option></select>
                        <input v-model="form.anio" type="number" class="block w-24 rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                </div>
                <div class="md:col-span-2"><label class="text-sm text-gray-700">Concepto / motivo</label><input v-model="form.concepto" :class="inp" placeholder="Ej: adelanto de sueldo, préstamo personal" /></div>

                <!-- Vista previa: exactamente qué se va a descontar y cuándo -->
                <div v-if="previewCuotas.length" class="md:col-span-2 rounded-lg border border-fuchsia-200 bg-fuchsia-50/60 p-3">
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-fuchsia-700">Así se descontará en la planilla</p>
                    <div v-if="form.tipo === 'adelanto'" class="text-sm text-gray-800">
                        Un solo descuento de <b>{{ money(previewCuotas[0].monto) }}</b> en <b>{{ meses[previewCuotas[0].mes] }} {{ previewCuotas[0].anio }}</b>.
                    </div>
                    <ul v-else class="space-y-0.5 text-sm text-gray-800">
                        <li v-for="c in previewCuotas" :key="c.n" class="flex items-center justify-between">
                            <span>Cuota {{ c.n }}/{{ c.de }} — {{ meses[c.mes] }} {{ c.anio }}</span>
                            <span class="font-semibold tabular-nums">{{ money(c.monto) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-3 md:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-fuchsia-600 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-700 disabled:opacity-50">Registrar</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
