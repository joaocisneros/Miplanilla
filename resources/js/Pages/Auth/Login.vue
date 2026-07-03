<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const mostrarPass = ref(false);

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const anio = new Date().getFullYear();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-900 px-4 py-10">
        <!-- Fondo sobrio con luz sutil -->
        <div class="pointer-events-none absolute -top-40 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 rounded-full bg-indigo-600/15 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom,rgba(30,41,59,0)_0%,rgba(2,6,23,0.6)_100%)]"></div>

        <div class="relative z-10 w-full max-w-md">
            <!-- Marca -->
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <div class="grid h-14 w-14 place-items-center rounded-2xl border border-white/15 bg-white/5 font-serif text-3xl font-bold text-white shadow-lg">M</div>
                <div>
                    <p class="text-xl font-semibold tracking-tight text-white">MiPlanilla</p>
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Planillas y Recursos Humanos</p>
                </div>
            </div>

            <!-- Tarjeta -->
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-2xl">
                <h2 class="text-lg font-semibold text-slate-900">Iniciar sesión</h2>
                <p class="mt-1 text-sm text-slate-500">Ingrese sus credenciales para continuar.</p>

                <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-5">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Correo electrónico</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="correo@empresa.com"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Contraseña</label>
                        <div class="relative">
                            <input
                                id="password"
                                :type="mostrarPass ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 pr-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            />
                            <button
                                type="button"
                                tabindex="-1"
                                @click="mostrarPass = !mostrarPass"
                                :aria-label="mostrarPass ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                            >
                                <svg v-if="!mostrarPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" v-model="form.remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Recordarme
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                        >
                            ¿Olvidó su contraseña?
                        </Link>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span v-if="form.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        {{ form.processing ? 'Ingresando…' : 'Iniciar sesión' }}
                    </button>
                </form>
            </div>

            <!-- Pie -->
            <p class="mt-6 text-center text-xs text-slate-500">Acceso restringido · uso exclusivo del personal autorizado</p>
            <p class="mt-1 text-center text-xs text-slate-600">© {{ anio }} Joao Cisneros — Todos los derechos reservados</p>
        </div>
    </div>
</template>
