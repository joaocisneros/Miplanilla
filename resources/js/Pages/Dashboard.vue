<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    charts: { type: Object, default: () => ({}) },
});

const page = usePage();
const permisos = computed(() => page.props.auth?.permissions ?? []);
const esAdmin = computed(() => (page.props.auth?.roles ?? []).includes('ADMIN'));
const can = (p) => permisos.value.includes(p);

const sol = (n) => 'S/ ' + Number(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// --- KPIs ---
const kpis = computed(() => [
    { label: 'Trabajadores', valor: props.stats.total_trabajadores ?? 0, icon: '👥', color: 'bg-indigo-500' },
    { label: `Planilla neta (${props.stats.mes_label ?? '—'})`, valor: sol(props.stats.planilla_neto), icon: '💰', color: 'bg-emerald-500' },
    { label: 'Aportes del empleador', valor: sol(props.stats.aportes_empleador), icon: '🏛️', color: 'bg-sky-500' },
    { label: 'Tardanzas / Faltas', valor: `${props.stats.tardanzas ?? 0} / ${props.stats.faltas ?? 0}`, icon: '⏰', color: 'bg-amber-500' },
]);

const paleta = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6'];

// --- Dona: trabajadores por empresa ---
const donaTrab = computed(() => ({
    series: props.charts.trabajadoresPorEmpresa?.data ?? [],
    options: {
        chart: { type: 'donut' },
        labels: props.charts.trabajadoresPorEmpresa?.labels ?? [],
        colors: paleta,
        legend: { position: 'bottom' },
        dataLabels: { enabled: true },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total' } } } } },
    },
}));

// --- Barras: costo de planilla por empresa ---
const barCosto = computed(() => ({
    series: [{ name: 'Planilla neta', data: props.charts.costoPorEmpresa?.data ?? [] }],
    options: {
        chart: { type: 'bar', toolbar: { show: false } },
        colors: ['#10b981'],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: props.charts.costoPorEmpresa?.labels ?? [] },
        yaxis: { labels: { formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE') } },
    },
}));

// --- Pie: AFP vs ONP ---
const piePension = computed(() => ({
    series: props.charts.pension?.data ?? [],
    options: {
        chart: { type: 'pie' },
        labels: props.charts.pension?.labels ?? [],
        colors: paleta,
        legend: { position: 'bottom' },
    },
}));

const hayDatos = computed(() => (props.charts.trabajadoresPorEmpresa?.data ?? []).some((n) => n > 0));

// --- Accesos rápidos ---
const accesos = computed(() => [
    { label: 'Empleados', desc: 'Gestiona el personal', icon: '👥', route: 'empleados.index', show: can('empleados.ver') },
    { label: 'Asistencia', desc: 'Importa y revisa marcaciones', icon: '🕒', route: 'asistencia.index', show: can('asistencia.ver') },
    { label: 'Planilla', desc: 'Genera y revisa planillas', icon: '💰', route: 'planilla.index', show: can('planilla.ver') },
    { label: 'Consolidado', desc: 'Totales de todas las empresas', icon: '📊', route: 'reportes.consolidado', show: can('reportes.ver') },
    { label: 'Empresas', desc: 'Empresas y configuración', icon: '🏢', route: 'admin.empresas.index', show: esAdmin.value },
]);
</script>

<template>
    <Head title="Inicio" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Inicio</h2>
        </template>

        <div class="space-y-6 p-6">
            <!-- Bienvenida -->
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-xl font-bold text-gray-800">Bienvenido, {{ page.props.auth.user.name }}</p>
                <p class="mt-1 text-sm text-gray-500">Resumen de tus {{ stats.empresas ?? 0 }} empresa(s). Datos del periodo <b>{{ stats.mes_label ?? '—' }}</b>.</p>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="k in kpis" :key="k.label" class="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm">
                    <div :class="k.color" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg text-2xl">{{ k.icon }}</div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-400">{{ k.label }}</p>
                        <p class="truncate text-2xl font-bold text-gray-800">{{ k.valor }}</p>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div v-if="hayDatos" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">👥 Trabajadores por empresa</h3>
                    <apexchart type="donut" height="280" :options="donaTrab.options" :series="donaTrab.series" />
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">💰 Costo de planilla por empresa ({{ stats.mes_label }})</h3>
                    <apexchart type="bar" height="280" :options="barCosto.options" :series="barCosto.series" />
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">🏦 Sistema de pensiones</h3>
                    <apexchart type="pie" height="280" :options="piePension.options" :series="piePension.series" />
                </div>
            </div>
            <div v-else class="rounded-xl bg-amber-50 p-5 text-sm text-amber-800 shadow-sm">
                Aún no hay datos suficientes para los gráficos. Registra empleados y genera una planilla para ver los indicadores.
            </div>

            <!-- Accesos rápidos -->
            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-400">Accesos rápidos</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="a in accesos.filter((x) => x.show)"
                        :key="a.label"
                        :href="route(a.route)"
                        class="flex items-center gap-4 rounded-lg bg-white p-5 shadow-sm transition hover:shadow-md"
                    >
                        <span class="text-3xl">{{ a.icon }}</span>
                        <span>
                            <span class="block font-semibold text-gray-800">{{ a.label }}</span>
                            <span class="block text-sm text-gray-500">{{ a.desc }}</span>
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
