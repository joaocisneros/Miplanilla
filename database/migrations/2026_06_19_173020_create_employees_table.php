<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empleados: datos personales (de la Ficha de Registro de Personal).
 * Los datos contractuales/remunerativos van en `contracts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();

            // Identidad
            $table->string('apellido_paterno');
            $table->string('apellido_materno')->nullable();
            $table->string('nombres');
            $table->string('tipo_documento')->default('DNI');
            $table->string('numero_documento', 20)->unique();
            $table->string('ruc', 11)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F'])->nullable();
            $table->string('estado_civil')->nullable();
            $table->string('lugar_nacimiento')->nullable();
            $table->string('profesion')->nullable();

            // Contacto / domicilio
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            $table->string('distrito')->nullable();
            $table->string('provincia')->nullable();
            $table->string('departamento')->nullable();
            $table->string('tipo_vivienda')->nullable();
            $table->string('nivel_educativo')->nullable();

            // Datos bancarios (para el pago del neto)
            $table->string('banco')->nullable();
            $table->string('cuenta_corriente')->nullable();
            $table->string('cuenta_ahorros')->nullable();
            $table->string('cci')->nullable();

            // Contacto de emergencia
            $table->string('emergencia_nombre')->nullable();
            $table->string('emergencia_telefono')->nullable();
            $table->string('emergencia_parentesco')->nullable();

            // Vínculo con biométrico y sistema
            $table->string('codigo_biometrico')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
