<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de cálculo por empresa y campos extra de la "bolsa de movilidad" del cliente
 * (sábados y domingos/feriados), para replicar su Excel:
 *
 *  - 'excel': horas extra, sábados, domingos e incentivos van DENTRO de la movilidad
 *             (no afectos), como hace hoy el cliente.
 *  - 'legal': esos conceptos van afectos (pagan AFP/EsSalud/Renta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->enum('modo_calculo', ['excel', 'legal'])->default('excel')->after('regimen_laboral');
        });

        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            // Montos de la bolsa de movilidad (además de horas extra e incentivo/bono ya existentes).
            $table->decimal('sabado', 10, 2)->default(0)->after('monto_horas');
            $table->decimal('domingo_feriado', 10, 2)->default(0)->after('sabado');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('modo_calculo');
        });
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            $table->dropColumn(['sabado', 'domingo_feriado']);
        });
    }
};
