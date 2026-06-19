<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro: pólizas de Seguro Vida Ley (DL 688).
 * NO es 0.54% universal: prima según póliza/base/vigencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polizas_vida_ley', function (Blueprint $table) {
            $table->id();
            $table->string('aseguradora')->nullable();
            $table->decimal('tasa', 6, 4)->default(0)->comment('Prima como % de la base');
            $table->string('base')->nullable()->comment('Base sobre la que se calcula');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('confirmado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polizas_vida_ley');
    }
};
