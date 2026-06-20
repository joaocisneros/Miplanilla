<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia derivada por empleado y día (estado + tardanza + horas extra).
 * Se calcula desde marcaciones + turno, o se importa por Excel. No reemplaza
 * la marca cruda; es el resultado consolidado que alimenta la planilla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('fecha');
            $table->enum('estado', [
                'NORMAL', 'FALTA', 'FALTA_JUSTIFICADA', 'VACACIONES', 'LICENCIA',
                'LICENCIA_SIN_GOCE', 'DESCANSO_MEDICO', 'SUBSIDIO', 'FERIADO', 'DESCANSO',
                'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO',
            ])->default('NORMAL');
            $table->time('hora_entrada_real')->nullable();
            $table->time('hora_salida_real')->nullable();
            $table->unsignedSmallInteger('minutos_tarde')->default(0);
            $table->decimal('horas_extra', 5, 2)->default(0);
            $table->boolean('horas_extra_aprobadas')->default(false);
            $table->string('origen')->default('manual')->comment('biometrico/excel/manual');
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'fecha']);
            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
