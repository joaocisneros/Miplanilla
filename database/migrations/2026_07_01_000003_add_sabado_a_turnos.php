<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            if (! Schema::hasColumn('turnos', 'trabaja_sabado')) {
                $table->boolean('trabaja_sabado')->default(true)->after('minutos_jornada')
                    ->comment('Si el turno labora los sábados');
            }
            if (! Schema::hasColumn('turnos', 'hora_salida_sabado')) {
                $table->time('hora_salida_sabado')->nullable()->after('trabaja_sabado')
                    ->comment('Hora de salida especial para sábados (ej. medio día)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn(['trabaja_sabado', 'hora_salida_sabado']);
        });
    }
};
