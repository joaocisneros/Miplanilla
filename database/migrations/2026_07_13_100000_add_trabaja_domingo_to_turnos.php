<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vigilancia trabaja de lunes a domingo (con descanso rotativo): el turno
 * necesita saber si el domingo es día laborable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->boolean('trabaja_domingo')->default(false)->after('hora_salida_sabado');
        });

        DB::table('turnos')->where('nombre', 'LIKE', '%Vigilancia%')->update(['trabaja_domingo' => true]);
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn('trabaja_domingo');
        });
    }
};
