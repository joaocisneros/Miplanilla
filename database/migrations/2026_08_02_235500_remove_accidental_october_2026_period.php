<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('periodos')
            ->where('anio', 2026)
            ->where('mes', 10)
            ->where('quincena', 1)
            ->where('fecha_inicio', '2026-10-01')
            ->where('fecha_fin', '2026-10-15')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('payrolls')
                    ->whereColumn('payrolls.periodo_id', 'periodos.id');
            })
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('periodos')->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void
    {
        // No se recrea un período accidental.
    }
};
