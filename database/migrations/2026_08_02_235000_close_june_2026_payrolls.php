<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cierra operativamente junio de 2026 en producción.
     *
     * Planilla y Honorarios comparten payroll/periodo, por lo que este cierre
     * cubre ambos módulos sin recalcular ni modificar importes o detalles.
     */
    public function up(): void
    {
        $periodoIds = DB::table('periodos')
            ->where('anio', 2026)
            ->where('mes', 6)
            ->pluck('id');

        if ($periodoIds->isEmpty()) {
            return;
        }

        $periodosConPlanilla = DB::table('payrolls')
            ->whereIn('periodo_id', $periodoIds)
            ->pluck('periodo_id')
            ->unique()
            ->values();

        if ($periodosConPlanilla->isEmpty()) {
            return;
        }

        $ahora = now();

        DB::table('payrolls')
            ->whereIn('periodo_id', $periodosConPlanilla)
            ->where('estado', '!=', 'cerrado')
            ->update([
                'estado' => 'cerrado',
                'cerrado_at' => $ahora,
                'updated_at' => $ahora,
            ]);

        DB::table('periodos')
            ->whereIn('id', $periodosConPlanilla)
            ->where('estado', '!=', 'cerrado')
            ->update([
                'estado' => 'cerrado',
                'updated_at' => $ahora,
            ]);
    }

    /**
     * Un cierre histórico no se revierte automáticamente para evitar reabrir
     * planillas u honorarios que ya pudieron ser procesados en producción.
     */
    public function down(): void
    {
        // Sin reversión intencional.
    }
};
