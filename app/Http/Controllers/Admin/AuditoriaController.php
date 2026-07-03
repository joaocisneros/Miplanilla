<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

class AuditoriaController extends Controller
{
    /** Nombres legibles de cada modelo auditado. */
    private const MODELOS = [
        'Employee' => 'Empleado',
        'Empresa' => 'Empresa',
        'Sede' => 'Sede',
        'Contract' => 'Contrato',
        'Attendance' => 'Asistencia',
        'Incidencia' => 'Incidencia',
        'ParametroPeriodo' => 'Parámetro',
        'PolizaSctr' => 'Póliza SCTR',
        'PolizaVidaLey' => 'Póliza Vida Ley',
        'TasaAfp' => 'Tasa AFP',
    ];

    private const EVENTOS = [
        'created' => 'Creó',
        'updated' => 'Editó',
        'deleted' => 'Eliminó',
        'restored' => 'Restauró',
    ];

    public function index(Request $request): Response
    {
        $evento = $request->input('evento');

        $audits = Audit::with('user:id,name')
            ->when($evento, fn ($q) => $q->where('event', $evento))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Audit $a) => $this->formatear($a));

        return Inertia::render('Admin/Auditoria/Index', [
            'audits' => $audits,
            'filtros' => ['evento' => $evento],
        ]);
    }

    private function formatear(Audit $a): array
    {
        $corto = class_basename($a->auditable_type);
        $nuevos = (array) $a->new_values;
        $viejos = (array) $a->old_values;

        // Lista de cambios legibles (máx. 6 campos para no saturar).
        $campos = [];
        foreach (array_slice($nuevos, 0, 6, true) as $campo => $valor) {
            if (in_array($campo, ['updated_at', 'created_at', 'password', 'remember_token'], true)) {
                continue;
            }
            $campos[] = [
                'campo' => ucfirst(str_replace('_', ' ', $campo)),
                'antes' => $this->corto($viejos[$campo] ?? null),
                'despues' => $this->corto($valor),
            ];
        }

        return [
            'id' => $a->id,
            'fecha' => $a->created_at?->format('d/m/Y H:i'),
            'usuario' => $a->user?->name ?? 'Sistema',
            'evento' => self::EVENTOS[$a->event] ?? $a->event,
            'evento_raw' => $a->event,
            'modelo' => self::MODELOS[$corto] ?? $corto,
            'registro_id' => $a->auditable_id,
            'cambios' => $campos,
        ];
    }

    /** Recorta valores largos para mostrarlos en la tabla. */
    private function corto($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if (is_array($valor)) {
            $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
        }
        $valor = (string) $valor;

        return mb_strlen($valor) > 40 ? mb_substr($valor, 0, 40).'…' : $valor;
    }
}
