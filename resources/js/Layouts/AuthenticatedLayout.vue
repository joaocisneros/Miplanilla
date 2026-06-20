<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const sidebarAbierto = ref(true);

const roles = computed(() => page.props.auth?.roles ?? []);
const esAdmin = computed(() => roles.value.includes('ADMIN'));
const permisos = computed(() => page.props.auth?.permissions ?? []);
const can = (p) => permisos.value.includes(p);

const contexto = computed(() => page.props.contexto ?? { empresas: [], sedes: [], empresa_id: null, sede_id: null });

function cambiarEmpresa(e) {
    router.post(route('contexto.empresa'), { empresa_id: e.target.value }, { preserveScroll: true });
}
function cambiarSede(e) {
    router.post(route('contexto.sede'), { sede_id: e.target.value || null }, { preserveScroll: true });
}

// Definición del menú agrupado
const menu = computed(() => [
    {
        titulo: 'Operación',
        items: [
            { label: 'Dashboard', icon: '🏠', route: 'dashboard', active: 'dashboard', show: true },
            { label: 'Empleados', icon: '👥', route: 'empleados.index', active: 'empleados.*', show: can('empleados.ver') },
            { label: 'Asistencia', icon: '🕒', route: 'asistencia.index', active: 'asistencia.*', show: can('asistencia.ver') },
            { label: 'Planilla', icon: '💰', route: 'planilla.index', active: 'planilla.*', show: can('planilla.ver') },
            { label: 'Consolidado', icon: '📊', route: 'reportes.consolidado', active: 'reportes.*', show: can('reportes.ver') },
        ],
    },
    {
        titulo: 'Configuración',
        items: [
            { label: 'Empresas', icon: '🏢', route: 'admin.empresas.index', active: 'admin.empresas.*', show: esAdmin.value },
            { label: 'Sedes', icon: '📍', route: 'admin.sedes.index', active: 'admin.sedes.*', show: esAdmin.value },
            { label: 'Áreas', icon: '🗂️', route: 'admin.areas.index', active: 'admin.areas.*', show: esAdmin.value },
            { label: 'Cargos', icon: '🏷️', route: 'admin.cargos.index', active: 'admin.cargos.*', show: esAdmin.value },
            { label: 'Turnos', icon: '⏰', route: 'admin.turnos.index', active: 'admin.turnos.*', show: esAdmin.value },
            { label: 'Parámetros', icon: '⚙️', route: 'admin.parametros.index', active: 'admin.parametros.*', show: esAdmin.value },
            { label: 'Tasas AFP', icon: '📈', route: 'admin.tasas-afp.index', active: 'admin.tasas-afp.*', show: esAdmin.value },
            { label: 'Conceptos', icon: '🧮', route: 'admin.conceptos.index', active: 'admin.conceptos.*', show: esAdmin.value },
        ],
    },
]);
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Sidebar -->
        <aside
            :class="sidebarAbierto ? 'w-64' : 'w-0 -translate-x-full'"
            class="fixed inset-y-0 left-0 z-30 flex flex-col overflow-hidden bg-gray-900 text-gray-200 transition-all duration-200"
        >
            <div class="flex h-16 items-center gap-2 border-b border-gray-800 px-4">
                <ApplicationLogo class="h-8 w-auto fill-current text-white" />
                <span class="text-lg font-semibold text-white">MiPlanilla</span>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <template v-for="grupo in menu" :key="grupo.titulo">
                    <div v-if="grupo.items.some((i) => i.show)" class="mb-6">
                        <p class="mb-2 px-2 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ grupo.titulo }}</p>
                        <template v-for="item in grupo.items" :key="item.label">
                            <Link
                                v-if="item.show"
                                :href="route(item.route)"
                                :class="route().current(item.active)
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
                                class="mb-1 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            >
                                <span class="text-base">{{ item.icon }}</span>
                                <span>{{ item.label }}</span>
                            </Link>
                        </template>
                    </div>
                </template>
            </nav>
        </aside>

        <!-- Contenido -->
        <div :class="sidebarAbierto ? 'ml-64' : 'ml-0'" class="flex min-h-screen flex-col transition-all duration-200">
            <!-- Topbar -->
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <button @click="sidebarAbierto = !sidebarAbierto" class="rounded-md p-2 text-gray-500 hover:bg-gray-100" title="Menú">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <!-- Selector empresa / sede -->
                    <select :value="contexto.empresa_id" @change="cambiarEmpresa" class="rounded-md border-gray-300 py-1.5 text-sm" title="Empresa activa">
                        <option v-for="e in contexto.empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial || e.razon_social }}</option>
                    </select>
                    <select v-if="contexto.sedes.length" :value="contexto.sede_id ?? ''" @change="cambiarSede" class="rounded-md border-gray-300 py-1.5 text-sm" title="Sede activa">
                        <option value="">Todas las sedes</option>
                        <option v-for="s in contexto.sedes" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                </div>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ page.props.auth.user.name }}
                            <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Perfil</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">Cerrar sesión</DropdownLink>
                    </template>
                </Dropdown>
            </header>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mx-6 mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="mx-6 mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ $page.props.flash.error }}</div>

            <!-- Header de página -->
            <div v-if="$slots.header" class="bg-white px-6 py-4 shadow-sm">
                <slot name="header" />
            </div>

            <!-- Contenido principal -->
            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
