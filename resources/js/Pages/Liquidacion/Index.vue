<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    resultado: { type: Object, default: null },
    trabajador: { type: String, default: null },
    filtros: { type: Object, default: () => ({ empresa_id: null, employee_id: null, fecha_cese: null }) },
});

const empresaId = ref(props.filtros.empresa_id);
const employeeId = ref(props.filtros.employee_id);
const fechaCese = ref(props.filtros.fecha_cese);

// Al cambiar empresa, recarga la lista de empleados.
watch(empresaId, (v) => {
    employeeId.value = null;
    router.get(route('liquidacion.index'), { empresa_id: v }, { preserveState: true, replace: true });
});

function calcular() {
    if (!employeeId.value || !fechaCese.value) { alert('Elige trabajador y fecha de cese.'); return; }
    router.get(route('liquidacion.index'), { empresa_id: empresaId.value, employee_id: employeeId.value, fecha_cese: fechaCese.value }, { preserveState: true });
}

const money = (v) => 'S/ ' + Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
</script>

<template>
    <Head title="Liquidación de cese" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Liquidación de beneficios sociales (cese)</h2></template>

        <div class="p-6 space-y-6">
            <div class="rounded-lg bg-orange-50 p-4 text-sm text-orange-900">
                🧾 Cuando un trabajador deja la empresa se le paga su <b>liquidación</b>: gratificación trunca (+9%), CTS trunca y vacaciones no gozadas, calculadas hasta la <b>fecha de cese</b>.
            </div>

            <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <label class="text-sm text-gray-700">Empresa</label>
                    <select v-model="empresaId" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Trabajador</label>
                    <select v-model="employeeId" :disabled="!empresaId" class="mt-1 block rounded-md border-gray-300 text-sm disabled:bg-gray-100">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="e in empleados" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                    </select>
                </div>
                <div><label class="text-sm text-gray-700">Fecha de cese</label><input v-model="fechaCese" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" /></div>
                <button @click="calcular" class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Calcular liquidación</button>
            </div>

            <div v-if="resultado?.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ resultado.error }}</div>

            <div v-else-if="resultado" class="space-y-4">
                <div class="rounded-lg bg-white p-4 shadow-sm text-sm text-gray-600">
                    <b class="text-gray-900">{{ trabajador }}</b> · Ingreso {{ resultado.fecha_ingreso }} · Cese {{ resultado.fecha_cese }} · Remuneración {{ money(resultado.rem_mensual) }}
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-lg border border-rose-200 bg-rose-50/40">
                        <div class="border-b border-rose-100 px-4 py-2 text-sm font-semibold text-rose-900">🎁 Gratificación trunca</div>
                        <div class="space-y-1 p-4 text-sm">
                            <div class="flex justify-between text-gray-600"><span>Meses / días</span><span>{{ resultado.gratificacion.meses }} / {{ resultado.gratificacion.dias }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">Gratificación</span><span>{{ money(resultado.gratificacion.monto) }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">Bonif. 9%</span><span>{{ money(resultado.gratificacion.bonificacion) }}</span></div>
                            <div class="mt-2 flex justify-between border-t border-rose-100 pt-2 font-bold text-rose-900"><span>Subtotal</span><span>{{ money(resultado.gratificacion.subtotal) }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-cyan-200 bg-cyan-50/40">
                        <div class="border-b border-cyan-100 px-4 py-2 text-sm font-semibold text-cyan-900">🏦 CTS trunca</div>
                        <div class="space-y-1 p-4 text-sm">
                            <div class="flex justify-between text-gray-600"><span>Meses / días</span><span>{{ resultado.cts.meses }} / {{ resultado.cts.dias }}</span></div>
                            <div class="flex justify-between text-gray-600"><span>1/6 gratif.</span><span>{{ money(resultado.cts.sexto) }}</span></div>
                            <div class="flex justify-between text-gray-600"><span>Rem. computable</span><span>{{ money(resultado.cts.rem_computable) }}</span></div>
                            <div class="mt-2 flex justify-between border-t border-cyan-100 pt-2 font-bold text-cyan-900"><span>Subtotal</span><span>{{ money(resultado.cts.monto) }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-lime-200 bg-lime-50/40">
                        <div class="border-b border-lime-100 px-4 py-2 text-sm font-semibold text-lime-900">🌴 Vacaciones no gozadas</div>
                        <div class="space-y-1 p-4 text-sm">
                            <div class="flex justify-between text-gray-600"><span>Ganados / gozados</span><span>{{ resultado.vacaciones.dias_ganados }} / {{ resultado.vacaciones.dias_gozados }}</span></div>
                            <div class="flex justify-between text-gray-600"><span>Días pendientes</span><span>{{ resultado.vacaciones.dias_pendientes }}</span></div>
                            <div class="mt-2 flex justify-between border-t border-lime-100 pt-2 font-bold text-lime-900"><span>Subtotal</span><span>{{ money(resultado.vacaciones.monto) }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-gray-900 p-5 text-white">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Total liquidación a pagar</span>
                        <span class="text-2xl font-bold text-green-300">{{ money(resultado.total) }}</span>
                    </div>
                </div>

                <div class="flex justify-end">
                    <a :href="route('liquidacion.pdf', { employee_id: filtros.employee_id, fecha_cese: filtros.fecha_cese })" class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">📄 Descargar liquidación (PDF)</a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
