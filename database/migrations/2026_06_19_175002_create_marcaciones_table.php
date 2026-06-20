<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcaciones crudas (entrada/salida) — INMUTABLES.
 * Provienen del reloj biométrico o de import Excel/CSV. No se editan a mano;
 * las correcciones van por `incidencias` y quedan en audit_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();

            $table->string('codigo_trabajador_origen')->nullable()->comment('Código tal cual del reloj');
            $table->string('codigo_dispositivo_origen')->nullable();
            $table->dateTime('fecha_hora_marca');
            $table->string('zona_horaria')->default('America/Lima');
            $table->dateTime('fecha_hora_recepcion')->nullable();
            $table->enum('tipo', ['entrada', 'salida', 'desconocido'])->default('desconocido');
            $table->string('metodo')->nullable()->comment('huella/rostro/tarjeta/manual');
            $table->string('origen')->default('excel')->comment('excel/csv/api/db/manual');
            $table->string('hash_unico')->unique()->comment('Anti-duplicado en reimportaciones');
            $table->json('raw_payload')->nullable()->comment('Registro original íntegro');
            $table->boolean('procesada')->default(false);
            $table->timestamps();

            $table->index(['empresa_id', 'employee_id', 'fecha_hora_marca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcaciones');
    }
};
