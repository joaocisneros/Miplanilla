<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modalidad del trabajador: 'planilla' (empleado 5ta) u 'honorarios' (RxH 4ta).
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'modalidad')) {
                $table->string('modalidad', 20)->default('planilla')->after('activo');
            }
        });

        // ¿Se le retiene el 8% de renta 4ta? (solo aplica a honorarios).
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'retiene_4ta')) {
                $table->boolean('retiene_4ta')->default(true)->after('tiene_vida_ley');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('retiene_4ta');
        });
    }
};
