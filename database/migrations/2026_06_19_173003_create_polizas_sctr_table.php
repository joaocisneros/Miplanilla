<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro: pólizas SCTR (Seguro Complementario de Trabajo de Riesgo).
 * NO es tasa universal: depende de aseguradora, riesgo y vigencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polizas_sctr', function (Blueprint $table) {
            $table->id();
            $table->string('aseguradora')->nullable();
            $table->string('actividad_riesgo')->nullable()->comment('Actividad/área cubierta');
            $table->decimal('tasa_salud', 6, 4)->default(0)->comment('% SCTR Salud');
            $table->decimal('tasa_pension', 6, 4)->default(0)->comment('% SCTR Pensión');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('confirmado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polizas_sctr');
    }
};
