<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro: tasas de AFP / ONP versionadas por vigencia.
 * Editable desde el panel ADMIN. El cálculo lee la tasa vigente al periodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_afp', function (Blueprint $table) {
            $table->id();
            $table->string('afp')->comment('INTEGRA, PRIMA, PROFUTURO, HABITAT, ONP');
            $table->enum('tipo', ['mixta', 'sueldo', 'onp'])->comment('Comisión flujo (sueldo) o mixta');
            $table->decimal('aporte_obligatorio', 6, 4)->default(0.10)->comment('Fondo de pensiones (10% AFP / 13% ONP)');
            $table->decimal('comision_flujo', 6, 4)->default(0)->comment('Comisión sobre remuneración');
            $table->decimal('comision_saldo', 6, 4)->default(0)->comment('Comisión anual sobre saldo (mixta)');
            $table->decimal('prima_seguro', 6, 4)->default(0)->comment('Prima de seguro de invalidez/sobrevivencia');
            $table->decimal('rem_max_asegurable', 10, 2)->nullable()->comment('Tope para prima de seguro');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('confirmado')->default(false);
            $table->string('fuente')->nullable()->comment('SBS u otra');
            $table->timestamps();

            $table->index(['afp', 'tipo', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_afp');
    }
};
