<?php

namespace App\Http\Controllers;

use App\Models\Adelanto;
use App\Models\Contract;
use App\Models\Empresa;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Adelantos y préstamos al trabajador. Se descuentan automáticamente del neto
 * en la planilla del mes correspondiente:
 *  - Adelanto: un solo descuento en el mes indicado.
 *  - Préstamo: se divide en N cuotas mensuales consecutivas.
 */
class AdelantoController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = $request->input('empresa_id');

        $registros = collect();
        if ($empresaId) {
            $registros = Adelanto::with('employee:id,nombres,apellido_paterno,apellido_materno,numero_documento')
                ->where('empresa_id', $empresaId)
                ->orderByDesc('anio')->orderByDesc('mes')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'trabajador' => $a->employee?->nombre_completo,
                    'dni' => $a->employee?->numero_documento,
                    'tipo' => $a->tipo,
                    'anio' => $a->anio,
                    'mes' => $a->mes,
                    'quincena' => $a->quincena,
                    'monto' => (float) $a->monto,
                    'concepto' => $a->concepto,
                    'grupo' => $a->grupo,
                    'cuota' => $a->cuota_num ? "{$a->cuota_num}/{$a->cuotas_total}" : null,
                ]);
        }

        return Inertia::render('Adelantos/Index', [
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social']),
            'empleados' => $empresaId
                ? Employee::where('empresa_id', $empresaId)->where('activo', true)
                    ->orderBy('apellido_paterno')->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'numero_documento'])
                    ->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre_completo.' ('.$e->numero_documento.')'])
                : [],
            'registros' => $registros,
            'filtros' => ['empresa_id' => $empresaId ? (int) $empresaId : null],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'employee_id' => ['required', Rule::exists('employees', 'id')
                ->where(fn ($q) => $q->where('empresa_id', $request->input('empresa_id')))],
            'tipo' => ['required', 'in:adelanto,prestamo'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'cuotas' => ['required_if:tipo,prestamo', 'nullable', 'integer', 'min:1', 'max:60'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ]);

        // No se puede descontar mas de lo que el trabajador gana: dejaria el neto
        // en cero y la deuda quedaria colgada. Se compara la cuota mensual (no el
        // total del prestamo) contra su sueldo, sumando lo ya registrado ese mes.
        $contrato = Contract::where('employee_id', $data['employee_id'])->orderByDesc('id')->first();
        $sueldo = (float) ($contrato->sueldo_basico ?? 0);
        $cuotaMensual = $data['tipo'] === 'prestamo'
            ? round((float) $data['monto'] / max(1, (int) $data['cuotas']), 2)
            : (float) $data['monto'];
        $yaRegistrado = (float) Adelanto::where('employee_id', $data['employee_id'])
            ->where('anio', $data['anio'])->where('mes', $data['mes'])->sum('monto');

        if ($sueldo > 0 && ($cuotaMensual + $yaRegistrado) > $sueldo) {
            return back()->withInput()->with('error', sprintf(
                'El descuento de %s (S/ %s) supera su sueldo de S/ %s. Ya tiene S/ %s registrado ese mes.',
                $data['tipo'] === 'prestamo' ? 'la cuota mensual' : 'este adelanto',
                number_format($cuotaMensual, 2), number_format($sueldo, 2), number_format($yaRegistrado, 2)
            ));
        }

        $base = [
            'empresa_id' => $data['empresa_id'],
            'employee_id' => $data['employee_id'],
            'tipo' => $data['tipo'],
            'concepto' => $data['concepto'] ?? null,
            'quincena' => $data['quincena'] ?? null,
            'registrado_por' => $request->user()?->id,
        ];

        if ($data['tipo'] === 'adelanto') {
            Adelanto::create([...$base, 'anio' => $data['anio'], 'mes' => $data['mes'], 'monto' => $data['monto']]);

            return back()->with('success', 'Adelanto registrado. Se descontará en la planilla del periodo indicado.');
        }

        // Préstamo: dividir en N cuotas mensuales consecutivas.
        $n = (int) $data['cuotas'];
        $cuota = round($data['monto'] / $n, 2);
        $grupo = (string) Str::uuid();
        $anio = (int) $data['anio'];
        $mes = (int) $data['mes'];

        DB::transaction(function () use ($n, $cuota, $data, $base, $grupo, &$anio, &$mes) {
            $acumulado = 0;
            for ($i = 1; $i <= $n; $i++) {
                // La última cuota ajusta el redondeo para cuadrar el total exacto.
                $montoCuota = $i === $n ? round($data['monto'] - $acumulado, 2) : $cuota;
                $acumulado += $montoCuota;

                Adelanto::create([
                    ...$base,
                    'anio' => $anio, 'mes' => $mes, 'monto' => $montoCuota,
                    'grupo' => $grupo, 'cuota_num' => $i, 'cuotas_total' => $n,
                ]);

                $mes++;
                if ($mes > 12) { $mes = 1; $anio++; }
            }
        });

        return back()->with('success', "Préstamo registrado en {$n} cuotas. Se descontarán automáticamente cada mes.");
    }

    public function destroy(Adelanto $adelanto)
    {
        if ($this->periodoCerrado($adelanto)) {
            return back()->with('error', 'No se puede eliminar: ese descuento ya se aplicó en una planilla cerrada.');
        }

        $adelanto->delete();

        return back()->with('success', 'Registro eliminado.');
    }

    public function destroyGrupo(string $grupo)
    {
        $cuotas = Adelanto::where('grupo', $grupo)->get();

        // Las cuotas ya cobradas no se tocan: solo se cancelan las pendientes,
        // para no dejar descuadre entre lo descontado y lo registrado.
        $cerradas = $cuotas->filter(fn ($c) => $this->periodoCerrado($c));

        Adelanto::where('grupo', $grupo)->whereNotIn('id', $cerradas->pluck('id'))->delete();

        if ($cerradas->isNotEmpty()) {
            return back()->with('success', sprintf(
                'Se cancelaron las cuotas pendientes. Se conservaron %d cuota(s) ya cobradas en planillas cerradas.',
                $cerradas->count()
            ));
        }

        return back()->with('success', 'Préstamo completo eliminado (todas sus cuotas).');
    }

    /** ¿La planilla del periodo de esta cuota ya está cerrada? */
    private function periodoCerrado(Adelanto $adelanto): bool
    {
        return Payroll::where('empresa_id', $adelanto->empresa_id)
            ->where('estado', 'cerrado')
            ->whereHas('periodo', fn ($q) => $q->where('anio', $adelanto->anio)->where('mes', $adelanto->mes))
            ->exists();
    }
}
