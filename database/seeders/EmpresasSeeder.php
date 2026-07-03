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
        // Datos reales tomados de los contratos físicos (junio 2026).
        // ACSA MAQUINARIAS sigue con datos por confirmar (no se tiene su contrato aún).
        $empresas = [
            [
                'ruc' => '20518022505',
                'razon_social' => 'ACS PERU E.I.R.L.',
                'nombre_comercial' => 'ACS Perú',
                'direccion' => 'Av. Colectora Industrial N° 115, Santa Anita - Lima - Lima',
                'representante_legal' => 'CISNEROS SUMA, ANDRES JUAN',
                'representante_dni' => '09594305',
                'representante_cargo' => 'Gerente / Titular',
                'regimen_laboral' => 'microempresa',
                'remype_numero' => '0000061816-2009',
                'remype_fecha' => '2009-03-14',
                'giro' => 'Prestación de servicios varios N.C.P.',
            ],
            [
                'ruc' => '20100000002',
                'razon_social' => 'ACSA MAQUINARIAS',
                'nombre_comercial' => 'ACSA Maquinarias',
                'regimen_laboral' => 'general',
            ],
            [
                'ruc' => '20458127400',
                'razon_social' => 'ACS INDUSTRIA METAL MECANICA E.I.R.L.',
                'nombre_comercial' => 'ACS Industrias',
                'direccion' => 'Av. Colectora Industrial N° 115, Santa Anita - Lima - Lima',
                'representante_legal' => 'TORRES OSORIO, VICTOR JAVIER',
                'representante_dni' => '26717007',
                'representante_cargo' => 'Gerente / Titular',
                'regimen_laboral' => 'microempresa',
                'remype_numero' => '0001683243-2019',
                'remype_fecha' => '2020-10-19',
                'giro' => 'Diseño, fabricación y venta de carrocerías metálicas para vehículos',
            ],
        ];

        foreach ($empresas as $e) {
            $empresa = Empresa::updateOrCreate(['ruc' => $e['ruc']], $e);

            // Sede principal por defecto
            Sede::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Sede Principal'],
                ['activo' => true]
            );
        }
    }
}
