<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de conceptos de planilla con banderas de naturaleza remunerativa.
 * El motor arma cada base sumando SOLO los conceptos marcados como afectos.
 * (Ver ESPECIFICACION_PLANILLA.md §9.2)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conceptos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->enum('tipo', ['ingreso', 'descuento', 'aporte_empleador']);
            $table->boolean('es_remunerativo')->default(true);
            $table->boolean('afecto_afp_onp')->default(false);
            $table->boolean('afecto_essalud')->default(false);
            $table->boolean('afecto_sctr')->default(false);
            $table->boolean('afecto_5ta')->default(false);
            $table->boolean('afecta_descuento_inasistencia')->default(false)->comment('Entra en base de descuento por falta/tardanza');
            $table->boolean('evalua_regularidad')->default(false)->comment('Aplica regla 3 de 6 meses (CTS)');
            $table->string('estrategia_calculo')->nullable()->comment('Clase Strategy que lo calcula');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos');
    }
};
