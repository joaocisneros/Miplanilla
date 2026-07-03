<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    sedes: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    cargos: { type: Array, default: () => [] },
    turnos: { type: Array, default: () => [] },
    tiposContrato: { type: Array, default: () => [] },
    empleado: { type: Object, default: null },
    contrato: { type: Object, default: null },
    derechohabientes: { type: Array, default: () => [] },
    empresaIdInicial: { default: '' },
    modo: { type: String, default: 'create' }, // create | edit
});

const emit = defineEmits(['guardado', 'cancelar']);

const e = props.empleado ?? {};
const c = props.contrato ?? {};

const form = useForm({
    empresa_id: e.empresa_id ?? props.empresaIdInicial ?? '',
    // Datos personales
    apellido_paterno: e.apellido_paterno ?? '',
    apellido_materno: e.apellido_materno ?? '',
    nombres: e.nombres ?? '',
    tipo_documento: e.tipo_documento ?? 'DNI',
    numero_documento: e.numero_documento ?? '',
    fecha_nacimiento: e.fecha_nacimiento?.substring(0, 10) ?? '',
    genero: e.genero ?? '',
    estado_civil: e.estado_civil ?? '',
    lugar_nacimiento: e.lugar_nacimiento ?? '',
    profesion: e.profesion ?? '',
    telefono: e.telefono ?? '',
    correo: e.correo ?? '',
    sede_id: e.sede_id ?? '',
    // Domicilio
    direccion: e.direccion ?? '',
    distrito: e.distrito ?? '',
    provincia: e.provincia ?? '',
    departamento: e.departamento ?? '',
    tipo_vivienda: e.tipo_vivienda ?? '',
    nivel_educativo: e.nivel_educativo ?? '',
    // Bancarios
    banco: e.banco ?? '',
    cuenta_corriente: e.cuenta_corriente ?? '',
    cuenta_ahorros: e.cuenta_ahorros ?? '',
    cci: e.cci ?? '',
    codigo_biometrico: e.codigo_biometrico ?? '',
    // Emergencia
    emergencia_nombre: e.emergencia_nombre ?? '',
    emergencia_telefono: e.emergencia_telefono ?? '',
    emergencia_parentesco: e.emergencia_parentesco ?? '',
    // Contrato
    tipo_contrato: c.tipo_contrato ?? '',
    categoria_ocupacional: c.categoria_ocupacional ?? 'empleado',
    fecha_ingreso: c.fecha_ingreso?.substring(0, 10) ?? '',
    fecha_cese: c.fecha_cese?.substring(0, 10) ?? '',
    sueldo_basico: c.sueldo_basico ?? '',
    percibe_asignacion_familiar: c.percibe_asignacion_familiar ?? false,
    movilidad: c.movilidad ?? 0,
    otros: c.otros ?? 0,
    sistema_pensiones: c.sistema_pensiones ?? '',
    afp: c.afp ?? '',
    tipo_afp: c.tipo_afp ?? '',
    codigo_afp: c.codigo_afp ?? '',
    fecha_afiliacion_pension: c.fecha_afiliacion_pension?.substring(0, 10) ?? '',
    aporta_sctr: c.aporta_sctr ?? false,
    aporta_senati: c.aporta_senati ?? false,
    tiene_vida_ley: c.tiene_vida_ley ?? false,
    area_id: c.area_id ?? '',
    cargo_id: c.cargo_id ?? '',
    turno_id: c.turno_id ?? '',
    // Derechohabientes
    derechohabientes: props.derechohabientes.map((d) => ({
        tipo: d.tipo, nombres: d.nombres,
        fecha_nacimiento: d.fecha_nacimiento?.substring(0, 10) ?? '',
        estudia: !!d.estudia,
    })),
});

// Sede y área dependen de la empresa elegida
const tipoEsPlazoFijo = computed(() => props.tiposContrato.find((t) => t.value === form.tipo_contrato)?.plazo_fijo ?? false);
const sedesFiltradas = computed(() => props.sedes.filter((s) => String(s.empresa_id) === String(form.empresa_id)));
const areasFiltradas = computed(() => props.areas.filter((a) => String(a.empresa_id) === String(form.empresa_id)));

