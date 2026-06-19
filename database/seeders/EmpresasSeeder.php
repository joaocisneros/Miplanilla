<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Las dos empresas reales del cliente. RUC provisional — editar en el panel ADMIN
 * con el RUC verdadero cuando lo confirme el cliente.
 */
class EmpresasSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            ['ruc' => '20100000001', 'razon_social' => 'ACS PERU', 'nombre_comercial' => 'ACS PERU'],
            ['ruc' => '20100000002', 'razon_social' => 'ACSA', 'nombre_comercial' => 'ACSA'],
        ];

        foreach ($empresas as $e) {
            $empresa = Empresa::firstOrCreate(['razon_social' => $e['razon_social']], $e);

            // Sede principal por defecto
            Sede::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Sede Principal'],
                ['activo' => true]
            );
        }
    }
}
