<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adelantos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('tipo', ['adelanto', 'prestamo']);
            $table->unsignedSmallInteger('anio'); // periodo en que se descuenta
            $table->unsignedTinyInteger('mes');
            $table->decimal('monto', 12, 2); // monto a descontar ese mes (adelanto completo o cuota)
            $table->string('concepto')->nullable();
            $table->string('grupo')->nullable();          // agrupa las cuotas de un mismo préstamo
            $table->unsignedTinyInteger('cuota_num')->nullable();
            $table->unsignedTinyInteger('cuotas_total')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'employee_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adelantos');
    }
};
