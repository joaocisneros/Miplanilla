<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario de feriados: el sistema los usa para avisar en la asistencia
 * diaria y pre-llenar las plantillas Excel con "Feriado" en vez de horas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('nombre');
            $table->timestamps();
        });

        // Feriados nacionales del Perú 2026 (los movibles ya resueltos para 2026).
        $feriados2026 = [
            '2026-01-01' => 'Año Nuevo',
            '2026-04-02' => 'Jueves Santo',
            '2026-04-03' => 'Viernes Santo',
            '2026-05-01' => 'Día del Trabajo',
            '2026-06-07' => 'Batalla de Arica y Día de la Bandera',
            '2026-06-29' => 'San Pedro y San Pablo',
            '2026-07-23' => 'Día de la Fuerza Aérea del Perú',
            '2026-07-28' => 'Fiestas Patrias',
            '2026-07-29' => 'Fiestas Patrias',
            '2026-08-06' => 'Batalla de Junín',
            '2026-08-30' => 'Santa Rosa de Lima',
            '2026-10-08' => 'Combate de Angamos',
            '2026-11-01' => 'Día de Todos los Santos',
            '2026-12-08' => 'Inmaculada Concepción',
            '2026-12-09' => 'Batalla de Ayacucho',
            '2026-12-25' => 'Navidad',
        ];
        $now = now();
        foreach ($feriados2026 as $fecha => $nombre) {
            DB::table('feriados')->insertOrIgnore([
                'fecha' => $fecha, 'nombre' => $nombre,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
