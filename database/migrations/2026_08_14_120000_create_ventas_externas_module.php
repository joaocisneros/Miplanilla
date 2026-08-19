<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendedores_externos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 10)->default('DNI');
            $table->string('numero_documento', 15)->unique();
            $table->string('nombre');
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('vendedor_externo_tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_externo_id')->constrained('vendedores_externos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_pago', 20)->default('mixto'); // fijo, comision, mixto
            $table->decimal('pago_fijo', 12, 2)->default(0);
            $table->string('tipo_comision', 20)->default('porcentaje'); // porcentaje, manual
            $table->decimal('porcentaje_comision', 7, 4)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['vendedor_externo_id', 'empresa_id']);
        });

        Schema::create('periodos_ventas_externas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 20)->default('abierto');
            $table->date('fecha_pago')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'anio', 'mes']);
        });

        Schema::create('pagos_ventas_externas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_venta_externa_id')->constrained('periodos_ventas_externas')->cascadeOnDelete();
            $table->foreignId('vendedor_externo_id')->constrained('vendedores_externos')->restrictOnDelete();
            $table->string('tipo_pago', 20);
            $table->decimal('pago_fijo', 12, 2)->default(0);
            $table->decimal('ventas', 14, 2)->default(0);
            $table->string('tipo_comision', 20)->default('porcentaje');
            $table->decimal('porcentaje_comision', 7, 4)->default(0);
            $table->decimal('comision', 12, 2)->default(0);
            $table->decimal('bonos', 12, 2)->default(0);
            $table->decimal('adelantos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['periodo_venta_externa_id', 'vendedor_externo_id'], 'pve_periodo_vendedor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_ventas_externas');
        Schema::dropIfExists('periodos_ventas_externas');
        Schema::dropIfExists('vendedor_externo_tarifas');
        Schema::dropIfExists('vendedores_externos');
    }
};
