<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de planilla por empleado: totales clave + desglose completo (json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->decimal('base_afecta', 12, 2)->default(0);
            $table->decimal('total_ingresos', 12, 2)->default(0);
            $table->decimal('pension_total', 12, 2)->default(0);
            $table->decimal('renta_5ta', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);
            $table->decimal('neto', 12, 2)->default(0);

            $table->decimal('essalud', 12, 2)->default(0);
            $table->decimal('sctr_pension', 12, 2)->default(0);
            $table->decimal('sctr_salud', 12, 2)->default(0);
            $table->decimal('vida_ley', 12, 2)->default(0);
            $table->decimal('senati', 12, 2)->default(0);

            $table->json('desglose')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
