<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->session()->get('empresa_id');
        $sedeId = $request->session()->get('sede_id');

        $empleados = Employee::with(['sede:id,nombre', 'contratoVigente'])
            ->where('empresa_id', $empresaId)
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->orderBy('apellido_paterno')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'nombre_completo' => $e->nombre_completo,
                'numero_documento' => $e->numero_documento,
                'sede' => $e->sede?->nombre,
                'sueldo_basico' => $e->contratoVigente->first()?->sueldo_basico,
                'sistema_pensiones' => $e->contratoVigente->first()?->sistema_pensiones,
                'activo' => $e->activo,
            ]);

        return Inertia::render('Empleados/Index', [
            'empleados' => $empleados,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Empleados/Create', $this->datosFormulario($request));
    }

    public function store(Request $request)
    {
        $empresaId = $request->session()->get('empresa_id');
        abort_if(! $empresaId, 422, 'Selecciona una empresa activa.');

        $data = $this->validar($request);

        DB::transaction(function () use ($data, $empresaId) {
            $empleado = Employee::create(array_merge($data['empleado'], [
                'empresa_id' => $empresaId,
            ]));

            $empleado->contratos()->create($data['contrato']);

            foreach ($data['derechohabientes'] ?? [] as $d) {
                $empleado->derechohabientes()->create($d);
            }
        });

        return redirect()->route('empleados.index')->with('success', 'Empleado registrado.');
    }

    public function edit(Request $request, Employee $empleado): Response
    {
        $this->autorizarEmpresa($request, $empleado);
        $empleado->load(['contratoVigente', 'derechohabientes']);

        return Inertia::render('Empleados/Edit', array_merge($this->datosFormulario($request), [
            'empleado' => $empleado,
            'contrato' => $empleado->contratoVigente->first(),
            'derechohabientes' => $empleado->derechohabientes,
        ]));
    }

    public function update(Request $request, Employee $empleado)
    {
        $this->autorizarEmpresa($request, $empleado);
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

    public function destroy(Request $request, Employee $empleado)
    {
        $this->autorizarEmpresa($request, $empleado);
        $empleado->delete();

        return back()->with('success', 'Empleado eliminado.');
    }

    private function datosFormulario(Request $request): array
    {
        $empresaId = $request->session()->get('empresa_id');

        return [
            'sedes' => \App\Models\Sede::where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cargos' => Cargo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria']),
            'turnos' => Turno::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    private function validar(Request $request): array
    {
        $empleadoId = $request->route('empleado')?->id;

        $request->validate([
            // Empleado
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'nombres' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'string', 'max:20'],
            'numero_documento' => ['required', 'string', 'max:20', Rule::unique('employees', 'numero_documento')->ignore($empleadoId)],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', 'in:M,F'],
            'estado_civil' => ['nullable', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'banco' => ['nullable', 'string', 'max:100'],
            'cuenta_ahorros' => ['nullable', 'string', 'max:50'],
            'cci' => ['nullable', 'string', 'max:50'],
            'codigo_biometrico' => ['nullable', 'string', 'max:50'],
            // Contrato
            'tipo_contrato' => ['nullable', 'string', 'max:100'],
            'categoria_ocupacional' => ['required', 'in:empleado,obrero'],
            'fecha_ingreso' => ['required', 'date'],
            'sueldo_basico' => ['required', 'numeric', 'min:0'],
            'percibe_asignacion_familiar' => ['boolean'],
            'movilidad' => ['nullable', 'numeric', 'min:0'],
            'sistema_pensiones' => ['nullable', 'in:AFP,ONP'],
            'afp' => ['nullable', 'string', 'max:50', 'required_if:sistema_pensiones,AFP'],
            'tipo_afp' => ['nullable', 'in:mixta,sueldo', 'required_if:sistema_pensiones,AFP'],
            'aporta_sctr' => ['boolean'],
            'aporta_senati' => ['boolean'],
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
                'apellido_paterno', 'apellido_materno', 'nombres', 'tipo_documento',
                'numero_documento', 'fecha_nacimiento', 'genero', 'estado_civil',
                'telefono', 'correo', 'sede_id', 'banco', 'cuenta_ahorros', 'cci', 'codigo_biometrico',
            ]),
            'contrato' => $request->only([
                'tipo_contrato', 'categoria_ocupacional', 'fecha_ingreso', 'sueldo_basico',
                'percibe_asignacion_familiar', 'movilidad', 'sistema_pensiones', 'afp',
                'tipo_afp', 'aporta_sctr', 'aporta_senati', 'area_id', 'cargo_id', 'turno_id',
            ]),
            'derechohabientes' => $request->input('derechohabientes', []),
        ];
    }

    private function autorizarEmpresa(Request $request, Employee $empleado): void
    {
        abort_if((int) $empleado->empresa_id !== (int) $request->session()->get('empresa_id'), 403);
    }
}
