<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo base de conceptos con su naturaleza remunerativa.
 * (Ver ESPECIFICACION_PLANILLA.md §9.2). Editable luego desde el panel ADMIN.
 * Pendiente confirmar con contador: asignación familiar en descuento, incentivos regulares.
 */
class ConceptosSeeder extends Seeder
{
    public function run(): void
    {
        // codigo, nombre, tipo, remun, afp_onp, essalud, sctr, 5ta, desc_inasist, regularidad
        $conceptos = [
            ['SUELDO_BASICO', 'Sueldo básico', 'ingreso', 1,1,1,1,1, 1,0],
            ['ASIG_FAMILIAR', 'Asignación familiar', 'ingreso', 1,1,1,1,1, 0,0], // descuento por confirmar
            ['HORAS_EXTRAS', 'Horas extras', 'ingreso', 1,1,1,1,1, 0,0],
            ['TRABAJO_SABADO', 'Trabajo en sábado', 'ingreso', 1,1,1,1,1, 0,0],
            ['TRABAJO_DOM_FER', 'Trabajo domingo/feriado', 'ingreso', 1,1,1,1,1, 0,0],
            ['INCENTIVO_PROD', 'Incentivo por producción', 'ingreso', 1,1,1,1,1, 0,1], // evalúa regularidad
            ['MOVILIDAD', 'Movilidad (condición de trabajo)', 'ingreso', 0,0,0,0,0, 0,0],
            ['SUBSIDIO', 'Subsidio (DM/maternidad)', 'ingreso', 0,0,0,0,0, 0,0],
            ['GRATIFICACION', 'Gratificación', 'ingreso', 1,0,0,0,1, 0,0],
            // Descuentos al trabajador
            ['DESC_TARDANZA', 'Descuento por tardanza', 'descuento', 0,0,0,0,0, 0,0],
            ['DESC_FALTA', 'Descuento por falta', 'descuento', 0,0,0,0,0, 0,0],
            ['APORTE_AFP', 'Aporte/retención AFP', 'descuento', 0,0,0,0,0, 0,0],
            ['APORTE_ONP', 'Aporte/retención ONP', 'descuento', 0,0,0,0,0, 0,0],
            ['RENTA_5TA', 'Retención renta 5ta', 'descuento', 0,0,0,0,0, 0,0],
            ['ADELANTO', 'Adelanto / préstamo', 'descuento', 0,0,0,0,0, 0,0],
            // Aportes del empleador
            ['ESSALUD', 'EsSalud (9%)', 'aporte_empleador', 0,0,0,0,0, 0,0],
            ['SCTR_PENSION', 'SCTR Pensión', 'aporte_empleador', 0,0,0,0,0, 0,0],
            ['SCTR_SALUD', 'SCTR Salud', 'aporte_empleador', 0,0,0,0,0, 0,0],
            ['VIDA_LEY', 'Seguro Vida Ley (DL 688)', 'aporte_empleador', 0,0,0,0,0, 0,0],
            ['SENATI', 'Senati', 'aporte_empleador', 0,0,0,0,0, 0,0],
        ];

        foreach ($conceptos as $c) {
            DB::table('conceptos')->updateOrInsert(
                ['codigo' => $c[0]],
                [
                    'nombre' => $c[1],
                    'tipo' => $c[2],
                    'es_remunerativo' => $c[3],
                    'afecto_afp_onp' => $c[4],
                    'afecto_essalud' => $c[5],
                    'afecto_sctr' => $c[6],
                    'afecto_5ta' => $c[7],
                    'afecta_descuento_inasistencia' => $c[8],
                    'evalua_regularidad' => $c[9],
                    'activo' => 1,
                    'updated_at' => now(), 'created_at' => now(),
                ]
            );
        }
    }
}
