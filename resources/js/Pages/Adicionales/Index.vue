<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    filas: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const f = ref({
    empresa_id: props.filtros.empresa_id ?? null,
    anio: props.filtros.anio ?? new Date().getFullYear(),
    mes: props.filtros.mes ?? new Date().getMonth() + 1,
    quincena: props.filtros.quincena ?? '',
});

function filtrar() {
    router.get(route('adicionales.index'), {
        empresa_id: f.value.empresa_id,
        anio: f.value.anio,
        mes: f.value.mes,
        quincena: f.value.quincena,
    }, { preserveState: true, preserveScroll: true });
}

// Copia editable de las filas
const form = useForm({
    empresa_id: props.filtros.empresa_id ?? null,
    anio: props.filtros.anio ?? new Date().getFullYear(),
    mes: props.filtros.mes ?? new Date().getMonth() + 1,
    quincena: props.filtros.quincena ?? '',
    filas: props.filas.map((x) => ({ ...x })),
});

function guardar() {
    form.empresa_id = f.value.empresa_id;
    form.anio = f.value.anio;
    form.mes = f.value.mes;
    form.quincena = f.value.quincena === '' ? null : f.value.quincena;
    form.post(route('adicionales.store'), { preserveScroll: true });
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const anios = [2025, 2026, 2027];

// Lo que realmente se pagará (horas solo si está aprobado)
const totalPagar = computed(() => form.filas.reduce((s, r) =>
    s + (r.aprobado ? Number(r.monto_horas || 0) : 0)
      + Number(r.sabado || 0) + Number(r.domingo_feriado || 0) + Number(r.bono || 0), 0));
const inp = 'w-full rounded-md border-gray-300 shadow-sm text-sm text-right';
</script>

<template>
    <Head title="Horas y bonos" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Horas extra y bonos (aprobados por supervisor)</h2>
        </template>

        <div class="p-6 space-y-4">
            <!-- Filtros -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <div>
                        <label class="text-xs text-gray-500">Empresa</label>
                        <select v-model="f.empresa_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option :value="null">— Elegir —</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Año</label>
                        <select v-model="f.anio" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Mes</label>
                        <select v-model="f.mes" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option v-for="m in 12" :key="m" :value="m">{{ meses[m] }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Quincena</label>
                        <select v-model="f.quincena" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Mes completo</option>
                            <option :value="1">1ra quincena</option>
                            <option :value="2">2da quincena</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="filtrar" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Cargar</button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Las horas extra <b>solo se pagan si están marcadas como aprobadas</b>. El bono se paga siempre que lo registres. Ambos son afectos (pagan AFP/EsSalud/Renta).</p>
            </div>

            <!-- Grilla -->
            <div v-if="form.filas.length" class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-3 text-left">Trabajador</th>
                            <th class="px-3 py-3 text-right">Horas</th>
                            <th class="px-3 py-3 text-right">Min.</th>
                            <th class="px-3 py-3 text-center">¿Aprobado?</th>
                            <th class="px-3 py-3 text-right">Monto horas (S/)</th>
                            <th class="px-3 py-3 text-right">Sábados (S/)</th>
                            <th class="px-3 py-3 text-right">Dom./fer. (S/)</th>
                            <th class="px-3 py-3 text-right">Incentivo/bono (S/)</th>
                            <th class="px-3 py-3 text-left">Nota</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(r, i) in form.filas" :key="r.employee_id" :class="r.aprobado ? 'bg-green-50/40' : ''">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-800">{{ r.trabajador }}</div>
                                <div class="text-xs text-gray-400">{{ r.dni }} · {{ r.cargo }}</div>
                            </td>
                            <td class="px-3 py-2 w-20"><input v-model="r.horas" type="number" step="0.5" min="0" :class="inp" /></td>
                            <td class="px-3 py-2 w-16"><input v-model="r.minutos" type="number" min="0" max="59" :class="inp" /></td>
                            <td class="px-3 py-2 text-center">
                                <input v-model="r.aprobado" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-green-600" />
                            </td>
                            <td class="px-3 py-2 w-28"><input v-model="r.monto_horas" type="number" step="0.01" min="0" :class="[inp, !r.aprobado ? 'bg-gray-100 text-gray-400' : '']" /></td>
                            <td class="px-3 py-2 w-24"><input v-model="r.sabado" type="number" step="0.01" min="0" :class="inp" /></td>
                            <td class="px-3 py-2 w-24"><input v-model="r.domingo_feriado" type="number" step="0.01" min="0" :class="inp" /></td>
                            <td class="px-3 py-2 w-28"><input v-model="r.bono" type="number" step="0.01" min="0" :class="inp" /></td>
                            <td class="px-3 py-2"><input v-model="r.nota" type="text" maxlength="255" class="w-full rounded-md border-gray-300 text-sm" /></td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="8" class="px-3 py-3 text-right font-semibold text-gray-700">Total a pagar (aprobado):</td>
                            <td class="px-3 py-3 font-bold text-indigo-700">{{ money(totalPagar) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <div class="flex items-center justify-end gap-3 border-t bg-white p-4">
                    <span v-if="form.recentlySuccessful" class="text-sm text-green-600">✓ Guardado</span>
                    <button @click="guardar" :disabled="form.processing" class="rounded-md bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">Guardar adicionales</button>
                </div>
            </div>
            <div v-else class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                Elige una empresa y periodo, y presiona <b>Cargar</b>.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
