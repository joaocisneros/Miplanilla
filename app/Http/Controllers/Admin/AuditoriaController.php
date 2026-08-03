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
        'Periodo' => 'Período de planilla',
        'Payroll' => 'Planilla/Honorarios',
        'User' => 'Sesión de usuario',
    ];

    private const EVENTOS = [
        'created' => 'Creó',
        'updated' => 'Editó',
        'deleted' => 'Eliminó',
        'restored' => 'Restauró',
        'login' => 'Inició sesión',
        'logout' => 'Cerró sesión',
    ];

    public function index(Request $request): Response
    {
        $evento = $request->input('evento');

        $audits = Audit::with(['user:id,name', 'user.empleado:id,user_id,numero_documento'])
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
        if ($corto === 'Periodo' && in_array($a->event, ['created', 'deleted'], true)) {
            $datos = $a->event === 'created' ? $nuevos : $viejos;
            $campos[] = [
                'campo' => 'Período',
                'antes' => $a->event === 'created' ? '—' : $this->periodoLegible($datos),
                'despues' => $a->event === 'created' ? $this->periodoLegible($datos) : '—',
            ];
        }
        foreach ($campos === [] ? array_slice($nuevos, 0, 6, true) : [] as $campo => $valor) {
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
            'dni' => $a->user?->empleado?->numero_documento ?? 'No vinculado',
            'evento' => self::EVENTOS[$a->event] ?? $a->event,
            'evento_raw' => $a->event,
            'modelo' => self::MODELOS[$corto] ?? $corto,
            'registro_id' => $a->auditable_id,
            'cambios' => $campos,
            'ip' => $a->ip_address ?: 'No disponible',
            'navegador' => $this->navegadorLegible((string) $a->user_agent),
        ];
    }

    private function navegadorLegible(string $agente): string
    {
        if ($agente === '') {
            return 'No disponible';
        }

        $navegador = str_contains($agente, 'Edg/') ? 'Microsoft Edge'
            : (str_contains($agente, 'OPR/') ? 'Opera'
            : (str_contains($agente, 'Firefox/') ? 'Firefox'
            : (str_contains($agente, 'Chrome/') ? 'Google Chrome'
            : (str_contains($agente, 'Safari/') ? 'Safari' : 'Otro navegador'))));
        $sistema = str_contains($agente, 'Windows') ? 'Windows'
            : (str_contains($agente, 'Android') ? 'Android'
            : (str_contains($agente, 'iPhone') || str_contains($agente, 'iPad') ? 'iOS'
            : (str_contains($agente, 'Mac OS') ? 'macOS'
            : (str_contains($agente, 'Linux') ? 'Linux' : 'Sistema desconocido'))));

        return "{$navegador} · {$sistema}";
    }

    /** Recorta valores largos para mostrarlos en la tabla. */
    private function periodoLegible(array $datos): string
    {
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $mes = (int) ($datos['mes'] ?? 0);
        $quincena = $datos['quincena'] ?? null;
        $tipo = $quincena ? ((int) $quincena === 1 ? '1ra quincena' : '2da quincena') : 'Mes completo';
        $inicio = ! empty($datos['fecha_inicio']) ? date('d/m/Y', strtotime($datos['fecha_inicio'])) : 'sin fecha';
        $fin = ! empty($datos['fecha_fin']) ? date('d/m/Y', strtotime($datos['fecha_fin'])) : 'sin fecha';

        return "{$tipo} de ".($meses[$mes] ?? "mes {$mes}").' '.($datos['anio'] ?? '')." ({$inicio} al {$fin})";
    }

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
