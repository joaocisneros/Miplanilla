<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos: datos laborales y remunerativos del empleado, versionados.
 * Un empleado puede tener varios contratos en el tiempo (vigencia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();

            $table->string('tipo_contrato')->nullable()->comment('A plazo fijo, indefinido, etc.');
            $table->enum('categoria_ocupacional', ['empleado', 'obrero'])->default('empleado');
            $table->date('fecha_ingreso');
            $table->date('fecha_cese')->nullable();

            // Remuneración
            $table->decimal('sueldo_basico', 10, 2)->default(0);
            $table->boolean('percibe_asignacion_familiar')->default(false);
            $table->decimal('movilidad', 10, 2)->default(0);
            $table->decimal('otros', 10, 2)->default(0);

            // Pensiones
            $table->enum('sistema_pensiones', ['AFP', 'ONP'])->nullable();
            $table->string('afp')->nullable()->comment('INTEGRA, PRIMA, PROFUTURO, HABITAT');
            $table->enum('tipo_afp', ['mixta', 'sueldo'])->nullable()->comment('Comisión flujo o mixta');
            $table->string('codigo_afp')->nullable();
            $table->date('fecha_afiliacion_pension')->nullable();

            // Seguros / aportes
            $table->boolean('aporta_sctr')->default(false);
            $table->boolean('aporta_senati')->default(false);
            $table->boolean('tiene_vida_ley')->default(true);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'fecha_ingreso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
