<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaExport;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Empresa;
use App\Models\Employee;
use App\Models\Sede;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id') ?: null;
        $sedeId = $request->input('sede_id') ?: null;

        $empleados = Employee::with(['empresa:id,razon_social,nombre_comercial', 'sede:id,nombre', 'contratoVigente.turno:id,nombre,hora_entrada,hora_salida', 'contratoVigente.area:id,nombre', 'contratoVigente.cargo:id,nombre', 'derechohabientes', 'documentos'])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->orderBy('apellido_paterno')
            ->get()
            ->map(function ($e) {
                $c = $e->contratoVigente->first();
                // Alerta de vencimiento: solo contratos a plazo fijo (con fecha_cese).
                $diasVencimiento = null;
                if ($c?->fecha_cese) {
                    $diasVencimiento = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($c->fecha_cese)->startOfDay(), false);
                }
                return [
                    'id' => $e->id,
                    'nombre_completo' => $e->nombre_completo,
                    'numero_documento' => $e->numero_documento,
                    'empresa' => $e->empresa?->nombre_comercial ?? $e->empresa?->razon_social,
                    'sede' => $e->sede?->nombre,
                    'sueldo_basico' => $c?->sueldo_basico,
                    'sistema_pensiones' => $c?->sistema_pensiones,
                    'area' => $c?->area?->nombre,
                    'cargo' => $c?->cargo?->nombre,
                    'turno' => $c?->turno?->nombre,
                    'turno_horario' => $c?->turno ? substr($c->turno->hora_entrada, 0, 5).'–'.substr($c->turno->hora_salida, 0, 5) : null,
                    'fecha_cese' => $c?->fecha_cese?->toDateString(),
                    'dias_vencimiento' => $diasVencimiento,
                    'activo' => $e->activo,
                    // Datos para el modal de edición
                    'empleado' => $e->only([
                        'id', 'empresa_id', 'apellido_paterno', 'apellido_materno', 'nombres', 'tipo_documento',
                        'numero_documento', 'ruc', 'fecha_nacimiento', 'genero', 'estado_civil', 'lugar_nacimiento',
                        'profesion', 'telefono', 'correo', 'sede_id', 'direccion', 'distrito', 'provincia',
                        'departamento', 'tipo_vivienda', 'nivel_educativo', 'banco', 'cuenta_corriente',
                        'cuenta_ahorros', 'cci', 'codigo_biometrico',
                        'emergencia_nombre', 'emergencia_telefono', 'emergencia_parentesco',
                    ]),
                    'contrato' => $c,
                    'derechohabientes' => $e->derechohabientes,
                    'documentos' => $e->documentos->map(fn ($d) => [
                        'id' => $d->id,
                        'tipo' => $d->tipo,
                        'nombre_original' => $d->nombre_original,
                        'fecha' => $d->created_at?->format('d/m/Y H:i'),
                    ]),
                ];
            });

        return Inertia::render('Empleados/Index', array_merge(
            [
                'empleados' => $empleados,
                'filtros' => ['empresa_id' => $empresaId, 'sede_id' => $sedeId],
            ],
            $this->datosFormulario()
        ));
    }

    /** Exporta la lista de empleados a Excel (.xlsx), respetando los filtros. */
    public function export(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $empresaId = $request->input('empresa_id');
        $area = $request->input('area');
        $cargo = $request->input('cargo');
        $estado = $request->input('estado'); // '', 'activo', 'cesado'

        $empleados = Employee::with(['empresa', 'contratoVigente.area', 'contratoVigente.cargo', 'contratoVigente.turno'])
            ->when($empresaId, fn ($qq) => $qq->where('empresa_id', $empresaId))
            ->when($estado === 'activo', fn ($qq) => $qq->where('activo', true))
            ->when($estado === 'cesado', fn ($qq) => $qq->where('activo', false))
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                ->where('numero_documento', 'like', "%$q%")
                ->orWhereRaw("CONCAT(apellido_paterno,' ',COALESCE(apellido_materno,''),' ',nombres) like ?", ["%$q%"])))
            ->orderBy('apellido_paterno')->get();

        $rows = [];
        foreach ($empleados as $e) {
            $c = $e->contratoVigente->first();
            $areaN = $c?->area?->nombre;
            $cargoN = $c?->cargo?->nombre;
            if ($area && $areaN !== $area) {
                continue;
            }
            if ($cargo && $cargoN !== $cargo) {
                continue;
            }
            $rows[] = [
                $e->empresa?->nombre_comercial ?: $e->empresa?->razon_social,
                $e->numero_documento,
                $e->nombre_completo,
                $areaN ?? '',
                $cargoN ?? '',
                $c?->turno?->nombre ?? '',
                $c?->sistema_pensiones ?? '',
                $c?->sueldo_basico ? number_format((float) $c->sueldo_basico, 2, '.', '') : '',
                $e->activo ? 'Activo' : 'Cesado',
            ];
        }

        $headings = ['Empresa', 'DNI', 'Apellidos y nombres', 'Área', 'Cargo', 'Turno', 'Pensión', 'Sueldo básico', 'Estado'];

        return Excel::download(new PlantillaExport($headings, $rows), 'empleados_'.now()->format('Ymd_His').'.xlsx');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data) {
            $empleado = Employee::create($data['empleado']);
            $empleado->contratos()->create($data['contrato']);

            foreach ($data['derechohabientes'] ?? [] as $d) {
                $empleado->derechohabientes()->create($d);
            }
        });

        return redirect()->route('empleados.index')->with('success', 'Empleado registrado.');
    }

    public function update(Request $request, Employee $empleado)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data, $empleado) {
            $empleado->update($data['empleado']);

            $contrato = $empleado->contratos()->latest('fecha_ingreso')->first();
            $contrato
                ? $contrato->update($data['contrato'])
                : $empleado->contratos()->create($data['contrato']);

            $empleado->derechohabientes()->delete();
            foreach ($data['derechohabientes'] ?? [] as $d) {
                $empleado->derechohabientes()->create($d);
            }
        });

        return redirect()->route('empleados.index')->with('success', 'Empleado actualizado.');
    }

    public function destroy(Employee $empleado)
    {
        $empleado->delete();

        return back()->with('success', 'Empleado eliminado.');
    }

    /** Catálogos para filtros y formulario (todas las empresas; se filtran en el cliente). */
    private function datosFormulario(): array
    {
        return [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
            'areas' => Area::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
            'cargos' => Cargo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria']),
            'turnos' => Turno::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tiposContrato' => \App\Support\TiposContrato::opciones(),
        ];
    }

    private function validar(Request $request): array
    {
        $empleadoId = $request->route('empleado')?->id;

        $request->validate([
            // Empleado
            'empresa_id' => ['required', 'exists:empresas,id'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'nombres' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'string', 'max:20'],
            'numero_documento' => ['required', 'string', 'max:20', Rule::unique('employees', 'numero_documento')->ignore($empleadoId)],
            'ruc' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', 'in:M,F'],
            'estado_civil' => ['nullable', 'string', 'max:50'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:255'],
            'profesion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'tipo_vivienda' => ['nullable', 'string', 'max:50'],
            'nivel_educativo' => ['nullable', 'string', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'cuenta_corriente' => ['nullable', 'string', 'max:50'],
            'cuenta_ahorros' => ['nullable', 'string', 'max:50'],
            'cci' => ['nullable', 'string', 'max:50'],
            'codigo_biometrico' => ['nullable', 'string', 'max:50'],
            'emergencia_nombre' => ['nullable', 'string', 'max:255'],
            'emergencia_telefono' => ['nullable', 'string', 'max:50'],
            'emergencia_parentesco' => ['nullable', 'string', 'max:50'],
            // Contrato
            'tipo_contrato' => ['nullable', Rule::in(array_keys(\App\Support\TiposContrato::all()))],
            'categoria_ocupacional' => ['required', 'in:empleado,obrero'],
            'fecha_ingreso' => ['required', 'date'],
            'fecha_cese' => ['nullable', 'date', 'after:fecha_ingreso'],
            'sueldo_basico' => ['required', 'numeric', 'min:0'],
            'percibe_asignacion_familiar' => ['boolean'],
            'movilidad' => ['nullable', 'numeric', 'min:0'],
            'otros' => ['nullable', 'numeric', 'min:0'],
            'sistema_pensiones' => ['nullable', 'in:AFP,ONP'],
            'afp' => ['nullable', 'string', 'max:50', 'required_if:sistema_pensiones,AFP'],
            'tipo_afp' => ['nullable', 'in:mixta,sueldo', 'required_if:sistema_pensiones,AFP'],
            'codigo_afp' => ['nullable', 'string', 'max:50'],
            'fecha_afiliacion_pension' => ['nullable', 'date'],
            'aporta_sctr' => ['boolean'],
            'aporta_senati' => ['boolean'],
            'tiene_vida_ley' => ['boolean'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'cargo_id' => ['nullable', 'exists:cargos,id'],
            'turno_id' => ['nullable', 'exists:turnos,id'],
            // Derechohabientes
            'derechohabientes' => ['array'],
            'derechohabientes.*.tipo' => ['required_with:derechohabientes', 'in:hijo,conyuge,concubino'],
            'derechohabientes.*.nombres' => ['required_with:derechohabientes', 'string', 'max:255'],
            'derechohabientes.*.fecha_nacimiento' => ['nullable', 'date'],
            'derechohabientes.*.estudia' => ['boolean'],
        ]);

        return [
            'empleado' => $request->only([
                'empresa_id', 'apellido_paterno', 'apellido_materno', 'nombres', 'tipo_documento',
                'numero_documento', 'ruc', 'fecha_nacimiento', 'genero', 'estado_civil', 'lugar_nacimiento',
                'profesion', 'telefono', 'correo', 'sede_id', 'direccion', 'distrito', 'provincia',
                'departamento', 'tipo_vivienda', 'nivel_educativo', 'banco', 'cuenta_corriente',
                'cuenta_ahorros', 'cci', 'codigo_biometrico',
                'emergencia_nombre', 'emergencia_telefono', 'emergencia_parentesco',
            ]),
            'contrato' => $request->only([
                'tipo_contrato', 'categoria_ocupacional', 'fecha_ingreso', 'fecha_cese', 'sueldo_basico',
                'percibe_asignacion_familiar', 'movilidad', 'otros', 'sistema_pensiones', 'afp',
                'tipo_afp', 'codigo_afp', 'fecha_afiliacion_pension', 'aporta_sctr', 'aporta_senati',
                'tiene_vida_ley', 'area_id', 'cargo_id', 'turno_id',
            ]),
            'derechohabientes' => $request->input('derechohabientes', []),
        ];
    }
}
