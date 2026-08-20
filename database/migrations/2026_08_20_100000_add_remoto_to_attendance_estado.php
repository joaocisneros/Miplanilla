<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega REMOTO a los estados de asistencia. La columna es un ENUM, asi que
     * sin esto el estado aparece en pantalla pero al guardar la base lo rechaza
     * ("Data truncated for column 'estado'").
     *
     * Remoto cuenta como dia trabajado: no entra en los estados que descuentan
     * (FALTA y LICENCIA_SIN_GOCE), asi que paga el dia completo.
     */
    private const ESTADOS = [
        'NORMAL', 'REMOTO', 'FALTA', 'FALTA_JUSTIFICADA', 'VACACIONES',
        'LICENCIA', 'LICENCIA_SIN_GOCE', 'DESCANSO_MEDICO', 'SUBSIDIO',
        'FERIADO', 'DESCANSO', 'TRABAJO_SABADO', 'TRABAJO_DOMINGO', 'TRABAJO_FERIADO',
    ];

    public function up(): void
    {
        $this->cambiarEnum(self::ESTADOS);
    }

    public function down(): void
    {
        // Antes de quitar el valor del ENUM, los registros remotos pasan a NORMAL
        // para no perderlos: ambos cuentan como dia trabajado.
        DB::table('attendance')->where('estado', 'REMOTO')->update(['estado' => 'NORMAL']);

        $this->cambiarEnum(array_values(array_diff(self::ESTADOS, ['REMOTO'])));
    }

    private function cambiarEnum(array $estados): void
    {
        // SQLite (usado en los tests) no tiene ENUM: la columna ya es de texto libre.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $lista = implode(',', array_map(fn ($e) => "'".$e."'", $estados));
        DB::statement("ALTER TABLE attendance MODIFY estado ENUM({$lista}) NOT NULL DEFAULT 'NORMAL'");
    }
};
