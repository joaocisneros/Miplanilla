<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import BotonAccion from '@/Components/BotonAccion.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    usuarios: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    empresasDisponibles: { type: Array, default: () => [] },
    rolesDetalle: { type: Array, default: () => [] },
    todosPermisos: { type: Array, default: () => [] },
});

const verPermisos = ref(false);

// Estado editable de los permisos por rol (copia local que se puede marcar/desmarcar).
const permisosPorRol = reactive({});
props.rolesDetalle.forEach((r) => { permisosPorRol[r.nombre] = [...r.permisos]; });

const tienePermiso = (rol, p) => permisosPorRol[rol]?.includes(p);
function togglePermiso(rol, p) {
    if (rol === 'ADMIN') return; // ADMIN es intocable
    const arr = permisosPorRol[rol];
    const i = arr.indexOf(p);
    if (i >= 0) arr.splice(i, 1); else arr.push(p);
}
function guardarPermisos(rol) {
    router.put(route('admin.roles.permisos'), { rol, permisos: permisosPorRol[rol] }, { preserveScroll: true });
}

const yoSoySuper = computed(() => (usePage().props.auth?.user?.id) === 1);

const mostrar = ref(false);
const editandoId = ref(null);
const form = useForm({ name: '', email: '', password: '', rol: 'RRHH', empleado_id: '', empresas: [] });

// Marca/desmarca una empresa en el acceso del usuario.
function toggleEmpresa(id) {
    const i = form.empresas.indexOf(id);
    if (i >= 0) form.empresas.splice(i, 1); else form.empresas.push(id);
}

// Empleado seleccionado (para mostrar su área/cargo debajo del selector)
const empleadoSel = computed(() => props.empleados.find((e) => String(e.id) === String(form.empleado_id)));

function abrirNuevo() { editandoId.value = null; form.reset(); form.rol = 'RRHH'; form.empleado_id = ''; form.empresas = []; form.clearErrors(); mostrar.value = true; }
function abrirEditar(u) {
    editandoId.value = u.id;
    form.name = u.name; form.email = u.email; form.password = '';
    form.rol = u.rol ?? 'RRHH'; form.empleado_id = u.empleado_id ?? '';
    form.empresas = [...(u.empresas_ids ?? [])];
    form.clearErrors(); mostrar.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.usuarios.update', editandoId.value), opts) : form.post(route('admin.usuarios.store'), opts);
}
function eliminar(u) { if (confirm(`¿Eliminar al usuario "${u.name}"?`)) form.delete(route('admin.usuarios.destroy', u.id), { preserveScroll: true }); }
function toggleActivo(u) {
    const accion = u.activo ? 'desactivar' : 'activar';
    if (confirm(`¿Seguro que deseas ${accion} a "${u.name}"?${u.activo ? '\n\nNo podrá iniciar sesión, pero se conserva todo su historial.' : ''}`)) {
        router.patch(route('admin.usuarios.estado', u.id), {}, { preserveScroll: true });
    }
}

// ¿Se puede editar/borrar este usuario? El super admin está protegido.
const puedeEditar = (u) => !u.es_super || yoSoySuper.value;
const puedeBorrar = (u) => !u.es_super;

