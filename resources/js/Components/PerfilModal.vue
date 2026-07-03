<script setup>
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const user = computed(() => usePage().props.auth.user);
const inicial = computed(() => (user.value?.name || '?').charAt(0).toUpperCase());

// Pestaña activa
const tab = ref('datos');

// Mostrar/ocultar contraseñas
const mostrar = ref({ actual: false, nueva: false, confirmar: false });
const inp = 'block w-full rounded-lg border-gray-300 pr-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const inpSimple = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

// --- Formulario datos ---
const formDatos = useForm({
    name: user.value.name,
    email: user.value.email,
});
const guardarDatos = () => {
    formDatos.patch(route('profile.update'), { preserveScroll: true, preserveState: true });
};

// --- Formulario contraseña ---
const formPass = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const guardarPass = () => {
    formPass.put(route('password.update'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => formPass.reset(),
        onError: () => {
            if (formPass.errors.password) formPass.reset('password', 'password_confirmation');
            if (formPass.errors.current_password) formPass.reset('current_password');
        },
    });
};

const cerrar = () => emit('close');
</script>

<template>
    <Modal :show="show" max-width="lg" @close="cerrar">
        <div class="bg-white">
            <!-- Cabecera -->
            <div class="flex items-center gap-4 border-b border-gray-100 bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
                <div class="grid h-14 w-14 flex-none place-items-center rounded-full bg-white/10 text-2xl font-bold text-white ring-2 ring-white/20">
                    {{ inicial }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base font-semibold text-white">{{ user.name }}</p>
                    <p class="truncate text-sm text-slate-300">{{ user.email }}</p>
                </div>
                <button type="button" @click="cerrar" class="rounded-md p-1 text-slate-300 hover:bg-white/10 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Pestañas -->
            <div class="flex gap-1 border-b border-gray-100 px-4 pt-3">
                <button
                    type="button"
                    @click="tab = 'datos'"
                    :class="tab === 'datos' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="border-b-2 px-4 py-2 text-sm font-medium transition"
                >
                    Datos personales
                </button>
                <button
                    type="button"
                    @click="tab = 'seguridad'"
                    :class="tab === 'seguridad' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="border-b-2 px-4 py-2 text-sm font-medium transition"
                >
                    Seguridad
                </button>
            </div>

            <!-- Contenido -->
            <div class="px-6 py-6">
                <!-- TAB: DATOS -->
                <form v-show="tab === 'datos'" @submit.prevent="guardarDatos" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" v-model="formDatos.name" required autocomplete="name" :class="inpSimple" />
                        <InputError class="mt-1" :message="formDatos.errors.name" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
                        <input type="email" v-model="formDatos.email" required autocomplete="username" :class="inpSimple" />
                        <InputError class="mt-1" :message="formDatos.errors.email" />
                    </div>
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" :disabled="formDatos.processing" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50">Guardar cambios</button>
                        <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
                            <p v-if="formDatos.recentlySuccessful" class="text-sm font-medium text-emerald-600">✓ Guardado</p>
                        </Transition>
                    </div>
                </form>

                <!-- TAB: SEGURIDAD -->
                <form v-show="tab === 'seguridad'" @submit.prevent="guardarPass" class="space-y-4">
                    <!-- Actual -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Contraseña actual</label>
                        <div class="relative">
                            <input :type="mostrar.actual ? 'text' : 'password'" v-model="formPass.current_password" autocomplete="current-password" :class="inp" />
                            <button type="button" tabindex="-1" @click="mostrar.actual = !mostrar.actual" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <svg v-if="!mostrar.actual" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        <InputError class="mt-1" :message="formPass.errors.current_password" />
                    </div>
                    <!-- Nueva -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nueva contraseña</label>
                        <div class="relative">
                            <input :type="mostrar.nueva ? 'text' : 'password'" v-model="formPass.password" autocomplete="new-password" :class="inp" />
                            <button type="button" tabindex="-1" @click="mostrar.nueva = !mostrar.nueva" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <svg v-if="!mostrar.nueva" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        <InputError class="mt-1" :message="formPass.errors.password" />
                    </div>
                    <!-- Confirmar -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                        <div class="relative">
                            <input :type="mostrar.confirmar ? 'text' : 'password'" v-model="formPass.password_confirmation" autocomplete="new-password" :class="inp" />
                            <button type="button" tabindex="-1" @click="mostrar.confirmar = !mostrar.confirmar" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <svg v-if="!mostrar.confirmar" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        <InputError class="mt-1" :message="formPass.errors.password_confirmation" />
                    </div>
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" :disabled="formPass.processing" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50">Actualizar contraseña</button>
                        <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
                            <p v-if="formPass.recentlySuccessful" class="text-sm font-medium text-emerald-600">✓ Contraseña actualizada</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </Modal>
</template>
