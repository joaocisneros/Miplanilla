<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Valores iniciales de los maestros (vigentes 2026).
 * NOTA: lo marcado confirmado=false debe ratificarlo el contador.
 * A partir de aquí, todo se administra desde el panel ADMIN.
 */
class MaestrosSeeder extends Seeder
{
    public function run(): void
    {
        // --- Parámetros del periodo 2026 ---
        DB::table('parametros_periodo')->updateOrInsert(
            ['anio' => 2026],
            [
                'uit' => null, // por confirmar con contador
                'rmv' => 1130.00,
                'asignacion_familiar' => 113.00,
                'dias_base' => 30,
                'vigente_desde' => '2026-01-01',
                'confirmado' => false,
                'fuente' => 'RMV vigente 2025 (S/1,130); UIT 2026 por confirmar',
                'updated_at' => now(), 'created_at' => now(),
            ]
        );

        // --- Tasas AFP/ONP (referencia SBS; confirmar mes de devengue) ---
        $afps = [
            // afp, tipo, aporte, com_flujo, com_saldo, prima, rem_max
            ['INTEGRA',   'mixta',  0.10, 0.0056, 0.0120, 0.0174, 9665.33],
            ['INTEGRA',   'sueldo', 0.10, 0.0155, 0.0000, 0.0174, null],
            ['PRIMA',     'mixta',  0.10, 0.0018, 0.0125, 0.0174, 9665.33],
            ['PRIMA',     'sueldo', 0.10, 0.0160, 0.0000, 0.0174, null],
            ['PROFUTURO', 'mixta',  0.10, 0.0067, 0.0120, 0.0174, 9665.33],
            ['PROFUTURO', 'sueldo', 0.10, 0.0169, 0.0000, 0.0174, null],
            ['HABITAT',   'mixta',  0.10, 0.0038, 0.0125, 0.0174, 9665.33],
            ['HABITAT',   'sueldo', 0.10, 0.0147, 0.0000, 0.0174, null],
            ['ONP',       'onp',    0.13, 0.0000, 0.0000, 0.0000, null],
        ];
        foreach ($afps as [$afp, $tipo, $aporte, $cf, $cs, $prima, $rmax]) {
            DB::table('tasas_afp')->updateOrInsert(
                ['afp' => $afp, 'tipo' => $tipo, 'vigente_desde' => '2026-01-01'],
                [
                    'aporte_obligatorio' => $aporte,
                    'comision_flujo' => $cf,
                    'comision_saldo' => $cs,
                    'prima_seguro' => $prima,
                    'rem_max_asegurable' => $rmax,
                    'confirmado' => false,
                    'fuente' => 'SBS (referencia Excel) - confirmar devengue',
                    'updated_at' => now(), 'created_at' => now(),
                ]
            );
        }

        // --- Tramos de renta de 5ta categoría ---
        $tramos = [
            [1, 0,  5,  0.08],
            [2, 5,  20, 0.14],
            [3, 20, 35, 0.17],
            [4, 35, 45, 0.20],
            [5, 45, null, 0.30],
        ];
        foreach ($tramos as [$orden, $desde, $hasta, $tasa]) {
            DB::table('tramos_renta_5ta')->updateOrInsert(
                ['orden' => $orden, 'vigente_desde' => '2026-01-01'],
                [
                    'desde_uit' => $desde,
                    'hasta_uit' => $hasta,
                    'tasa' => $tasa,
                    'updated_at' => now(), 'created_at' => now(),
                ]
            );
        }

        // --- Tasa EsSalud (referencia; SCTR y Vida Ley se cargan por póliza) ---
        // EsSalud 9% no tiene tabla propia aún; se usará una constante en parametros
        // o tabla tasas_aportes en una iteración posterior.
    }
}
