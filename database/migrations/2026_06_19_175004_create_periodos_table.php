<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periodos de planilla (quincenal o mensual) por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->unsignedTinyInteger('quincena')->nullable()->comment('1, 2 o null=mensual');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('fecha_pago')->nullable();
            $table->enum('estado', ['borrador', 'calculado', 'cerrado'])->default('borrador');
            $table->timestamps();

            $table->unique(['empresa_id', 'anio', 'mes', 'quincena']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
