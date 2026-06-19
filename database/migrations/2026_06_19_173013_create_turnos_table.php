<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de turnos: horarios para derivar tardanzas y horas extra
 * a partir de las marcaciones del biométrico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->time('hora_entrada');
            $table->time('hora_salida');
            $table->unsignedSmallInteger('refrigerio_min')->default(0);
            $table->unsignedSmallInteger('tolerancia_min')->default(0)->comment('Minutos de gracia para tardanza');
            $table->boolean('cruza_medianoche')->default(false)->comment('Turno nocturno');
            $table->unsignedSmallInteger('minutos_jornada')->nullable()->comment('Minutos esperados de trabajo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
