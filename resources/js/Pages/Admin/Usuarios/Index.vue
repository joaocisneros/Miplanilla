<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CrudModal from '@/Components/CrudModal.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    usuarios: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
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
const form = useForm({ name: '', email: '', password: '', rol: 'RRHH', empleado_id: '' });

// Empleado seleccionado (para mostrar su área/cargo debajo del selector)
const empleadoSel = computed(() => props.empleados.find((e) => String(e.id) === String(form.empleado_id)));

function abrirNuevo() { editandoId.value = null; form.reset(); form.rol = 'RRHH'; form.empleado_id = ''; form.clearErrors(); mostrar.value = true; }
function abrirEditar(u) {
    editandoId.value = u.id;
    form.name = u.name; form.email = u.email; form.password = '';
    form.rol = u.rol ?? 'RRHH'; form.empleado_id = u.empleado_id ?? '';
    form.clearErrors(); mostrar.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => { mostrar.value = false; } };
    editandoId.value ? form.put(route('admin.usuarios.update', editandoId.value), opts) : form.post(route('admin.usuarios.store'), opts);
}
function eliminar(u) { if (confirm(`¿Eliminar al usuario "${u.name}"?`)) form.delete(route('admin.usuarios.destroy', u.id), { preserveScroll: true }); }

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
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ u.email }}</td>
                            <td class="px-4 py-3"><span :class="colorRol(u.rol)" class="rounded-full px-2 py-1 text-xs font-semibold">{{ u.rol ?? 'sin rol' }}</span></td>
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
                                <button v-if="puedeEditar(u)" @click="abrirEditar(u)" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button v-if="puedeBorrar(u)" @click="eliminar(u)" class="ml-3 text-red-600 hover:text-red-900">Eliminar</button>
                                <span v-if="u.es_super" class="text-xs text-gray-400">protegido</span>
                            </td>
                        </tr>
                        <tr v-if="usuarios.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-500">Sin usuarios.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrudModal :show="mostrar" :titulo="editandoId ? 'Editar usuario' : 'Nuevo usuario'" @close="mostrar = false">
            <form @submit.prevent="guardar" class="grid grid-cols-1 gap-4">
                <div><label class="text-sm text-gray-700">Nombre</label><input v-model="form.name" :class="inp" /><p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p></div>
                <div><label class="text-sm text-gray-700">Correo</label><input v-model="form.email" type="email" :class="inp" /><p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p></div>
                <div>
                    <label class="text-sm text-gray-700">Contraseña {{ editandoId ? '(dejar en blanco para no cambiar)' : '' }}</label>
                    <input v-model="form.password" type="password" :class="inp" autocomplete="new-password" />
                    <p v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Rol</label>
                    <select v-model="form.rol" :class="inp">
                        <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <p v-if="form.errors.rol" class="text-xs text-red-600">{{ form.errors.rol }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-700">Vincular a un trabajador <span class="text-xs text-gray-400">(opcional)</span></label>
                    <select v-model="form.empleado_id" :class="inp">
                        <option value="">— No vincular (solo administra) —</option>
                        <option v-for="e in empleados" :key="e.id" :value="e.id">{{ e.nombre }} — {{ e.empresa }}</option>
                    </select>
                    <p v-if="empleadoSel" class="mt-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs text-indigo-700">
                        {{ empleadoSel.empresa }} · Cargo: <b>{{ empleadoSel.cargo || '—' }}</b> · Área: <b>{{ empleadoSel.area || '—' }}</b>
                    </p>
                    <p class="mt-1 text-xs text-gray-400">Solo si el usuario es un trabajador de la planilla. Los admin no se vinculan.</p>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="mostrar = false" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ editandoId ? 'Actualizar' : 'Crear' }}</button>
                </div>
            </form>
        </CrudModal>
    </AuthenticatedLayout>
</template>
