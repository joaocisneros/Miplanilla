<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Día de descanso semanal "por defecto" del trabajador (1=lunes .. 7=domingo,
     * NULL = sin fijo / domingo implícito). Sirve para prellenar la plantilla de
     * asistencia; el cliente puede corregir el día puntual si ese periodo rotó.
     * Pensado sobre todo para vigilancia (turnos que trabajan los 7 días).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('dia_descanso_fijo')->nullable()->after('turno_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('dia_descanso_fijo');
        });
    }
};
