<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    audits: { type: Object, default: () => ({ data: [], links: [] }) },
    filtros: { type: Object, default: () => ({}) },
});

const filtros = [
    { valor: '', label: 'Todo' },
    { valor: 'created', label: 'Creaciones' },
    { valor: 'updated', label: 'Ediciones' },
    { valor: 'deleted', label: 'Eliminaciones' },
];

function filtrar(valor) {
    router.get(route('admin.auditoria.index'), { evento: valor || undefined }, { preserveScroll: true, preserveState: true });
}

const colorEvento = (raw) => ({
    created: 'bg-green-100 text-green-700',
    updated: 'bg-amber-100 text-amber-700',
    deleted: 'bg-red-100 text-red-700',
    restored: 'bg-sky-100 text-sky-700',
}[raw] ?? 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head title="Bitácora" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Bitácora / Auditoría</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <p class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    📖 Registro automático de cambios: <b>quién</b> cambió <b>qué</b> y <b>cuándo</b>. Sirve para controlar todo lo que hacen los usuarios en el sistema.
                </p>

                <!-- Filtros -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="f in filtros"
                        :key="f.valor"
                        @click="filtrar(f.valor)"
                        :class="(filtros.evento ?? '') === f.valor ? 'bg-slate-900 text-white' : 'bg-white text-gray-600 ring-1 ring-inset ring-gray-200 hover:bg-gray-50'"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                    >
                        {{ f.label }}
                    </button>
                </div>

                <!-- Tabla -->
                <div class="overflow-x-auto rounded-lg bg-white shadow">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Acción</th>
                                <th class="px-4 py-3">Registro</th>
                                <th class="px-4 py-3">Cambios</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="a in audits.data" :key="a.id" class="align-top hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ a.fecha }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ a.usuario }}</td>
                                <td class="px-4 py-3">
                                    <span :class="colorEvento(a.evento_raw)" class="rounded-full px-2 py-1 text-xs font-semibold">{{ a.evento }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ a.modelo }} <span class="text-xs text-gray-400">#{{ a.registro_id }}</span></td>
                                <td class="px-4 py-3">
                                    <div v-if="a.cambios.length" class="space-y-0.5">
                                        <div v-for="(c, i) in a.cambios" :key="i" class="text-xs text-gray-600">
                                            <span class="font-medium text-gray-700">{{ c.campo }}:</span>
                                            <span class="text-gray-400 line-through">{{ c.antes }}</span>
                                            <span class="mx-1">→</span>
                                            <span class="text-gray-800">{{ c.despues }}</span>
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!audits.data.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No hay movimientos registrados todavía.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="audits.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                    <template v-for="(l, i) in audits.links" :key="i">
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            preserve-scroll
                            :class="l.active ? 'bg-slate-900 text-white' : 'bg-white text-gray-600 ring-1 ring-inset ring-gray-200 hover:bg-gray-50'"
                            class="rounded-md px-3 py-1.5 text-sm"
                            v-html="l.label"
                        />
                        <span v-else class="rounded-md px-3 py-1.5 text-sm text-gray-300" v-html="l.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
