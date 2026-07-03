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
        DB::statement("ALTER TABLE contracts MODIFY sistema_pensiones ENUM('AFP','ONP','NINGUNO') NULL");

        // Ingreso AFECTO adicional (col. "OTROS PENSIONABLES / INCENTIVOS" del Excel del cliente).
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            if (! Schema::hasColumn('ingresos_adicionales', 'otros_afectos')) {
                $table->decimal('otros_afectos', 10, 2)->default(0)->after('bono');
            }
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE contracts MODIFY sistema_pensiones ENUM('AFP','ONP') NULL");
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            if (Schema::hasColumn('ingresos_adicionales', 'otros_afectos')) {
                $table->dropColumn('otros_afectos');
            }
        });
    }
};
