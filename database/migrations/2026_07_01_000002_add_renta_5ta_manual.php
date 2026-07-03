<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            if (! Schema::hasColumn('ingresos_adicionales', 'renta_5ta_manual')) {
                // Retención de Renta 5ta cargada del cálculo externo del cliente.
                // Si está seteada, el motor la usa en lugar de proyectarla.
                $table->decimal('renta_5ta_manual', 10, 2)->nullable()->after('otros_afectos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_adicionales', function (Blueprint $table) {
            if (Schema::hasColumn('ingresos_adicionales', 'renta_5ta_manual')) {
                $table->dropColumn('renta_5ta_manual');
            }
        });
    }
};
