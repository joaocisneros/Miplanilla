<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingresos adicionales por trabajador y periodo, cargados/aprobados por el supervisor:
 * horas extra (sábado/domingo/feriado/sobretiempo) y bonos/incentivos. Son AFECTOS
 * (pagan AFP/EsSalud/Renta). Solo lo que se registra aquí = lo aprobado = lo que se paga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos_adicionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->unsignedTinyInteger('quincena')->nullable(); // 1, 2 o null (mes completo)

            // Horas extra registradas: horas + minutos (lo que marcó/reportó).
            $table->decimal('horas', 6, 2)->default(0);
            $table->unsignedSmallInteger('minutos')->default(0);
            // Aprobación del supervisor: solo si está aprobado se paga la hora extra.
            $table->boolean('aprobado')->default(false);
            // Monto a pagar por las horas (afecto). Se calcula/ingresa al aprobar.
            $table->decimal('monto_horas', 10, 2)->default(0);
            // Bono / incentivo aprobado (afecto).
            $table->decimal('bono', 10, 2)->default(0);

            $table->string('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'employee_id', 'anio', 'mes', 'quincena'], 'adicional_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_adicionales');
    }
};
