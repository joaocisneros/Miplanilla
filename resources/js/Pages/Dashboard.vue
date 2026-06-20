<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const permisos = computed(() => page.props.auth?.permissions ?? []);
const esAdmin = computed(() => (page.props.auth?.roles ?? []).includes('ADMIN'));
const can = (p) => permisos.value.includes(p);
const empresaActiva = computed(() => {
    const c = page.props.contexto;
    const e = c?.empresas?.find((x) => x.id === c.empresa_id);
    return e ? (e.nombre_comercial || e.razon_social) : '—';
});

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

        <div class="p-6">
            <div class="mb-6 rounded-lg bg-white p-6 shadow-sm">
                <p class="text-sm text-gray-500">Empresa activa</p>
                <p class="text-2xl font-bold text-gray-800">{{ empresaActiva }}</p>
                <p class="mt-1 text-sm text-gray-500">Bienvenido, {{ page.props.auth.user.name }}.</p>
            </div>

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
    </AuthenticatedLayout>
</template>