watch(() => form.empresa_id, () => {
    if (!sedesFiltradas.value.some((s) => String(s.id) === String(form.sede_id))) form.sede_id = '';
    if (!areasFiltradas.value.some((a) => String(a.id) === String(form.area_id))) form.area_id = '';
});

function agregarDH() {
    form.derechohabientes.push({ tipo: 'hijo', nombres: '', fecha_nacimiento: '', estudia: false });
}
function quitarDH(i) {
    form.derechohabientes.splice(i, 1);
}

function enviar() {
    const opts = { onSuccess: () => emit('guardado') };
    if (props.modo === 'edit') {
        form.put(route('empleados.update', props.empleado.id), opts);
    } else {
        form.post(route('empleados.store'), opts);
    }
}
const inp = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm';
</script>

<template>
    <form @submit.prevent="enviar" class="space-y-8">
        <!-- Datos personales -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">1. Datos personales</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-3"><label class="text-sm font-semibold text-gray-700">Empresa *</label><select v-model="form.empresa_id" :class="inp"><option value="">— Selecciona empresa —</option><option v-for="em in empresas" :key="em.id" :value="em.id">{{ em.nombre_comercial || em.razon_social }}</option></select><p v-if="form.errors.empresa_id" class="text-xs text-red-600">{{ form.errors.empresa_id }}</p></div>
                <div><label class="text-sm text-gray-700">Apellido paterno *</label><input v-model="form.apellido_paterno" :class="inp" /><p v-if="form.errors.apellido_paterno" class="text-xs text-red-600">{{ form.errors.apellido_paterno }}</p></div>
                <div><label class="text-sm text-gray-700">Apellido materno</label><input v-model="form.apellido_materno" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Nombres *</label><input v-model="form.nombres" :class="inp" /><p v-if="form.errors.nombres" class="text-xs text-red-600">{{ form.errors.nombres }}</p></div>
                <div><label class="text-sm text-gray-700">Tipo doc.</label><select v-model="form.tipo_documento" :class="inp"><option>DNI</option><option>CE</option><option>PASAPORTE</option></select></div>
                <div><label class="text-sm text-gray-700">N° documento *</label><input v-model="form.numero_documento" :class="inp" /><p v-if="form.errors.numero_documento" class="text-xs text-red-600">{{ form.errors.numero_documento }}</p></div>
                <div><label class="text-sm text-gray-700">Fecha nacimiento</label><input v-model="form.fecha_nacimiento" type="date" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Género</label><select v-model="form.genero" :class="inp"><option value="">—</option><option value="M">Masculino</option><option value="F">Femenino</option></select></div>
                <div><label class="text-sm text-gray-700">Estado civil</label><input v-model="form.estado_civil" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Lugar de nacimiento</label><input v-model="form.lugar_nacimiento" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Profesión u ocupación</label><input v-model="form.profesion" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Teléfono</label><input v-model="form.telefono" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Correo</label><input v-model="form.correo" type="email" :class="inp" /></div>
                <!-- Sede oculta: 1 sola sede por empresa (se asigna sola). Código biométrico oculto: no usan huellero. -->
                <div v-if="false"><label class="text-sm text-gray-700">Sede</label><select v-model="form.sede_id" :class="inp" :disabled="!form.empresa_id"><option value="">—</option><option v-for="s in sedesFiltradas" :key="s.id" :value="s.id">{{ s.nombre }}</option></select></div>
                <div v-if="false"><label class="text-sm text-gray-700">Código biométrico</label><input v-model="form.codigo_biometrico" :class="inp" /></div>
            </div>
        </section>

        <!-- Domicilio -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">2. Domicilio</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-3"><label class="text-sm text-gray-700">Dirección (Av./Jr./Calle/Psje.)</label><input v-model="form.direccion" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Distrito</label><input v-model="form.distrito" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Provincia</label><input v-model="form.provincia" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Departamento</label><input v-model="form.departamento" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Tipo de vivienda</label><select v-model="form.tipo_vivienda" :class="inp"><option value="">—</option><option value="propia">Propia</option><option value="alquilada">Alquilada</option><option value="otros">Otros</option></select></div>
                <div class="md:col-span-2"><label class="text-sm text-gray-700">Nivel educativo</label><input v-model="form.nivel_educativo" placeholder="Ej. Secundaria completa / Superior" :class="inp" /></div>
            </div>
        </section>

        <!-- Datos bancarios -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">3. Datos bancarios</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div><label class="text-sm text-gray-700">Banco</label><input v-model="form.banco" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Cuenta corriente</label><input v-model="form.cuenta_corriente" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Cuenta de ahorros</label><input v-model="form.cuenta_ahorros" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">CCI</label><input v-model="form.cci" :class="inp" /></div>
            </div>
        </section>

        <!-- Contrato -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">4. Datos laborales / contrato</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm text-gray-700">Tipo de contrato</label>
                    <select v-model="form.tipo_contrato" :class="inp">
                        <option value="">— Selecciona —</option>
                        <option v-for="t in tiposContrato" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <p v-if="form.errors.tipo_contrato" class="text-xs text-red-600">{{ form.errors.tipo_contrato }}</p>
                </div>
                <div><label class="text-sm text-gray-700">Categoría *</label><select v-model="form.categoria_ocupacional" :class="inp"><option value="empleado">Empleado</option><option value="obrero">Obrero</option></select></div>
                <div><label class="text-sm text-gray-700">Fecha de ingreso *</label><input v-model="form.fecha_ingreso" type="date" :class="inp" /><p v-if="form.errors.fecha_ingreso" class="text-xs text-red-600">{{ form.errors.fecha_ingreso }}</p></div>
                <div v-if="tipoEsPlazoFijo">
                    <label class="text-sm text-gray-700">Fecha de vencimiento (cese) *</label>
                    <input v-model="form.fecha_cese" type="date" :class="inp" />
                    <p class="text-xs text-amber-600">Requerida para contratos a plazo fijo.</p>
                    <p v-if="form.errors.fecha_cese" class="text-xs text-red-600">{{ form.errors.fecha_cese }}</p>
                </div>
                <div><label class="text-sm text-gray-700">Sueldo básico *</label><input v-model="form.sueldo_basico" type="number" step="0.01" :class="inp" /><p v-if="form.errors.sueldo_basico" class="text-xs text-red-600">{{ form.errors.sueldo_basico }}</p></div>
                <div><label class="text-sm text-gray-700">Movilidad</label><input v-model="form.movilidad" type="number" step="0.01" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Otros ingresos</label><input v-model="form.otros" type="number" step="0.01" :class="inp" /></div>
                <div class="flex items-center gap-2 pt-6"><input v-model="form.percibe_asignacion_familiar" type="checkbox" id="af" class="rounded" /><label for="af" class="text-sm">Percibe asignación familiar</label></div>
                <div><label class="text-sm text-gray-700">Área</label><select v-model="form.area_id" :class="inp" :disabled="!form.empresa_id"><option value="">{{ form.empresa_id ? '—' : '↑ Elige la empresa primero' }}</option><option v-for="a in areasFiltradas" :key="a.id" :value="a.id">{{ a.nombre }}</option></select><p v-if="!form.empresa_id" class="text-xs text-amber-600">Selecciona la empresa (arriba) para ver sus áreas.</p></div>
                <div><label class="text-sm text-gray-700">Cargo</label><select v-model="form.cargo_id" :class="inp"><option value="">—</option><option v-for="ca in cargos" :key="ca.id" :value="ca.id">{{ ca.nombre }}</option></select></div>
                <div><label class="text-sm text-gray-700">Turno</label><select v-model="form.turno_id" :class="inp"><option value="">—</option><option v-for="t in turnos" :key="t.id" :value="t.id">{{ t.nombre }}</option></select></div>
            </div>
        </section>

        <!-- Pensiones / seguros -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">5. Pensiones y seguros</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><label class="text-sm text-gray-700">Sistema de pensiones</label><select v-model="form.sistema_pensiones" :class="inp"><option value="">—</option><option value="AFP">AFP</option><option value="ONP">ONP</option></select></div>
                <div v-if="form.sistema_pensiones === 'AFP'"><label class="text-sm text-gray-700">AFP *</label><select v-model="form.afp" :class="inp"><option value="">—</option><option>INTEGRA</option><option>PRIMA</option><option>PROFUTURO</option><option>HABITAT</option></select><p v-if="form.errors.afp" class="text-xs text-red-600">{{ form.errors.afp }}</p></div>
                <div v-if="form.sistema_pensiones === 'AFP'"><label class="text-sm text-gray-700">Tipo comisión *</label><select v-model="form.tipo_afp" :class="inp"><option value="">—</option><option value="mixta">Mixta</option><option value="sueldo">Flujo (sueldo)</option></select><p v-if="form.errors.tipo_afp" class="text-xs text-red-600">{{ form.errors.tipo_afp }}</p></div>
                <div v-if="form.sistema_pensiones === 'AFP'"><label class="text-sm text-gray-700">Código AFP (CUSPP)</label><input v-model="form.codigo_afp" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Fecha afiliación pensión</label><input v-model="form.fecha_afiliacion_pension" type="date" :class="inp" /></div>
                <div class="flex items-center gap-2 pt-6"><input v-model="form.aporta_sctr" type="checkbox" id="sctr" class="rounded" /><label for="sctr" class="text-sm">Aporta SCTR</label></div>
                <div class="flex items-center gap-2 pt-6"><input v-model="form.aporta_senati" type="checkbox" id="sen" class="rounded" /><label for="sen" class="text-sm">Aporta Senati</label></div>
                <div class="flex items-center gap-2 pt-6"><input v-model="form.tiene_vida_ley" type="checkbox" id="vl" class="rounded" /><label for="vl" class="text-sm">Tiene Seguro Vida Ley</label></div>
            </div>
        </section>

        <!-- Contacto de emergencia -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">6. En caso de emergencia llamar a</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><label class="text-sm text-gray-700">Apellidos y nombres</label><input v-model="form.emergencia_nombre" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Teléfono</label><input v-model="form.emergencia_telefono" :class="inp" /></div>
                <div><label class="text-sm text-gray-700">Parentesco</label><input v-model="form.emergencia_parentesco" :class="inp" /></div>
            </div>
        </section>

        <!-- Derechohabientes -->
        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
            <div class="mb-4 flex items-center justify-between border-b pb-2">
                <h3 class="text-lg font-medium text-gray-900">7. Hijos / cónyuge (derechohabientes)</h3>
                <button type="button" @click="agregarDH" class="rounded bg-gray-200 px-3 py-1 text-sm font-medium hover:bg-gray-300">+ Agregar</button>
            </div>
            <div v-if="form.derechohabientes.length === 0" class="text-sm text-gray-500">Sin derechohabientes registrados.</div>
            <div v-for="(d, i) in form.derechohabientes" :key="i" class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-5">
                <select v-model="d.tipo" :class="inp"><option value="hijo">Hijo</option><option value="conyuge">Cónyuge</option><option value="concubino">Concubino</option></select>
                <input v-model="d.nombres" placeholder="Nombres" :class="[inp, 'md:col-span-2']" />
                <input v-model="d.fecha_nacimiento" type="date" :class="inp" />
                <div class="flex items-center gap-2">
                    <input v-model="d.estudia" type="checkbox" :id="'est'+i" class="rounded" /><label :for="'est'+i" class="text-sm">Estudia</label>
                    <button type="button" @click="quitarDH(i)" class="ml-auto text-sm text-red-600">Quitar</button>
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <button type="button" @click="emit('cancelar')" class="rounded-md bg-gray-200 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                Cancelar
            </button>
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ modo === 'edit' ? 'Actualizar empleado' : 'Registrar empleado' }}
            </button>
        </div>
    </form>
</template>
