<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Contratistas: pagos por avance de obra (destajo), separado de planilla.
 * Réplica del Excel del cliente: OT con precio pactado + % de avance por corte,
 * pago = precio × % del periodo, factura con IGV. Sin asistencia ni beneficios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratistas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ruc', 15)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('cuenta', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contratista_id')->constrained('contratistas')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('codigo', 30);
            $table->string('producto')->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('precio', 12, 2)->default(0);
            // en_curso | terminada | anulada (string portable MySQL/PostgreSQL)
            $table->string('estado', 20)->default('en_curso');
            $table->timestamps();

            $table->unique(['contratista_id', 'codigo']);
        });

        Schema::create('ot_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('porcentaje', 5, 2); // 0.01 a 100 (en %)
            $table->boolean('pagado')->default(false);
            $table->date('fecha_pago')->nullable();
            $table->string('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['orden_trabajo_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_avances');
        Schema::dropIfExists('ordenes_trabajo');
        Schema::dropIfExists('contratistas');
    }
};
