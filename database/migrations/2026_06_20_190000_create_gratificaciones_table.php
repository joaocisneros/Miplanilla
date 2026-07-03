<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gratificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->enum('tipo', ['julio', 'diciembre']); // Fiestas Patrias / Navidad
            $table->unsignedTinyInteger('meses_computables')->default(0);
            $table->unsignedTinyInteger('dias_computables')->default(0);
            $table->decimal('rem_computable', 12, 2)->default(0);
            $table->decimal('monto', 12, 2)->default(0);                    // gratificación
            $table->decimal('bonificacion_extraordinaria', 12, 2)->default(0); // 9% Ley 30334
            $table->decimal('renta_5ta', 12, 2)->default(0);
            $table->decimal('neto', 12, 2)->default(0);
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'employee_id', 'anio', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gratificaciones');
    }
};
