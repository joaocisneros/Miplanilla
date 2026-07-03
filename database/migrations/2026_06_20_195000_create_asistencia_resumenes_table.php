<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia_resumenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->unsignedTinyInteger('quincena')->nullable(); // 1, 2, o null (mensual)
            $table->decimal('dias_trabajados', 5, 2)->default(0);
            $table->unsignedSmallInteger('faltas')->default(0);
            $table->unsignedInteger('tardanza_min')->default(0);
            $table->decimal('horas_extra', 6, 2)->default(0);
            // Informativos (del cuadro resumen del cliente)
            $table->unsignedSmallInteger('sabado')->default(0);
            $table->unsignedSmallInteger('feriados_domingos')->default(0);
            $table->unsignedSmallInteger('vacaciones')->default(0);
            $table->unsignedSmallInteger('licencia')->default(0);
            $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'employee_id', 'anio', 'mes', 'quincena'], 'resumen_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_resumenes');
    }
};
