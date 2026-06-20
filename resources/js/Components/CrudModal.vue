<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: { type: Boolean, default: false },
    titulo: { type: String, default: '' },
    maxWidth: { type: String, default: '2xl' },
});
const emit = defineEmits(['close']);
</script>

<template>
    <Modal :show="show" :max-width="maxWidth" @close="emit('close')">
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between border-b pb-3">
                <h3 class="text-lg font-semibold text-gray-900">{{ titulo }}</h3>
                <button type="button" @click="emit('close')" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <slot />
        </div>
    </Modal>
</template>
