<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->enum('periodo', ['mayo', 'noviembre']); // depósitos semestrales
            $table->unsignedTinyInteger('meses_computables')->default(0);
            $table->unsignedTinyInteger('dias_computables')->default(0);
            $table->decimal('rem_computable', 12, 2)->default(0);
            $table->decimal('sexto_gratificacion', 12, 2)->default(0); // 1/6 de la gratificación
            $table->decimal('monto', 12, 2)->default(0);
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'employee_id', 'anio', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts');
    }
};
