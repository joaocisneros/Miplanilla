<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro: escala progresiva de renta de 5ta categoría, versionada.
 * Tramos en UIT con su tasa (8/14/17/20/30%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramos_renta_5ta', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('orden');
            $table->decimal('desde_uit', 6, 2)->comment('Límite inferior en UIT');
            $table->decimal('hasta_uit', 6, 2)->nullable()->comment('Límite superior en UIT (null = sin tope)');
            $table->decimal('tasa', 5, 4)->comment('Tasa del tramo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['vigente_desde', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramos_renta_5ta');
    }
};