const colorRol = (r) => ({ ADMIN: 'bg-purple-100 text-purple-800', RRHH: 'bg-indigo-100 text-indigo-800', SUPERVISOR: 'bg-amber-100 text-amber-800', EMPLEADO: 'bg-gray-100 text-gray-700' }[r] ?? 'bg-gray-100 text-gray-700');
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <Head title="Usuarios" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Usuarios del sistema</h2>
                <button @click="abrirNuevo" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nuevo usuario</button>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                Cada usuario tiene <b>un rol</b> que define qué puede ver y hacer. ADMIN: todo · RRHH: empleados/asistencia/planilla · SUPERVISOR: ver y validar asistencia · EMPLEADO: solo lo suyo.
                Puedes <b>vincular un usuario a un trabajador</b> (opcional) para saber su empresa/área/cargo — no se mezcla ni duplica nada.
            </div>

            <!-- Permisos por rol (EDITABLE) -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="verPermisos = !verPermisos" class="flex w-full items-center justify-between text-sm font-semibold text-gray-700">
                    <span>🔑 Permisos de cada rol <span class="font-normal text-gray-400">(marca/desmarca y guarda)</span></span>
                    <span class="text-gray-400">{{ verPermisos ? '▲ ocultar' : '▼ ver / editar' }}</span>
                </button>
                <div v-if="verPermisos" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="r in rolesDetalle" :key="r.nombre" class="flex flex-col rounded-md border border-gray-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span :class="colorRol(r.nombre)" class="rounded-full px-2 py-1 text-xs font-semibold">{{ r.nombre }}</span>
                            <span v-if="r.nombre === 'ADMIN'" class="text-xs text-gray-400">🛡️ fijo</span>
                        </div>
                        <ul class="flex-1 space-y-1 text-xs text-gray-700">
                            <li v-for="p in todosPermisos" :key="p">
                                <label class="flex cursor-pointer items-center gap-2" :class="r.nombre === 'ADMIN' && 'cursor-not-allowed opacity-70'">
                                    <input
                                        type="checkbox"
                                        class="rounded"
                                        :checked="r.nombre === 'ADMIN' ? true : tienePermiso(r.nombre, p)"
                                        :disabled="r.nombre === 'ADMIN'"
                                        @change="togglePermiso(r.nombre, p)"
                                    />
                                    <span>{{ p }}</span>
                                </label>
                            </li>
                        </ul>
                        <button
                            v-if="r.nombre !== 'ADMIN'"
                            type="button"
                            @click="guardarPermisos(r.nombre)"
                            class="mt-3 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                        >Guardar permisos de {{ r.nombre }}</button>
                        <p v-else class="mt-3 text-xs text-gray-400">El ADMIN siempre tiene todos los permisos.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Correo</th>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Último acceso</th>
                            <th class="px-4 py-3">Trabajador vinculado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="u in usuarios" :key="u.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ u.name }}
                                <span v-if="u.es_super" class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800" title="Cuenta protegida: no se puede eliminar ni cambiar su rol.">🛡️ Super admin</span>
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    <template v-if="u.es_super || !u.empresas_nombres?.length">
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">🏢 Todas las empresas</span>
                                    </template>
                                    <template v-else>
                                        <span v-for="n in u.empresas_nombres" :key="n" class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">🏢 {{ n }}</span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ u.email }}</td>
                            <td class="px-4 py-3"><span :class="colorRol(u.rol)" class="rounded-full px-2 py-1 text-xs font-semibold">{{ u.rol ?? 'sin rol' }}</span></td>
                            <td class="px-4 py-3">
                                <span :class="u.activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold">
                                    <span :class="u.activo ? 'bg-green-500' : 'bg-gray-400'" class="h-1.5 w-1.5 rounded-full"></span>{{ u.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                <span v-if="u.ultimo_acceso" :title="u.ultimo_acceso_fecha">🟢 {{ u.ultimo_acceso }}</span>
                                <span v-else class="text-xs text-gray-400">nunca ha entrado</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                <template v-if="u.empleado">
                                    <span class="font-medium">{{ u.empleado.nombre }}</span>
                                    <span class="block text-xs text-gray-500">{{ u.empleado.empresa }} · {{ u.empleado.cargo || '—' }} · {{ u.empleado.area || '—' }}</span>
                                </template>
                                <span v-else class="text-xs text-gray-400">— (solo administra)</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <BotonAccion v-if="puedeEditar(u)" variante="editar" @click="abrirEditar(u)" />
                                    <button v-if="!u.es_super" @click="toggleActivo(u)" :class="u.activo ? 'text-amber-700 bg-amber-50 hover:bg-amber-100 ring-amber-100' : 'text-green-700 bg-green-50 hover:bg-green-100 ring-green-100'" class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold ring-1 ring-inset transition">
                                        <svg v-if="u.activo" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        {{ u.activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    <BotonAccion v-if="puedeBorrar(u)" variante="eliminar" @click="eliminar(u)" />
                                    <span v-if="u.es_super" class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" /></svg>Protegido</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="usuarios.length === 0"><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin usuarios.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" max-width="3xl" :titulo="editandoId ? 'Editar usuario' : 'Nuevo usuario'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                <div><label class="text-sm font-medium text-gray-700">Nombre</label><input v-model="form.name" :class="inp" /><p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p></div>
                <div><label class="text-sm font-medium text-gray-700">Correo</label><input v-model="form.email" type="email" :class="inp" /><p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p></div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Contraseña <span v-if="editandoId" class="text-xs font-normal text-gray-400">(en blanco = no cambiar)</span></label>
                    <input v-model="form.password" type="password" :class="inp" autocomplete="new-password" />
                    <p v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Rol</label>
                    <select v-model="form.rol" :class="inp">
                        <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <p v-if="form.errors.rol" class="text-xs text-red-600">{{ form.errors.rol }}</p>
                </div>

                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 sm:col-span-2">
                    <label class="text-sm font-medium text-gray-700">Empresas con acceso</label>
                    <p class="mb-2 text-xs text-gray-400">Sin marcar = todas (admin). Para un contador/auditor, marca solo la suya.</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <label v-for="e in empresasDisponibles" :key="e.id" :class="form.empresas.includes(e.id) ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-white'" class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition hover:bg-gray-50">
                            <input type="checkbox" :checked="form.empresas.includes(e.id)" @change="toggleEmpresa(e.id)" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-gray-700">{{ e.nombre }}</span>
                        </label>
                    </div>
                    <p v-if="form.empresas.length === 0" class="mt-1.5 text-xs text-amber-600">Sin empresas marcadas = acceso a todas.</p>
                    <p v-else class="mt-1.5 text-xs text-emerald-600">Acceso limitado a {{ form.empresas.length }} empresa(s).</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-gray-700">Vincular a un trabajador <span class="text-xs font-normal text-gray-400">(opcional)</span></label>
                    <select v-model="form.empleado_id" :class="inp">
                        <option value="">— No vincular (solo administra) —</option>
                        <option v-for="e in empleados" :key="e.id" :value="e.id">{{ e.nombre }} — {{ e.empresa }}</option>
                    </select>
                    <p v-if="empleadoSel" class="mt-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs text-indigo-700">
                        {{ empleadoSel.empresa }} · Cargo: <b>{{ empleadoSel.cargo || '—' }}</b> · Área: <b>{{ empleadoSel.area || '—' }}</b>
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 sm:col-span-2">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Crear' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
