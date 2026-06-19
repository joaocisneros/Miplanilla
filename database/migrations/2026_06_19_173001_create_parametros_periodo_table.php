<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro: parámetros legales versionados por vigencia.
 * RMV, UIT, asignación familiar, días base de prorrateo.
 * NO se hardcodean: el cálculo siempre lee el registro vigente al periodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_periodo', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->decimal('uit', 10, 2)->nullable()->comment('UIT del año (por confirmar 2026)');
            $table->decimal('rmv', 10, 2)->comment('Remuneración Mínima Vital');
            $table->decimal('asignacion_familiar', 10, 2)->comment('10% de la RMV');
            $table->unsignedTinyInteger('dias_base')->default(30)->comment('Días para prorrateo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('confirmado')->default(false)->comment('Confirmado por contador');
            $table->string('fuente')->nullable();
            $table->timestamps();

            $table->index(['vigente_desde', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_periodo');
    }
};
