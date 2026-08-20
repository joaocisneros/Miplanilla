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

    /**
     * La columna se creo con $table->enum(). Cada motor lo implementa distinto:
     * MySQL usa un ENUM y PostgreSQL un varchar con una restriccion CHECK. Hay que
     * tocar los dos, si no en produccion (PostgreSQL) el estado nuevo se rechaza.
     */
    private function cambiarEnum(array $estados): void
    {
        $lista = implode(',', array_map(fn ($e) => "'".$e."'", $estados));
        $motor = DB::getDriverName();

        if ($motor === 'mysql') {
            DB::statement("ALTER TABLE attendance MODIFY estado ENUM({$lista}) NOT NULL DEFAULT 'NORMAL'");

            return;
        }

        if ($motor === 'pgsql') {
            DB::statement('ALTER TABLE attendance DROP CONSTRAINT IF EXISTS attendance_estado_check');
            DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_estado_check CHECK (estado IN ({$lista}))");

            return;
        }

        // SQLite (tests): no admite alterar una restriccion CHECK sin rehacer la
        // tabla. Se deja como esta; los tests corren sobre una base recien migrada.
    }
};
