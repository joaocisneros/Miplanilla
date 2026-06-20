<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Employee;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Carga los empleados reales extraídos de los Excel de ACS PERU y ACSA
 * (tools/empleados_extraidos.json). Los Excel "FINAL" no traen DNI, así que
 * se asigna un documento provisional TMP-* a completar luego desde el panel.
 */
class EmpleadosRealesSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('tools/empleados_extraidos.json');
        if (! file_exists($path)) {
            $this->command->warn('No existe tools/empleados_extraidos.json — omitido.');
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        foreach ($data as $razonSocial => $empleados) {
            $empresa = Empresa::where('razon_social', $razonSocial)->first();
            if (! $empresa) {
                $this->command->warn("Empresa {$razonSocial} no existe — omitida.");
                continue;
            }
            $sede = Sede::where('empresa_id', $empresa->id)->first();

            foreach ($empleados as $i => $e) {
                $doc = sprintf('TMP-%s-%02d', str($razonSocial)->slug(), $i + 1);

                $empleado = Employee::updateOrCreate(
                    ['numero_documento' => $doc],
                    [
                        'empresa_id' => $empresa->id,
                        'sede_id' => $sede?->id,
                        'apellido_paterno' => $e['pat'] ?: 'SIN',
                        'apellido_materno' => $e['mat'] ?: null,
                        'nombres' => $e['nom'] ?: '(s/n)',
                        'tipo_documento' => 'DNI',
                        'genero' => in_array($e['genero'], ['M', 'F']) ? $e['genero'] : null,
                        'activo' => true,
                    ]
                );

                $empleado->contratos()->updateOrCreate(
                    ['employee_id' => $empleado->id],
                    [
                        'categoria_ocupacional' => $e['categoria'] === 'obrero' ? 'obrero' : 'empleado',
                        'tipo_contrato' => $e['contrato'] ?: null,
                        'fecha_ingreso' => '2026-01-01',
                        'sueldo_basico' => $e['basico'],
                        'percibe_asignacion_familiar' => ($e['asigfam'] ?? 0) > 0,
                        'movilidad' => $e['movilidad'] ?? 0,
                        'sistema_pensiones' => $e['sis'],
                        'afp' => $e['afp'],
                        'tipo_afp' => $e['tipo'],
                        'aporta_sctr' => (bool) $e['sctr'],
                        'aporta_senati' => (bool) $e['senati'],
                        'activo' => true,
                    ]
                );
            }

            $this->command->info("{$razonSocial}: ".count($empleados).' empleados cargados.');
        }
    }
}
