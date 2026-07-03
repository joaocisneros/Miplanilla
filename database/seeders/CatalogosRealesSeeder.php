<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Empresa;
use App\Models\Turno;
use Illuminate\Database\Seeder;

/**
 * Catálogos reales para ACS (construcción / maquinaria):
 *  - Áreas: por empresa (marcan riesgo para SCTR).
 *  - Cargos y Turnos: globales (compartidos por las 3 empresas).
 */
class CatalogosRealesSeeder extends Seeder
{
    public function run(): void
    {
        // Áreas por empresa (con bandera de riesgo SCTR)
        $areas = [
            ['Obra / Producción', true],
            ['Mantenimiento de maquinaria', true],
            ['Operaciones', true],
            ['Administración', false],
            ['Logística', false],
        ];
        foreach (Empresa::all() as $empresa) {
            foreach ($areas as [$nombre, $riesgo]) {
                Area::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'nombre' => $nombre],
                    ['es_riesgo' => $riesgo, 'activo' => true]
                );
            }
        }

        // Cargos (globales) — categorías de construcción civil + administrativos
        $cargos = [
            ['Maestro de obra', 'MAESTRO'],
            ['Operario', 'OPERARIO'],
            ['Oficial', 'OFICIAL'],
            ['Ayudante / Peón', 'AYUDANTE'],
            ['Operador de maquinaria pesada', 'OPERARIO'],
            ['Supervisor de obra', 'EMPLEADO'],
            ['Asistente administrativo', 'EMPLEADO'],
            ['Contador', 'EMPLEADO'],
            ['Almacenero', 'EMPLEADO'],
        ];
        foreach ($cargos as [$nombre, $cat]) {
            Cargo::firstOrCreate(['nombre' => $nombre], ['categoria' => $cat, 'activo' => true]);
        }

        // Turnos (globales)
        $turnos = [
            ['Turno Día (08:00–17:00)', '08:00', '17:00', 60, 10, 480],
            ['Turno Obra (07:30–17:30)', '07:30', '17:30', 60, 10, 540],
            ['Turno Mañana (07:00–15:00)', '07:00', '15:00', 45, 10, 435],
        ];
        foreach ($turnos as [$nombre, $ent, $sal, $refr, $tol, $jor]) {
            Turno::firstOrCreate(
                ['nombre' => $nombre],
                ['hora_entrada' => $ent, 'hora_salida' => $sal, 'refrigerio_min' => $refr, 'tolerancia_min' => $tol, 'minutos_jornada' => $jor, 'activo' => true]
            );
        }

        $this->command?->info('Catálogos: '.Area::count().' áreas, '.Cargo::count().' cargos, '.Turno::count().' turnos.');
    }
}
