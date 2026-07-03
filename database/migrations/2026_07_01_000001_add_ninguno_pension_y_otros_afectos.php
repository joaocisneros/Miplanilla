<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Permitir "NINGUNO" (pensionista jubilado exonerado de aporte).
        // MySQL usa ENUM real (MODIFY); PostgreSQL usa una restricción CHECK.
        $this->permitirPensiones(['AFP', 'ONP', 'NINGUNO']);

        // Ingreso AFECTO adicional (col. "OTROS PENSIONABLES / INCENTIVOS" del Excel del cliente).
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            if (! Schema::hasColumn('ingresos_adicionales', 'otros_afectos')) {
                $table->decimal('otros_afectos', 10, 2)->default(0)->after('bono');
            }
        });
    }

    public function down(): void
    {
        $this->permitirPensiones(['AFP', 'ONP']);
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            if (Schema::hasColumn('ingresos_adicionales', 'otros_afectos')) {
                $table->dropColumn('otros_afectos');
            }
        });
    }

    /** Ajusta los valores permitidos de contracts.sistema_pensiones según el motor de BD. */
    private function permitirPensiones(array $valores): void
    {
        $driver = DB::getDriverName();
        $lista = "'".implode("','", $valores)."'";

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE contracts MODIFY sistema_pensiones ENUM($lista) NULL");
        } elseif ($driver === 'pgsql') {
            // En PostgreSQL el enum de Laravel es un VARCHAR con una restricción CHECK.
            DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_sistema_pensiones_check');
            DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_sistema_pensiones_check CHECK (sistema_pensiones::text IN ($lista))");
        }
        // sqlite u otros: sin restricción estricta, no se hace nada.
    }
};
