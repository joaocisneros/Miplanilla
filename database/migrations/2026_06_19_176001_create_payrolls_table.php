<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilla generada para un periodo y empresa (independiente por empresa para SUNAT/SUNAFIL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->enum('estado', ['borrador', 'calculado', 'cerrado'])->default('borrador');
            $table->decimal('total_ingresos', 14, 2)->default(0);
            $table->decimal('total_descuentos', 14, 2)->default(0);
            $table->decimal('total_neto', 14, 2)->default(0);
            $table->decimal('total_aportes_empleador', 14, 2)->default(0);
            $table->unsignedSmallInteger('cantidad_empleados')->default(0);
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'periodo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
