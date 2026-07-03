<script setup>
import { computed } from 'vue';

const props = defineProps({
    variante: { type: String, default: 'editar' }, // editar | eliminar | ver
    label: { type: String, default: '' },
});

const cfg = computed(() => ({
    editar:   { txt: 'Editar',   cls: 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100 ring-indigo-100' },
    eliminar: { txt: 'Eliminar', cls: 'text-red-700 bg-red-50 hover:bg-red-100 ring-red-100' },
    ver:      { txt: 'Ver',      cls: 'text-slate-700 bg-slate-100 hover:bg-slate-200 ring-slate-200' },
}[props.variante] ?? { txt: props.label, cls: 'text-slate-700 bg-slate-100 hover:bg-slate-200 ring-slate-200' }));

const texto = computed(() => props.label || cfg.value.txt);
</script>

<template>
    <button
        type="button"
        :class="cfg.cls"
        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold ring-1 ring-inset transition focus:outline-none focus:ring-2"
    >
        <!-- Ícono lápiz (editar) -->
        <svg v-if="variante === 'editar'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
        <!-- Ícono basura (eliminar) -->
        <svg v-else-if="variante === 'eliminar'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
        <!-- Ícono ojo (ver) -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        {{ texto }}
    </button>
</template>
