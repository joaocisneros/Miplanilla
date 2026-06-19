<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derechohabientes (hijos / cónyuge). Base para asignación familiar
 * (hijos < 18 o hasta 24 estudiando) y EsSalud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derechohabientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('tipo', ['hijo', 'conyuge', 'concubino']);
            $table->string('nombres');
            $table->string('numero_documento', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F'])->nullable();
            $table->boolean('estudia')->default(false)->comment('Hijo mayor de edad cursando estudios superiores');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derechohabientes');
    }
};
